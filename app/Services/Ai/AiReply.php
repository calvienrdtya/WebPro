<?php

namespace App\Services\Ai;

/** Balasan model: teks, model yang benar-benar menjawab (combo router bisa jatuh ke model lain), dan pemakaian token. */
final class AiReply
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
    ) {}

    /** @return array{prompt: int|null, completion: int|null} */
    public function usage(): array
    {
        return ['prompt' => $this->promptTokens, 'completion' => $this->completionTokens];
    }
}
