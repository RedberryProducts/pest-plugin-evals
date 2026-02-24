<?php

declare(strict_types=1);

namespace Redberry\Evals;

final class JudgeResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly ?int $score,
        public readonly string $reasoning,
    ) {}
}
