<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Lampiran laporan pindah dari folder publik (public/uploads/reports) ke penyimpanan privat (storage/app/private). */
    public function up(): void
    {
        $public = Storage::disk('uploads');
        $private = Storage::disk('local');

        foreach (DB::table('report_attachments')->get(['id', 'path']) as $a) {
            if ($public->exists($a->path) && ! $private->exists($a->path)) {
                $private->put($a->path, $public->get($a->path));
                $public->delete($a->path);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
