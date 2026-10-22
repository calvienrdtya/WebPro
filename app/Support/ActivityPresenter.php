<?php

namespace App\Support;

use App\Models\Setting;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Menerjemahkan daftar perubahan Log Aktivitas untuk admin: nama kolom ("cogs" -> "HPP"), ya/tidak, Rupiah, label status,
 * tanggal, dan ID menjadi nama ("product_id 5" -> "Netflix Premium"). Data tersimpan tidak diubah, jadi catatan lama
 * ikut tampil rapi. Teks label di config/activity.php (fields, money, references, values).
 */
class ActivityPresenter
{
    /** @var array<string, class-string<Model>> alias tipe (mis. order) => kelas model */
    private array $classes;

    /** Perubahan yang sedang diterjemahkan adalah saklar pengaturan (nilai 1/0 tampil Ya/Tidak). */
    private bool $settingIsSwitch = false;

    /** @var array<string, array<string, string>> nama yang dirujuk: "Model@kolom" => [ID => nama] */
    private array $names = [];

    public function __construct()
    {
        $this->classes = collect(array_keys(config('activity.models')))->mapWithKeys(fn ($c) => [Activity::alias($c) => $c])->all();
    }

    /**
     * @param  array<int, array>  $logsChanges  kolom changes dari beberapa baris sekaligus (satu halaman)
     * @return array<int, array> urutan sama; tiap perubahan berisi rows: [[kolom, sebelum, sesudah], ...]
     */
    public function present(array $logsChanges): array
    {
        $this->loadReferences($logsChanges);

        return array_map(fn (array $changes) => array_map(fn (array $c) => isset($c['more']) ? $c : [
            'model' => $c['model'],
            'label' => ($c['type'] ?? null) === 'setting' ? $this->settingLabel((string) $c['label']) : $c['label'],
            'event' => $c['event'],
            'rows' => $this->rows($c),
        ], $changes), $logsChanges);
    }

    /** "backup.auto.days" -> "Backup otomatis setiap (hari)"; kunci tak dikenal memakai awalan, mis. "Tampilan toko: hero.title". */
    private function settingLabel(string $key): string
    {
        if ($label = config('activity.settings')[$key] ?? null) {
            return $label;
        }
        foreach (config('activity.setting_prefixes') as $prefix => $label) {
            if (str_starts_with($key, $prefix)) {
                return $label.substr($key, strlen($prefix));
            }
        }

        return $key;
    }

    private function rows(array $change): array
    {
        $key = (string) ($change['label'] ?? '');
        $this->settingIsSwitch = ($change['type'] ?? null) === 'setting'
            && (str_ends_with($key, 'enabled') || str_starts_with($key, 'notify.email.type.'));
        $class = $this->classes[$change['type']] ?? null;
        $casts = $class ? rescue(fn () => (new $class)->getCasts(), [], false) : [];
        $rows = [];

        foreach ($change['fields'] ?? [] as $field => [$before, $after]) {
            if (in_array($field, config('activity.hidden_fields'), true)) {
                continue;
            }
            $rows[] = [
                config('activity.fields')[$field] ?? $field,
                $this->value($class, $field, $before, $casts[$field] ?? null),
                $this->value($class, $field, $after, $casts[$field] ?? null),
            ];
        }

        return $rows;
    }

    private function value(?string $class, string $field, mixed $value, ?string $cast): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Nilai rahasia sudah disamarkan saat dicatat.
        if ($value === '(rahasia)' || $value === '(diubah)') {
            return $value;
        }

        if ($ref = config('activity.references')[$field] ?? null) {
            return $this->names[$ref[0].'@'.$ref[1]][(string) $value] ?? '#'.$value;
        }
        if (in_array($field, config('activity.money'), true) && is_numeric($value)) {
            return 'Rp '.number_format((float) $value, 0, ',', '.');
        }
        if ($label = config("activity.values.{$class}.{$field}.{$value}")) {
            return $label;
        }
        // Saklar di tabel settings disimpan "1"/"0".
        if ($class === Setting::class && $field === 'value' && $this->settingIsSwitch && in_array((string) $value, ['0', '1'], true)) {
            return $value === '1' || $value === 1 ? 'Ya' : 'Tidak';
        }

        try {
            if ($cast && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
                $int = (new \ReflectionEnum($cast))->getBackingType()?->getName() === 'int';
                $case = $cast::tryFrom($int ? (int) $value : (string) $value);

                return $case && method_exists($case, 'label') ? $case->label() : (string) $value;
            }

            return match (true) {
                in_array($cast, ['boolean', 'bool'], true) => in_array($value, [true, 1, '1', 'true'], true) ? 'Ya' : 'Tidak',
                in_array($cast, ['date', 'immutable_date'], true) => Carbon::parse($value)->locale('id')->translatedFormat('j M Y'),
                in_array($cast, ['datetime', 'immutable_datetime'], true) => Carbon::parse($value)->locale('id')->translatedFormat('j M Y H.i'),
                default => (string) $value,
            };
        } catch (Throwable) {
            return (string) $value;
        }
    }

    /** Ambil nama semua data yang dirujuk di halaman ini sekaligus (satu query per model). */
    private function loadReferences(array $logsChanges): void
    {
        $ids = [];
        foreach ($logsChanges as $changes) {
            foreach ($changes as $c) {
                foreach ($c['fields'] ?? [] as $field => $pair) {
                    if ($ref = config('activity.references')[$field] ?? null) {
                        foreach ($pair as $v) {
                            if (is_numeric($v)) {
                                $ids[$ref[0]][$ref[1]][] = (int) $v;
                            }
                        }
                    }
                }
            }
        }

        foreach ($ids as $class => $columns) {
            foreach ($columns as $column => $list) {
                $query = $class::query();
                if (method_exists($class, 'bootSoftDeletes')) {
                    $query->withTrashed();
                }
                foreach ($query->whereKey(array_unique($list))->get([(new $class)->getKeyName(), $column]) as $m) {
                    $this->names[$class.'@'.$column][(string) $m->getKey()] = (string) $m->getAttribute($column);
                }
            }
        }
    }
}
