<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pembaca file log Laravel di storage/logs untuk menu admin Log Sistem.
 *
 * File dibaca baris demi baris (tidak dimuat utuh ke memori); file yang lebih besar dari proakses.logs.max_read_mb
 * hanya dibaca bagian akhirnya. Teks yang ditampilkan disamarkan dulu (password, token, kunci dari config).
 */
class SystemLog
{
    /** Kelompok level untuk filter dan badge. */
    public const LEVELS = [
        'error' => ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'],
        'warning' => ['WARNING'],
        'info' => ['NOTICE', 'INFO'],
        'debug' => ['DEBUG'],
    ];

    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})[^\]]*\] ([\w-]+)\.([A-Z]+): /';

    /** Pola nama file log (juga dipakai sebagai batasan parameter rute). */
    public const FILE_PATTERN = 'laravel(?:-\d{4}-\d{2}-\d{2})?\.log';

    private const FILE = '/^laravel(?:-(\d{4}-\d{2}-\d{2}))?\.log$/';

    /** Batas teks satu entri yang disimpan saat membaca (byte); sisanya diabaikan. */
    private const ENTRY_CAP = 262144;

    private const NEW_ERRORS_CACHE = 'logs.system.new_errors';

    private ?array $secrets = null;

    public function __construct(private ?string $dir = null)
    {
        $this->dir ??= storage_path('logs');
    }

    public static function levelGroup(string $level): string
    {
        foreach (self::LEVELS as $group => $levels) {
            if (in_array($level, $levels, true)) {
                return $group;
            }
        }

        return 'info';
    }

    /** Daftar file log, terbaru dulu. `laravel.log` (tanpa tanggal) adalah file lama sebelum log harian. */
    public function files(): array
    {
        return collect(is_dir($this->dir) ? scandir($this->dir) : [])
            ->filter(fn ($name) => preg_match(self::FILE, $name) && is_file($this->dir.'/'.$name))
            ->map(function ($name) {
                preg_match(self::FILE, $name, $m);

                return [
                    'name' => $name,
                    'date' => $m[1] ?? null,
                    'size' => filesize($this->dir.'/'.$name),
                    'modified_at' => Carbon::createFromTimestamp(filemtime($this->dir.'/'.$name))->toIso8601String(),
                ];
            })
            ->sortByDesc(fn ($f) => [$f['date'] !== null, $f['date'] ?? '', $f['modified_at']])
            ->values()
            ->all();
    }

    /** Path aman untuk nama file dari request; null bila nama tidak sah atau file tidak ada. */
    public function path(string $name): ?string
    {
        if (! preg_match(self::FILE, $name)) {
            return null;
        }
        $path = $this->dir.'/'.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * Entri satu file, terbaru dulu, dengan filter kelompok level dan kata kunci.
     *
     * @return array{entries: array, total: int, counts: array<string, int>, partial: bool}
     */
    public function read(string $name, ?string $level, ?string $q, int $page, int $perPage): array
    {
        $counts = array_fill_keys(array_keys(self::LEVELS), 0);
        $keep = max(1, $page) * $perPage;
        $buffer = [];
        $total = 0;
        $q = $q !== null && trim($q) !== '' ? mb_strtolower(trim($q)) : null;

        $partial = $this->each($name, function (array $entry) use (&$counts, &$buffer, &$total, $keep, $level, $q) {
            if ($q !== null && ! str_contains(mb_strtolower($entry['text']), $q)) {
                return;
            }
            $group = self::levelGroup($entry['level']);
            $counts[$group]++;
            if ($level !== null && $level !== $group) {
                return;
            }
            $total++;
            $buffer[] = $this->summary($entry);
            if (count($buffer) > $keep) {
                array_shift($buffer);
            }
        });

        // Buffer berisi $keep entri cocok terakhir (urutan file = lama ke baru); ambil potongan halaman dari belakang.
        $newest = array_reverse($buffer);
        $entries = array_slice($newest, (max(1, $page) - 1) * $perPage, $perPage);

        return ['entries' => $entries, 'total' => $total, 'counts' => $counts, 'partial' => $partial];
    }

    /** Detail satu entri berdasarkan posisi byte awalnya di file. */
    public function entry(string $name, int $offset): ?array
    {
        $path = $this->path($name);
        if ($path === null || $offset < 0 || $offset >= filesize($path)) {
            return null;
        }

        $h = fopen($path, 'rb');
        fseek($h, $offset);
        $first = fgets($h);
        if ($first === false || ! preg_match(self::HEADER, $first)) {
            fclose($h);

            return null;
        }
        $text = $first;
        while (($line = fgets($h)) !== false && ! preg_match(self::HEADER, $line)) {
            if (strlen($text) < self::ENTRY_CAP) {
                $text .= $line;
            }
        }
        fclose($h);

        return $this->detail($this->parse($text, $offset));
    }

    /**
     * Jumlah error (ERROR ke atas) di log harian sejak admin terakhir membuka Log Sistem. Disimpan di cache 1 menit
     * karena dipanggil di setiap halaman admin (badge sidebar).
     */
    public function newErrorCount(): int
    {
        return (int) Cache::remember(self::NEW_ERRORS_CACHE, 60, function () {
            $seen = Setting::get(Setting::LOG_SYSTEM_SEEN_AT);
            $since = $seen ? Carbon::parse($seen) : null;
            $count = 0;

            foreach ($this->files() as $file) {
                if ($file['date'] === null || ($since && $file['date'] < $since->toDateString())) {
                    continue;
                }
                $this->each($file['name'], function (array $entry) use (&$count, $since) {
                    if (self::levelGroup($entry['level']) === 'error' && (! $since || Carbon::parse($entry['datetime'])->gt($since))) {
                        $count++;
                    }
                }, headersOnly: true);
            }

            return $count;
        });
    }

    public function markSeen(): void
    {
        Setting::put(Setting::LOG_SYSTEM_SEEN_AT, now()->toDateTimeString());
        Cache::forget(self::NEW_ERRORS_CACHE);
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);
        Cache::forget(self::NEW_ERRORS_CACHE);

        return $path !== null && @unlink($path);
    }

    /** Hapus log harian yang tanggalnya lebih lama dari masa simpan. File laravel.log lama tidak disentuh. */
    public function prune(int $days): array
    {
        $cutoff = now()->subDays($days)->toDateString();
        $deleted = [];
        foreach ($this->files() as $file) {
            if ($file['date'] !== null && $file['date'] < $cutoff && $this->delete($file['name'])) {
                $deleted[] = $file['name'];
            }
        }

        return $deleted;
    }

    /** Samarkan nilai rahasia sebelum teks log ditampilkan di admin. */
    public function redact(string $text): string
    {
        foreach ($this->secrets() as $secret) {
            $text = str_replace($secret, '[disamarkan]', $text);
        }

        // Parameter rahasia di URL.
        $text = preg_replace('/([?&](?:token|signature|code|password|key)=)[^&\s"\']+/i', '$1[disamarkan]', $text);
        // "password":"x", password=x, 'api_key' => 'x', dan sejenisnya (termasuk pin akun). Kutip mengikuti nilai aslinya.
        $key = '(?:[\w.-]*(?:password|passwd|secret|token|api_?key|authorization|cookie|credential)s?|(?:[\w-]*_)?pin)';
        $text = preg_replace_callback(
            '/(["\']?\b'.$key.'["\']?\s*(?:=>|:|=)\s*)(?!\[disamarkan\])("(?:[^"\\\\]|\\\\.)*"|\'[^\']*\'|[^\s,}\]&)]+)/i',
            fn ($m) => $m[1].(in_array($m[2][0], ['"', "'"], true) ? $m[2][0].'[disamarkan]'.$m[2][0] : '[disamarkan]'),
            $text,
        );

        return preg_replace('/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/', '[hash disamarkan]', $text);
    }

    /**
     * Panggil $fn untuk setiap entri (urutan file). Mengembalikan true bila file terlalu besar dan hanya bagian akhirnya dibaca.
     */
    private function each(string $name, callable $fn, bool $headersOnly = false): bool
    {
        $path = $this->path($name);
        if ($path === null) {
            return false;
        }

        $h = fopen($path, 'rb');
        $max = (int) round((float) config('proakses.logs.max_read_mb') * 1024 * 1024);
        $partial = filesize($path) > $max;
        if ($partial) {
            fseek($h, -$max, SEEK_END);
            fgets($h); // buang baris yang terpotong
        }

        $current = null;
        while (true) {
            $offset = ftell($h);
            $line = fgets($h);
            $isHeader = $line !== false && preg_match(self::HEADER, $line);

            if (($line === false || $isHeader) && $current !== null) {
                $fn($headersOnly ? $current : $this->parse($current['text'], $current['offset']));
                $current = null;
            }
            if ($line === false) {
                break;
            }
            if ($isHeader) {
                if ($headersOnly) {
                    preg_match(self::HEADER, $line, $m);
                    $current = ['datetime' => $m[1], 'level' => $m[3], 'text' => '', 'offset' => $offset];
                } else {
                    $current = ['text' => $line, 'offset' => $offset];
                }
            } elseif ($current !== null && ! $headersOnly && strlen($current['text']) < self::ENTRY_CAP) {
                $current['text'] .= $line;
            }
        }
        fclose($h);

        return $partial;
    }

    /** Pecah teks mentah satu entri menjadi bagian-bagiannya. Format: [waktu] env.LEVEL: pesan {konteks} {extra}. */
    private function parse(string $text, int $offset): array
    {
        preg_match(self::HEADER, $text, $m);
        $body = rtrim(substr($text, strlen($m[0])));
        $firstLine = strtok($body, "\n");
        $exception = null;
        $trace = null;
        $context = null;
        $extra = null;

        // Exception: konteks berisi "exception":"[object] (Kelas(code: N): pesan at file:baris)" lalu [stacktrace] multi baris.
        $exPos = strpos($firstLine, '"exception":"[object] ');
        if ($exPos !== false) {
            $start = strrpos(substr($firstLine, 0, $exPos), ' {') ?: $exPos;
            $message = substr($firstLine, 0, $start);
            // Pesan exception bisa multi baris (mis. SQL); bagian ini selalu berakhir tepat sebelum [stacktrace].
            $head = ($t = strpos($body, "\n[stacktrace]")) !== false ? substr($body, 0, $t) : $firstLine;
            if (preg_match('/"exception":"\[object\] \(([^(]+)\(code: ([^)]*)\): (.*) at (.+):(\d+)\)$/s', $head, $em)) {
                $exception = ['class' => $this->unescape($em[1]), 'code' => $em[2], 'message' => $this->unescape($em[3]), 'file' => $this->unescape($em[4]), 'line' => (int) $em[5]];
            }
            // Kunci konteks lain bisa ada sebelum "exception" (baris pertama) atau sesudahnya (baris penutup `","k":v}`).
            $context = json_decode('{'.rtrim(substr($firstLine, $start + 2, $exPos - $start - 2), ',').'}', true) ?: [];
            if (($t = strpos($body, "[stacktrace]\n")) !== false) {
                $trace = substr($body, $t + 13);
                // Baris penutup: `"` + sisa konteks + `}` lalu extra (JSON) di baris yang sama.
                if (preg_match('/\n"(,[^\n]*?)?\}(?: (\{[^\n]*\}))?\s*$/', $trace, $tail, PREG_OFFSET_CAPTURE)) {
                    $context += json_decode('{'.substr($tail[1][0] ?? '', 1).'}', true) ?: [];
                    $extra = isset($tail[2]) ? json_decode($tail[2][0], true) : null;
                    $trace = substr($trace, 0, $tail[0][1]);
                }
                $trace = $this->unescape($trace);
            }
        } else {
            [$message, $context, $extra] = $this->splitJson($body);
        }

        return [
            'offset' => $offset,
            'datetime' => str_replace('T', ' ', $m[1]),
            'env' => $m[2],
            'level' => $m[3],
            'message' => trim($message),
            'exception' => $exception,
            'trace' => $trace,
            'context' => $context,
            'extra' => is_array($extra) ? $extra : null,
            'text' => $text,
        ];
    }

    /** Pisahkan {konteks} dan {extra} JSON di akhir pesan satu baris (mana yang ada). */
    private function splitJson(string $body): array
    {
        $found = [];
        $rest = $body;
        for ($i = 0; $i < 2; $i++) {
            $end = substr($rest, -1);
            if ($end !== '}' && $end !== ']') {
                break;
            }
            $hit = false;
            $pos = strlen($rest);
            while (($pos = strrpos(substr($rest, 0, $pos), ' ')) !== false) {
                $candidate = substr($rest, $pos + 1);
                if (($candidate[0] === '{' || $candidate[0] === '[') && is_array($decoded = json_decode($candidate, true))) {
                    array_unshift($found, $decoded);
                    $rest = substr($rest, 0, $pos);
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                break;
            }
        }

        // Dengan dua JSON: pertama konteks, kedua extra. Dengan satu: Laravel menulis konteks dulu, tapi bila isinya
        // hanya data permintaan (url/method/ip dari LogContext), itu extra.
        if (count($found) === 2) {
            return [$rest, $found[0], $found[1]];
        }
        if (count($found) === 1 && Arr::has($found[0], ['url', 'method'])) {
            return [$rest, null, $found[0]];
        }

        return [$rest, $found[0] ?? null, null];
    }

    private function summary(array $e): array
    {
        $request = $this->request($e);

        return [
            'offset' => $e['offset'],
            'datetime' => $this->iso($e['datetime']),
            'level' => $e['level'],
            'group' => self::levelGroup($e['level']),
            'message' => Str::limit($this->redact($e['message'] !== '' ? $e['message'] : ($e['exception']['message'] ?? '')), 300),
            'exception' => $e['exception'] ? class_basename($e['exception']['class']) : null,
            'location' => $e['exception'] ? $this->shortPath($e['exception']['file']).':'.$e['exception']['line'] : null,
            'url' => $request['url'] ? $this->redact(parse_url($request['url'], PHP_URL_PATH) ?? $request['url']) : null,
            'method' => $request['method'],
            'user_id' => $request['user_id'],
        ];
    }

    private function detail(array $e): array
    {
        $request = $this->request($e);
        $context = $e['context'];
        unset($context['userId']);

        return [
            ...$this->summary($e),
            'env' => $e['env'],
            'message' => $this->redact($e['message']),
            'exception' => $e['exception'] ? [
                'class' => $e['exception']['class'],
                'message' => $this->redact($e['exception']['message']),
                'file' => $this->shortPath($e['exception']['file']),
                'line' => $e['exception']['line'],
            ] : null,
            'request' => [...$request, 'url' => $request['url'] ? $this->redact($request['url']) : null],
            'context' => $context ? $this->redact(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : null,
            'trace' => $e['trace'] ? $this->redact($this->shortPath($e['trace'])) : null,
            'raw' => $this->redact($e['text']),
        ];
    }

    /** Data permintaan dari LogContext (extra) dengan cadangan userId bawaan Laravel di konteks. */
    private function request(array $e): array
    {
        $x = $e['extra'] ?? [];

        return [
            'url' => $x['url'] ?? null,
            'method' => $x['method'] ?? null,
            'ip' => $x['ip'] ?? null,
            'user_id' => $x['user_id'] ?? $e['context']['userId'] ?? null,
        ];
    }

    private function iso(string $datetime): string
    {
        return Carbon::parse($datetime, config('app.timezone'))->toIso8601String();
    }

    /** Exception di konteks ditulis sebagai string JSON (\\ dan \"); kembalikan ke teks biasa. */
    private function unescape(string $text): string
    {
        return str_replace(['\\\\', '\\"', '\\/'], ['\\', '"', '/'], $text);
    }

    private function shortPath(string $text): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $text);
    }

    /** Nilai rahasia dari config (APP_KEY, sandi DB/SMTP, secret OAuth, dsb.) yang tidak boleh tampil apa adanya. */
    private function secrets(): array
    {
        return $this->secrets ??= collect(Arr::dot(config()->all()))
            ->filter(fn ($v, $k) => is_string($v) && strlen($v) >= 8 && preg_match('/(key|secret|password|token)$/i', (string) $k))
            ->values()
            // API key AI ada di database (terenkripsi); bila database sedang mati, lewati saja.
            ->push(rescue(fn () => AiSettings::apiKey(), null, false))
            ->filter(fn ($v) => is_string($v) && strlen($v) >= 8)
            ->unique()->sortByDesc(fn ($v) => strlen($v))->values()->all();
    }
}
