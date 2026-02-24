<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\EvalContext;
use Redberry\Evals\JudgeResult;

class CustomSimilarityJudge implements Judge
{
    public function __construct(
        private float $threshold = 80
    ) {}

    public function evaluate(EvalContext $context): JudgeResult
    {
        $actual = strtolower(trim($context->output));
        $expected = strtolower(trim((string) $context->expected));

        // Simple word overlap similarity for demonstration
        $actualWords = array_unique(str_word_count($actual, 1));
        $expectedWords = array_unique(str_word_count($expected, 1));

        if (empty($expectedWords)) {
            $similarity = 0;
        } else {
            $commonWords = array_intersect($actualWords, $expectedWords);
            $similarity = (int) round((count($commonWords) / count($expectedWords)) * 100);
        }

        return new JudgeResult(
            passed: $similarity >= $this->threshold,
            score: $similarity,
            reasoning: "Word overlap similarity: {$similarity}% ({$this->threshold}% required)",
        );
    }
}
