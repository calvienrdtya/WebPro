<?php

namespace App\Enums;

/** Status entri pengetahuan AI. Hanya entri Siap yang aktif yang dikirim ke AI. */
enum KnowledgeStatus: string
{
    case Processing = 'processing';
    case Review = 'review';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Diproses',
            self::Review => 'Perlu ditinjau',
            self::Ready => 'Siap',
            self::Failed => 'Gagal',
        };
    }
}
