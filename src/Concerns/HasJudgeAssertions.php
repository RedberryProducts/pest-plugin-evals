<?php

declare(strict_types=1);

namespace Redberry\Evals\Concerns;

use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\Contracts\Rubric;
use Redberry\Evals\Judges\LlmJudge;
use Redberry\Evals\Judges\SimilarityJudge;

trait HasJudgeAssertions
{
    /**
     * Assert the output meets a criterion evaluated by an LLM judge.
     */
    public function assertMeets(string|Rubric $criterion, ?int $threshold = null): static
    {
        $judge = new LlmJudge(
            criterion: $criterion,
            threshold: $threshold,
            provider: $this->judgeProvider,
            model: $this->judgeModel,
        );

        $label = $criterion instanceof Rubric
            ? $criterion::class
            : "'{$criterion}'";

        $this->judgeEachResult(
            $judge,
            "Expected output to meet criterion: {$label}"
                .($threshold !== null ? " (threshold: {$threshold})" : ''),
        );

        return $this;
    }

    /**
     * Assert the output does NOT meet a criterion.
     */
    public function assertDoesNotMeet(string|Rubric $criterion): static
    {
        $judge = new LlmJudge(
            criterion: $criterion,
            provider: $this->judgeProvider,
            model: $this->judgeModel,
        );

        $label = $criterion instanceof Rubric
            ? $criterion::class
            : "'{$criterion}'";

        $this->judgeEachResult(
            $judge,
            "Expected output to NOT meet criterion: {$label}",
            negate: true,
        );

        return $this;
    }

    /**
     * Assert the output is semantically similar to the given expected text.
     */
    public function assertSimilarTo(string $expected, int $threshold = 80): static
    {
        $judge = new SimilarityJudge(
            threshold: $threshold,
            provider: $this->judgeProvider,
            model: $this->judgeModel,
        );

        $this->judgeEachResult(
            $judge,
            "Expected output to be similar (threshold: {$threshold})",
            expectedOverride: $expected,
        );

        return $this;
    }

    /**
     * Assert the output is similar to the previously set expected value.
     */
    public function assertSimilar(?int $threshold = null): static
    {
        $resolvedThreshold = $threshold ?? 80;

        $judge = new SimilarityJudge(
            threshold: $resolvedThreshold,
            provider: $this->judgeProvider,
            model: $this->judgeModel,
        );

        $this->judgeEachResult(
            $judge,
            "Expected output to be similar to expected value (threshold: {$resolvedThreshold})",
        );

        return $this;
    }

    /**
     * Assert the output passes a custom Judge implementation.
     */
    public function assertPasses(Judge $judge): static
    {
        $this->judgeEachResult(
            $judge,
            'Expected output to pass custom judge: '.$judge::class,
        );

        return $this;
    }
}
