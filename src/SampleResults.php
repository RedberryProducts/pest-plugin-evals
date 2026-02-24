<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, EvalResult>
 */
final class SampleResults implements Countable, IteratorAggregate
{
    /** @var Collection<int, JudgeResult>|null */
    private ?Collection $judgeResults = null;

    /**
     * @param  Collection<int, EvalResult>  $results  The raw results from each sample run.
     * @param  int|null  $minimum  Minimum samples that must pass. null = all.
     */
    public function __construct(
        private Collection $results,
        private ?int $minimum = null,
    ) {}

    public function count(): int
    {
        return $this->results->count();
    }

    /**
     * @return Collection<int, EvalResult>
     */
    public function outputs(): Collection
    {
        return $this->results;
    }

    public function first(): EvalResult
    {
        return $this->results->firstOrFail();
    }

    public function last(): EvalResult
    {
        /** @var EvalResult */
        return $this->results->last();
    }

    /**
     * @return Traversable<int, EvalResult>
     */
    public function getIterator(): Traversable
    {
        return $this->results->getIterator();
    }

    public function minimum(): ?int
    {
        return $this->minimum;
    }

    /**
     * @param  callable(EvalResult, int): mixed  $callback
     */
    public function each(callable $callback): static
    {
        $this->results->each($callback);

        return $this;
    }

    /**
     * Return a new instance with judge results attached.
     *
     * @param  Collection<int, JudgeResult>  $judgeResults
     */
    public function withJudgeResults(Collection $judgeResults): static
    {
        $clone = clone $this;
        $clone->judgeResults = $judgeResults;

        return $clone;
    }

    /**
     * @return Collection<int, JudgeResult>|null
     */
    public function judgeResults(): ?Collection
    {
        return $this->judgeResults;
    }

    /**
     * Get the pass rate as a percentage (0-100).
     */
    public function passRate(): float
    {
        if (! $this->judgeResults instanceof \Illuminate\Support\Collection || $this->judgeResults->isEmpty()) {
            return 0.0;
        }

        $passCount = $this->judgeResults->filter(fn (JudgeResult $r): bool => $r->passed)->count();

        return ($passCount / $this->judgeResults->count()) * 100;
    }

    /**
     * Get the average score across all judge results.
     * Returns null if no results have scores (binary-only judges).
     */
    public function averageScore(): ?float
    {
        if (! $this->judgeResults instanceof \Illuminate\Support\Collection || $this->judgeResults->isEmpty()) {
            return null;
        }

        $scored = $this->judgeResults->filter(fn (JudgeResult $r): bool => $r->score !== null);

        if ($scored->isEmpty()) {
            return null;
        }

        return $scored->avg(fn (JudgeResult $r): float => (float) $r->score);
    }

    /**
     * Whether enough samples passed based on the minimum threshold.
     */
    public function passed(): bool
    {
        if (! $this->judgeResults instanceof \Illuminate\Support\Collection) {
            return false;
        }

        $passCount = $this->judgeResults->filter(fn (JudgeResult $r): bool => $r->passed)->count();
        $required = $this->minimum ?? $this->count();

        return $passCount >= $required;
    }
}
