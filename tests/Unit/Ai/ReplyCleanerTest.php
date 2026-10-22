<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\ReplyCleaner;
use PHPUnit\Framework\TestCase;

class ReplyCleanerTest extends TestCase
{
    public function test_leaked_provider_notes_and_trailing_rules_are_removed(): void
    {
        $reply = "# Panduan\n| A | B |\n---\nskipped: formatting metadata, add when needed.";
        $this->assertSame("# Panduan\n| A | B |", ReplyCleaner::clean($reply, ['skipped:']));

        $this->assertSame('Halo', ReplyCleaner::clean("Halo\n\n * **Skipped:** parsing\n> skipped: x", ['skipped:']));
    }

    public function test_normal_text_is_kept_and_blank_runs_are_collapsed(): void
    {
        $this->assertSame("Langkah 1\n\nLangkah 2\n---\nLanjut", ReplyCleaner::clean("Langkah 1\n\n\n\nLangkah 2\n---\nLanjut", ['skipped:']));
        $this->assertSame('Tidak ada yang skipped: di tengah kalimat.', ReplyCleaner::clean('Tidak ada yang skipped: di tengah kalimat.', ['skipped:']));
    }
}
