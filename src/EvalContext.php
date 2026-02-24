<?php

declare(strict_types=1);

namespace Redberry\Evals;

final class EvalContext
{
    public function __construct(
        public readonly string $input,
        public readonly string $output,
        public readonly mixed $expected,
        public readonly EvalResult $result,
    ) {}
}
