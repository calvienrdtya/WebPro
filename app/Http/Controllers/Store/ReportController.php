<?php

namespace App\Http\Controllers\Store;

use App\Enums\ReportCategory;
use App\Http\Controllers\Controller;
use App\Models\CustomerReport;
use App\Models\Order;
use App\Models\ReportAttachment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Pelanggan mengirim laporan (satu arah) dari halaman Lapor Masalah; masuk ke menu admin Laporan.
 * Batas: 3 laporan terbuka dan 5 laporan per hari. Lampiran disimpan di penyimpanan privat.
 */
class ReportController extends Controller
{
    public const MAX_OPEN = 3;

    public const MAX_PER_DAY = 5;

    /** Batas isian form; dipakai validasi server dan validasi langsung di halaman. */
    public const LIMITS = ['subject_min' => 5, 'subject_max' => 120, 'body_min' => 20, 'body_max' => 2000, 'files' => 3, 'file_kb' => 3072];

    /** Halaman Lapor Masalah. `?pesanan=KODE` memilih pesanan terkait (mis. dari halaman pesanan). */
    public function create(Request $request): View
    {
        $user = $request->user();
        $orders = Order::where('user_id', $user->id)->latest('id')->limit(30)->get(['id', 'code', 'product_name', 'created_at']);

        return view('store.reports.create', [
            'categories' => ReportCategory::options(),
            'orders' => $orders,
            'selectedOrder' => old('order_id', $orders->firstWhere('code', $request->query('pesanan'))?->id),
            'openCount' => $user->reports()->open()->count(),
            'maxOpen' => self::MAX_OPEN,
            'limits' => self::LIMITS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $back = fn () => redirect()->route('reports.create');

        $l = self::LIMITS;
        $data = $request->validate([
            'category' => ['required', Rule::enum(ReportCategory::class)],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('user_id', $user->id)],
            'subject' => ['required', 'string', 'min:'.$l['subject_min'], 'max:'.$l['subject_max']],
            'body' => ['required', 'string', 'min:'.$l['body_min'], 'max:'.$l['body_max']],
            'attachments' => ['nullable', 'array', 'max:'.$l['files']],
            'attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$l['file_kb']],
        ], [
            'subject.min' => 'Judul minimal '.$l['subject_min'].' karakter.',
            'body.min' => 'Isi laporan minimal '.$l['body_min'].' karakter.',
            'attachments.max' => 'Lampiran maksimal '.$l['files'].' gambar.',
            'attachments.*.image' => 'Lampiran harus berupa gambar.',
            'attachments.*.mimes' => 'Lampiran harus berformat JPG, PNG, atau WebP.',
            'attachments.*.max' => 'Ukuran tiap lampiran maksimal '.($l['file_kb'] / 1024).' MB.',
        ], ['category' => 'Kategori', 'order_id' => 'Pesanan', 'subject' => 'Judul', 'body' => 'Isi laporan']);

        if ($user->reports()->open()->count() >= self::MAX_OPEN) {
            return $back()->withInput()->with('error', 'Kamu masih punya '.self::MAX_OPEN.' laporan yang menunggu. Tunggu admin menyelesaikannya dulu.');
        }

        if ($user->reports()->where('created_at', '>=', now()->startOfDay())->count() >= self::MAX_PER_DAY) {
            return $back()->withInput()->with('error', 'Batas laporan hari ini ('.self::MAX_PER_DAY.') sudah tercapai. Coba lagi besok.');
        }

        DB::transaction(function () use ($user, $data, $request) {
            $report = CustomerReport::create([
                'user_id' => $user->id,
                'category' => $data['category'],
                'order_id' => $data['order_id'] ?? null,
                'subject' => trim($data['subject']),
                'body' => trim($data['body']),
            ]);

            foreach ($request->file('attachments', []) as $file) {
                ReportAttachment::create([
                    'report_id' => $report->id,
                    'path' => Storage::disk(ReportAttachment::DISK)->putFile('reports/'.$report->id, $file),
                    'name' => mb_substr($file->getClientOriginalName(), 0, 150),
                    'size' => $file->getSize(),
                ]);
            }
        });

        return redirect()->route('profil', ['tab' => 'laporan'])->with('success', 'Laporan terkirim. Admin akan menindaklanjutinya.');
    }
}
