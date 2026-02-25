<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Illuminate\Support\Collection;

final class EvalRecord
{
    /**
     * @param  Collection<int, ToolInvocation>  $toolInvocations
     */
    public function __construct(
        public readonly string $assertionName,
        public readonly string $input,
        public readonly string $output,
        public readonly bool $passed,
        public readonly Collection $toolInvocations,
        public readonly ?int $score = null,
        public readonly ?string $reasoning = null,
        public readonly ?int $sampleIndex = null,
        public readonly ?int $sampleTotal = null,
        public readonly ?int $sampleMinimum = null,
    ) {}
}
