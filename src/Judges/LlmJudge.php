<?php

declare(strict_types=1);

namespace Redberry\Evals\Judges;

use Laravel\Ai\Enums\Lab;
use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\Contracts\Rubric;
use Redberry\Evals\EvalContext;
use Redberry\Evals\JudgeResult;

use function Laravel\Ai\agent;

final class LlmJudge implements Judge
{
    public function __construct(
        private readonly string|Rubric $criterion,
        private readonly ?int $threshold = null,
        private readonly Lab|string|null $provider = null,
        private readonly ?string $model = null,
    ) {}

    public function evaluate(EvalContext $context): JudgeResult
    {
        $criterionText = $this->criterion instanceof Rubric
            ? $this->criterion->description()
            : $this->criterion;

        $scored = $this->isScored();
        /** @var string $provider */
        $provider = $this->provider instanceof Lab
            ? $this->provider->value
            : ($this->provider ?? config('evals.judge.provider'));
        /** @var string $model */
        $model = $this->model ?? config('evals.judge.model');

        $judge = $scored
            ? $this->buildScoredAgent($criterionText)
            : $this->buildBinaryAgent($criterionText);

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $response = $judge->prompt(
            prompt: $this->buildPrompt($context),
            provider: $provider,
            model: $model,
        );

        if ($scored) {
            $score = (int) $response['score']; // @phpstan-ignore cast.int
            $threshold = $this->threshold ?? (int) config('evals.judge.default_threshold', 80); // @phpstan-ignore cast.int

            return new JudgeResult(
                passed: $score >= $threshold,
                score: $score,
                reasoning: (string) $response['reasoning'], // @phpstan-ignore cast.string
            );
        }

        return new JudgeResult(
            passed: (bool) $response['passed'],
            score: null,
            reasoning: (string) $response['reasoning'], // @phpstan-ignore cast.string
        );
    }

    private function isScored(): bool
    {
        if ($this->threshold !== null) {
            return true;
        }

        if ($this->criterion instanceof Rubric) {
            return $this->criterion->scored();
        }

        return false;
    }

    private function buildBinaryAgent(string $criterion): \Laravel\Ai\Contracts\Agent
    {
        return agent(
            instructions: $this->buildInstructions($criterion, scored: false),
            schema: fn ($s) => [
                /** @phpstan-ignore-next-line */
                'passed' => $s->boolean()->required(),
                /** @phpstan-ignore-next-line */
                'reasoning' => $s->string()->required(),
            ],
        );
    }

    private function buildScoredAgent(string $criterion): \Laravel\Ai\Contracts\Agent
    {
        return agent(
            instructions: $this->buildInstructions($criterion, scored: true),
            schema: fn ($s) => [
                /** @phpstan-ignore-next-line */
                'score' => $s->integer()->min(0)->max(100)->required(),
                /** @phpstan-ignore-next-line */
                'reasoning' => $s->string()->required(),
            ],
        );
    }

    private function buildInstructions(string $criterion, bool $scored): string
    {
        if ($scored) {
            return <<<PROMPT
            You are an evaluation judge. Score how well the given output meets the specified criterion on a scale of 0 to 100.

            Criterion: {$criterion}

            0 = completely fails to meet the criterion
            100 = perfectly meets the criterion

            Provide a score and brief reasoning for your assessment.
            PROMPT;
        }

        return <<<PROMPT
        You are an evaluation judge. Assess whether the given output meets the specified criterion.

        Criterion: {$criterion}

        Evaluate the output and determine if it passes or fails. Provide brief reasoning for your assessment.
        PROMPT;
    }

    private function buildPrompt(EvalContext $context): string
    {
        $prompt = "Input:\n{$context->input}\n\nOutput:\n{$context->output}";

        if ($context->expected !== null) {
            $expected = is_string($context->expected)
                ? $context->expected
                : (string) json_encode($context->expected, JSON_PRETTY_PRINT);

            $prompt .= "\n\nExpected:\n{$expected}";
        }

        return $prompt;
    }
}
