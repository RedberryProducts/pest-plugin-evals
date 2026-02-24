<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Redberry\Evals\Contracts\Rubric;

class ActionableAdvice extends Rubric
{
    public function description(): string
    {
        return <<<'PROMPT'
            Evaluate if the response provides actionable advice:
            - Contains specific, practical suggestions
            - Advice can be immediately applied
            - Includes concrete steps or examples
            - Not too vague or generic
        PROMPT;
    }

    public function scored(): bool
    {
        return false;
    }
}
