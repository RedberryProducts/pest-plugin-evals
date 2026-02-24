<?php

declare(strict_types=1);

namespace Redberry\Evals;

use ArrayAccess;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;
use LogicException;
use Stringable;

/**
 * @implements ArrayAccess<string, mixed>
 */
final class EvalResult implements ArrayAccess, Stringable
{
    /**
     * @param  string  $text  The agent's text output.
     * @param  array<string, mixed>|null  $structured  Parsed structured output (if agent implements HasStructuredOutput).
     * @param  Collection<int, ToolInvocation>  $toolInvocations  Normalized tool calls with results.
     * @param  AgentResponse  $response  The raw response for escape-hatch access.
     */
    public function __construct(
        public readonly string $text,
        public readonly ?array $structured,
        public readonly Collection $toolInvocations,
        public readonly AgentResponse $response,
    ) {}

    /**
     * Whether the agent produced structured output.
     */
    public function isStructured(): bool
    {
        return $this->structured !== null;
    }

    /**
     * Get the output as an array (structured) or null (text-only).
     *
     * @return array<string, mixed>|null
     */
    public function toArray(): ?array
    {
        return $this->structured;
    }

    // --- ArrayAccess (delegates to structured output) ---

    public function offsetExists(mixed $offset): bool
    {
        return $this->structured !== null && array_key_exists($offset, $this->structured);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($this->structured === null) {
            return null;
        }

        return $this->structured[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('EvalResult is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('EvalResult is immutable.');
    }

    // --- Stringable ---

    public function __toString(): string
    {
        return $this->text;
    }
}
