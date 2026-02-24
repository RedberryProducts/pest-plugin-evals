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
    /**
     * @param  Collection<int, EvalResult>  $results  The raw results from each sample run.
     * @param  int|null  $minimum  Minimum samples that must pass. null = all.
     */
    public function __construct(
        protected Collection $results,
        protected ?int $minimum = null,
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
}
