<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUserRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Manajemen user (tabel users) selain admin. Akun admin tidak ditampilkan dan tidak bisa diubah dari sini. */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), ['active', 'blocked'], true) ? $request->query('status') : 'all';
        $method = in_array($request->query('method'), ['google', 'email'], true) ? $request->query('method') : 'all';

        $scope = User::nonAdmin();

        $users = (clone $scope)
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->when($status === 'active', fn (Builder $query) => $query->whereNull('blocked_at'))
            ->when($status === 'blocked', fn (Builder $query) => $query->whereNotNull('blocked_at'))
            ->when($method === 'google', fn (Builder $query) => $query->whereNotNull('google_id'))
            ->when($method === 'email', fn (Builder $query) => $query->whereNotNull('password'))
            // Hanya pesanan lunas (paid/completed) yang dihitung sebagai belanja.
            ->withCount(['orders as paid_orders' => fn (Builder $o) => $o->whereIn('status', [OrderStatus::Paid, OrderStatus::Completed])])
            ->withSum(['orders as total_spent' => fn (Builder $o) => $o->whereIn('status', [OrderStatus::Paid, OrderStatus::Completed])], 'total')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'avatar' => $u->avatar,
                'methods' => array_values(array_filter([
                    $u->google_id ? 'google' : null,
                    $u->password ? 'email' : null,
                ])),
                'is_blocked' => $u->isBlocked(),
                'created_at' => $u->created_at->toDateString(),
                'paid_orders' => (int) $u->paid_orders,
                'total_spent' => (int) $u->total_spent,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['q' => $q, 'status' => $status, 'method' => $method],
            'counts' => [
                'all' => (clone $scope)->count(),
                'active' => (clone $scope)->whereNull('blocked_at')->count(),
                'blocked' => (clone $scope)->whereNotNull('blocked_at')->count(),
            ],
            'stats' => [
                'total' => (clone $scope)->count(),
                'new_30_days' => (clone $scope)->where('created_at', '>=', now()->subDays(30))->count(),
                'google' => (clone $scope)->whereNotNull('google_id')->count(),
                'email' => (clone $scope)->whereNotNull('password')->count(),
            ],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        // Tanpa password dan tanpa Google: user masuk lewat Google atau mengatur sandi dari "Lupa sandi".
        $created = User::forceCreate([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
        ]);

        return back()->with('success', "User \"{$created->name}\" ditambahkan.");
    }

    public function update(UserRequest $request, int $user): RedirectResponse
    {
        $target = User::nonAdmin()->findOrFail($user);
        $target->update($request->safe()->only(['name', 'phone']));

        return back()->with('success', "Data \"{$target->name}\" diperbarui.");
    }

    public function destroy(int $user): RedirectResponse
    {
        $target = User::nonAdmin()->findOrFail($user);

        // Riwayat transaksi harus utuh: user yang pernah memesan tidak dihapus, cukup diblokir.
        if ($target->orders()->exists()) {
            return back()->with('error', "User \"{$target->name}\" punya riwayat pesanan dan tidak bisa dihapus. Gunakan Blokir.");
        }

        DB::transaction(fn () => $this->deleteUser($target));

        return back()->with('success', "User \"{$target->name}\" dihapus.");
    }

    public function bulkBlock(BulkUserRequest $request): RedirectResponse
    {
        $block = $request->boolean('blocked');

        // Hanya yang statusnya benar-benar berubah yang dihitung.
        $count = User::nonAdmin()
            ->whereKey($request->validated('ids'))
            ->when($block, fn (Builder $q) => $q->whereNull('blocked_at'), fn (Builder $q) => $q->whereNotNull('blocked_at'))
            ->update(['blocked_at' => $block ? now() : null]);

        return back()->with('success', $block ? "{$count} user diblokir." : "Blokir {$count} user dibuka.");
    }

    public function bulkDestroy(BulkUserRequest $request): RedirectResponse
    {
        $users = User::nonAdmin()->whereKey($request->validated('ids'))->get();
        [$kept, $deletable] = $users->partition(fn (User $u) => $u->orders()->exists());

        DB::transaction(fn () => $deletable->each(fn (User $target) => $this->deleteUser($target)));

        if ($kept->isNotEmpty()) {
            $note = "{$kept->count()} user punya riwayat pesanan sehingga tidak dihapus (gunakan Blokir).";

            return $deletable->isEmpty()
                ? back()->with('error', $note)
                : back()->with('success', "{$deletable->count()} user dihapus. {$note}");
        }

        return back()->with('success', "{$deletable->count()} user dihapus.");
    }

    public function toggleBlock(int $user): RedirectResponse
    {
        $target = User::nonAdmin()->findOrFail($user);
        $target->forceFill(['blocked_at' => $target->isBlocked() ? null : now()])->save();

        return back()->with('success', $target->isBlocked() ? "\"{$target->name}\" diblokir." : "Blokir \"{$target->name}\" dibuka.");
    }

    /** Hapus user beserta sisa data yang menempel: sesi yang masih aktif dan token reset sandi. */
    private function deleteUser(User $target): void
    {
        DB::table('sessions')->where('user_id', $target->id)->delete();
        DB::table('password_reset_tokens')->where('email', $target->email)->delete();
        AiConversation::anonymizeUser($target->id);

        $target->delete();
    }
}
