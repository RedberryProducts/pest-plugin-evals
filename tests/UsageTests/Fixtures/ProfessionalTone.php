<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Redberry\Evals\Contracts\Rubric;

class ProfessionalTone extends Rubric
{
    public function description(): string
    {
        return <<<'PROMPT'
            Evaluate if the response maintains a professional tone:
            - No slang or informal language
            - Proper grammar and punctuation
            - Respectful and courteous
            - Appropriate for business communication
        PROMPT;
    }

    public function scored(): bool
    {
        return true;
    }
}
