<?php

declare(strict_types=1);

namespace Redberry\Evals\Contracts;

use Redberry\Evals\EvalContext;
use Redberry\Evals\JudgeResult;

interface Judge
{
    /**
     * Evaluate the agent's output and return a verdict.
     */
    public function evaluate(EvalContext $context): JudgeResult;
}
