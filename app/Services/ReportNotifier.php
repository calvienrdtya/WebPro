<?php

namespace App\Services;

use App\Mail\NewReportMail;
use App\Models\CustomerReport;

/** Memberi tahu admin soal laporan baru, sesuai saklar di Pengaturan > Notifikasi (bawaan: mati). */
class ReportNotifier
{
    public function newReport(CustomerReport $report): void
    {
        app(AdminNotifier::class)->send(new NewReportMail($report->loadMissing('user')), 'report');
    }
}
