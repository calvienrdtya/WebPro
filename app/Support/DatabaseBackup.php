<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Backup database ke file .sql.gz di storage/app/private/backups (tidak bisa dibuka dari web; diunduh lewat admin).
 *
 * Murni PHP (tanpa mysqldump, yang sering tidak tersedia di hosting): struktur tiap tabel + isinya dalam INSERT
 * bertahap, ditulis langsung ke gzip agar memori tetap kecil. Hanya tabel database aplikasi (bukan database lain di
 * server yang sama). File bisa diimpor lewat phpMyAdmin (Import) atau `mysql < file.sql`.
 */
class DatabaseBackup
{
    /** Tabel yang hanya disalin strukturnya: data sementara atau rahasia jangka pendek. */
    public const SKIP_DATA = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'password_reset_tokens'];

    private const FILE = '/^backup-(otomatis|manual)-(\d{4}-\d{2}-\d{2}-\d{6})\.sql\.gz$/';

    private const END_MARKER = '-- Backup selesai';

    public function __construct(private ?string $dir = null)
    {
        $this->dir ??= storage_path('app/private/backups');
    }

    /** Daftar backup, terbaru dulu. */
    public function all(): array
    {
        return collect(is_dir($this->dir) ? scandir($this->dir) : [])
            ->filter(fn ($name) => preg_match(self::FILE, $name))
            ->map(function ($name) {
                preg_match(self::FILE, $name, $m);

                return [
                    'name' => $name,
                    'type' => $m[1],
                    'size' => filesize($this->dir.'/'.$name),
                    'created_at' => Carbon::createFromFormat('Y-m-d-His', $m[2])->toIso8601String(),
                ];
            })
            ->sortByDesc('name')
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function path(string $name): ?string
    {
        $path = $this->dir.'/'.$name;

        return preg_match(self::FILE, $name) && is_file($path) ? $path : null;
    }

    public function lastAt(): ?Carbon
    {
        $last = $this->all()[0] ?? null;

        return $last ? Carbon::parse($last['created_at']) : null;
    }

    /** Backup otomatis sudah waktunya: belum pernah ada, atau backup terakhir sudah N hari (toleransi 1 jam untuk jadwal cron). */
    public function due(): bool
    {
        $last = $this->lastAt();

        return $last === null || $last->lte(now()->subDays(Setting::backupIntervalDays())->addHour());
    }

    /**
     * Buat backup baru lalu hapus yang lama melebihi jumlah simpan. File setengah jadi dihapus bila gagal.
     *
     * @return array{name: string, size: int, tables: int, rows: int}
     */
    public function create(string $type = 'manual'): array
    {
        if (! is_dir($this->dir) && ! mkdir($this->dir, 0755, true) && ! is_dir($this->dir)) {
            throw new RuntimeException('Folder backup tidak bisa dibuat: '.$this->dir);
        }
        @set_time_limit(0);

        $name = 'backup-'.$type.'-'.now()->format('Y-m-d-His').'.sql.gz';
        $path = $this->dir.'/'.$name;
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('File backup tidak bisa ditulis: '.$path);
        }

        try {
            [$tables, $rows] = $this->dump($gz);
            gzwrite($gz, "\n".self::END_MARKER."\n");
        } catch (Throwable $e) {
            gzclose($gz);
            @unlink($path);
            throw $e;
        }
        gzclose($gz);

        $this->prune(Setting::backupKeep());

        return ['name' => $name, 'size' => filesize($path), 'tables' => $tables, 'rows' => $rows];
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && @unlink($path);
    }

    /** Simpan hanya $keep backup terbaru. */
    public function prune(int $keep): int
    {
        $deleted = 0;
        foreach (array_slice($this->all(), max(1, $keep)) as $old) {
            $deleted += $this->delete($old['name']) ? 1 : 0;
        }

        return $deleted;
    }

    /** File utuh: diakhiri penanda selesai (backup yang terputus tidak punya penanda ini). */
    public function isComplete(string $name): bool
    {
        $path = $this->path($name);
        if ($path === null) {
            return false;
        }
        $tail = '';
        $gz = gzopen($path, 'rb');
        while (! gzeof($gz)) {
            $tail = substr($tail.gzread($gz, 65536), -200);
        }
        gzclose($gz);

        return str_contains($tail, self::END_MARKER);
    }

    /** @return array{0: int, 1: int} jumlah tabel dan baris */
    private function dump($gz): array
    {
        $db = DB::connection();
        $mysql = $db->getDriverName() === 'mysql';
        $pdo = $db->getPdo();
        $q = fn (string $id) => $mysql ? '`'.str_replace('`', '``', $id).'`' : '"'.str_replace('"', '""', $id).'"';

        // MySQL: hanya database aplikasi; tanpa nama skema, getTables mengembalikan tabel semua database di server.
        $tables = $mysql
            ? Schema::getTableListing($db->getDatabaseName(), false)
            : array_values(array_filter(Schema::getTableListing(null, false), fn ($t) => ! str_starts_with($t, 'sqlite_')));
        sort($tables);

        gzwrite($gz, "-- Backup database ProAkses\n-- Dibuat: ".now()->toDateTimeString().' ('.config('app.timezone').")\n-- Tabel: ".count($tables)."\n\n");
        if ($mysql) {
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        }

        $rows = 0;
        foreach ($tables as $table) {
            $create = $mysql
                ? array_values((array) $db->selectOne('SHOW CREATE TABLE '.$q($table)))[1]
                : $db->selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$table])->sql;

            gzwrite($gz, "-- Tabel {$table}\nDROP TABLE IF EXISTS {$q($table)};\n{$create};\n\n");
            if (in_array($table, self::SKIP_DATA, true)) {
                continue;
            }

            $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'] ?? null;
            $query = DB::table($table);
            foreach ($primary ?? Schema::getColumnListing($table) as $column) {
                $query->orderBy($column);
            }

            $batch = [];
            foreach ($query->lazy(500) as $row) {
                $row = (array) $row;
                $batch[] = '('.implode(',', array_map(fn ($v) => match (true) {
                    $v === null => 'NULL',
                    is_bool($v) => $v ? '1' : '0',
                    is_int($v), is_float($v) => (string) $v,
                    default => $pdo->quote((string) $v),
                }, $row)).')';
                $rows++;

                if (count($batch) === 100) {
                    $this->insert($gz, $q($table), array_map($q, array_keys($row)), $batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $this->insert($gz, $q($table), array_map($q, array_keys($row)), $batch);
            }
            gzwrite($gz, "\n");
        }

        if ($mysql) {
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        }

        return [count($tables), $rows];
    }

    private function insert($gz, string $table, array $columns, array $values): void
    {
        gzwrite($gz, "INSERT INTO {$table} (".implode(',', $columns).") VALUES\n".implode(",\n", $values).";\n");
    }
}
