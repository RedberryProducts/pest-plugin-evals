<?php

declare(strict_types=1);

namespace Redberry\Evals\Judges;

use InvalidArgumentException;
use Laravel\Ai\Enums\Lab;
use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\EvalContext;
use Redberry\Evals\JudgeResult;

use function Laravel\Ai\agent;

final class SimilarityJudge implements Judge
{
    public function __construct(
        private readonly int $threshold = 80,
        private readonly Lab|string|null $provider = null,
        private readonly ?string $model = null,
    ) {}

    public function evaluate(EvalContext $context): JudgeResult
    {
        if ($context->expected === null) {
            throw new InvalidArgumentException(
                'SimilarityJudge requires an expected value. Set it via ->expected() or ->assertSimilarTo().'
            );
        }

        /** @var string $provider */
        $provider = $this->provider instanceof Lab
            ? $this->provider->value
            : ($this->provider ?? config('evals.judge.provider'));
        /** @var string $model */
        $model = $this->model ?? config('evals.judge.model');

        $judge = agent(
            instructions: $this->buildInstructions(),
            schema: fn ($s) => [
                /** @phpstan-ignore-next-line */
                'score' => $s->integer()->min(0)->max(100)->required(),
                /** @phpstan-ignore-next-line */
                'reasoning' => $s->string()->required(),
            ],
        );

        $expected = is_string($context->expected)
            ? $context->expected
            : (string) json_encode($context->expected, JSON_PRETTY_PRINT);

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $response = $judge->prompt(
            prompt: "Input:\n{$context->input}\n\nActual Output:\n{$context->output}\n\nExpected Output:\n{$expected}",
            provider: $provider,
            model: $model,
        );

        $score = (int) $response['score']; // @phpstan-ignore cast.int

        return new JudgeResult(
            passed: $score >= $this->threshold,
            score: $score,
            reasoning: (string) $response['reasoning'], // @phpstan-ignore cast.string
        );
    }

    private function buildInstructions(): string
    {
        return <<<'PROMPT'
        You are a similarity judge. Compare the actual output against the expected output and rate their semantic similarity.

        0 = completely different meaning and content
        100 = semantically identical (wording may differ)

        Focus on meaning, not exact wording. Provide a score and brief reasoning for your assessment.
        PROMPT;
    }
}
