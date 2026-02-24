<?php

declare(strict_types=1);

namespace Redberry\Evals\Contracts;

abstract class Rubric
{
    /**
     * Describe the evaluation criteria in natural language.
     * This prompt is sent to the LLM judge.
     */
    abstract public function description(): string;

    /**
     * Whether the LLM should return a 0-100 score
     * instead of a binary pass/fail.
     */
    public function scored(): bool
    {
        return false;
    }
}
