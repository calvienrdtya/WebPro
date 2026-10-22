<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountRequest;
use App\Http\Requests\Admin\BulkAccountRequest;
use App\Models\Account;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request, ?Product $product = null): Response
    {
        $q = trim((string) $request->query('q'));
        $raw = (string) $request->query('status');
        // Halaman per produk membuka tab "Tersedia"; halaman semua akun membuka "Semua".
        $status = $raw === 'all' ? null : (AccountStatus::tryFrom($raw) ?? ($product && $raw === '' ? AccountStatus::Available : null));

        $scope = Account::query()->when($product, fn ($query) => $query->where('product_id', $product->id));

        $counts = (clone $scope)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $accounts = (clone $scope)
            ->with('product:id,name,slug,deleted_at')
            ->search($q)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Account $a) => [
                'id' => $a->id,
                'product_id' => $a->product_id,
                'product_name' => $a->product->name,
                'product_slug' => $a->product->slug,
                'email' => $a->email,
                'password' => $a->password,
                'profile' => $a->profile,
                'pin' => $a->pin,
                'cogs' => $a->cogs,
                'status' => $a->status->value,
                'sold_to' => $a->sold_to,
                'sold_at' => $a->sold_at?->toDateString(),
                'created_at' => $a->created_at->toDateString(),
                'started_at' => $a->started_at?->toDateString(),
                'active_days' => $a->active_days,
                'expires_at' => $a->expires_at?->toDateString(),
                'note' => $a->note,
            ]);

        return Inertia::render('Accounts/Index', [
            'product' => $product?->only(['id', 'name', 'slug']),
            'accounts' => $accounts,
            'filters' => ['q' => $q, 'status' => $status?->value ?? 'all'],
            'counts' => collect(AccountStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'stats' => $this->stats($scope),
            'defaults' => ['active_days' => (int) config('proakses.account_days')],
            'statuses' => AccountStatus::options(),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $count = (int) ($data['profile_count'] ?? 1);
        unset($data['profile_count']);

        DB::transaction(function () use ($data, $count) {
            // Beberapa profil dari satu akun: nama profil diberi nomor urut, kredensial dan HPP sama.
            $prefix = trim((string) ($data['profile'] ?? '')) ?: 'Profil';

            foreach (range(1, $count) as $i) {
                Account::create($count > 1 ? [...$data, 'profile' => "{$prefix} {$i}"] : $data);
            }
        });

        return back()->with('success', $count > 1 ? "{$count} akun ditambahkan." : 'Akun ditambahkan.');
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $account->update(collect($request->validated())->except('profile_count')->all());

        return back()->with('success', 'Akun diperbarui.');
    }

    /** Ubah status banyak akun sekaligus; setiap akun disimpan satu per satu agar aturan terjual/tersedia tetap berjalan. */
    public function bulkStatus(BulkAccountRequest $request): RedirectResponse
    {
        $status = AccountStatus::from($request->validated('status'));
        $soldTo = $request->validated('sold_to');

        $count = DB::transaction(function () use ($request, $status, $soldTo) {
            $accounts = Account::query()->whereKey($request->validated('ids'))->get();

            foreach ($accounts as $account) {
                $account->status = $status;

                // Pembeli hanya diisi bila diberikan; akun yang sudah punya pembeli tidak dikosongkan.
                if ($status === AccountStatus::Sold && filled($soldTo)) {
                    $account->sold_to = $soldTo;
                }

                $account->save();
            }

            return $accounts->count();
        });

        return back()->with('success', "{$count} akun diubah menjadi {$status->label()}.");
    }

    public function bulkDestroy(BulkAccountRequest $request): RedirectResponse
    {
        $count = Account::query()->whereKey($request->validated('ids'))->get()->each->delete()->count();

        return back()->with('success', "{$count} akun dihapus.");
    }

    public function destroy(Account $account): RedirectResponse
    {
        $account->delete();

        return back()->with('success', 'Akun dihapus.');
    }

    /**
     * Ringkasan HPP untuk cakupan halaman (semua akun atau satu produk), tidak terpengaruh tab/pencarian.
     *
     * @return array<string, int>
     */
    private function stats(Builder $scope): array
    {
        $available = (clone $scope)->where('status', AccountStatus::Available);

        return [
            'stock_count' => (clone $available)->count(),
            'total_count' => (clone $scope)->count(),
            'stock_cost' => (int) (clone $available)->sum('cogs'),
            'average_cost' => (int) round((clone $scope)->whereNotNull('cogs')->avg('cogs') ?? 0),
            'sold_cost' => (int) (clone $scope)->where('status', AccountStatus::Sold)->sum('cogs'),
            'missing_cost' => (clone $available)->whereNull('cogs')->count(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function productOptions(): array
    {
        return Product::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name])->all();
    }
}
