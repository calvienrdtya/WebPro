<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceFeeRequest;
use App\Models\ServiceFee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Biaya layanan (biaya admin) di checkout. Hanya satu yang aktif; mengaktifkan satu otomatis menonaktifkan yang lain. */
class ServiceFeeController extends Controller
{
    public function index(): Response
    {
        $fees = ServiceFee::query()->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (ServiceFee $f) => ['id' => $f->id, 'name' => $f->name, 'amount' => $f->amount, 'is_active' => $f->is_active]);

        return Inertia::render('ServiceFees/Index', ['fees' => $fees]);
    }

    public function store(ServiceFeeRequest $request): RedirectResponse
    {
        $fee = new ServiceFee;
        $replaced = $fee->saveExclusive($request->safe()->only(['name', 'amount', 'is_active']));

        return back()->with('success', $this->message("Biaya layanan \"{$fee->name}\" ditambahkan.", $replaced));
    }

    public function update(ServiceFeeRequest $request, ServiceFee $fee): RedirectResponse
    {
        $replaced = $fee->saveExclusive($request->safe()->only(['name', 'amount', 'is_active']));

        return back()->with('success', $this->message("Biaya layanan \"{$fee->name}\" diperbarui.", $replaced));
    }

    public function toggle(ServiceFee $fee): RedirectResponse
    {
        $replaced = $fee->saveExclusive(['is_active' => ! $fee->is_active]);

        return back()->with('success', $this->message("Biaya layanan \"{$fee->name}\" ".($fee->is_active ? 'diaktifkan.' : 'dinonaktifkan.'), $replaced));
    }

    public function destroy(ServiceFee $fee): RedirectResponse
    {
        $fee->delete();

        return back()->with('success', "Biaya layanan \"{$fee->name}\" dihapus.");
    }

    private function message(string $base, array $replaced): string
    {
        return $replaced === [] ? $base : $base.' "'.implode('", "', $replaced).'" dinonaktifkan karena hanya satu biaya yang boleh aktif.';
    }
}
