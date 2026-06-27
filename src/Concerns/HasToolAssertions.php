<?php

declare(strict_types=1);

namespace Redberry\Evals\Concerns;

use Closure;
use Redberry\Evals\EvalResult;
use Redberry\Evals\ToolInvocation;

trait HasToolAssertions
{
    /**
     * Assert a tool was used (optionally with matching arguments or closure constraint).
     *
     * @param  array<string, mixed>|Closure(ToolInvocation): bool|null  $constraint
     */
    public function assertToolUsed(string $tool, array|Closure|null $constraint = null): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $r->toolInvocations->contains(
                fn (ToolInvocation $inv): bool => $this->toolMatchesConstraint($inv, $tool, $constraint),
            ),
            "Expected tool '{$tool}' to be used"
                .($constraint !== null ? ' with matching constraint' : ''),
        );

        return $this;
    }

    /**
     * Assert a tool was NOT used.
     */
    public function assertToolNotUsed(string $tool): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => ! $r->toolInvocations->contains(
                fn (ToolInvocation $inv): bool => $this->toolMatches($inv, $tool),
            ),
            "Expected tool '{$tool}' to NOT be used",
        );

        return $this;
    }

    /**
     * Assert tools were used in the given sequence (subsequence matching — other tools may interleave).
     *
     * @param  array<int, string>  $tools
     */
    public function assertToolUseSequence(array $tools): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $this->checkToolSequence($r, $tools),
            'Expected tool use sequence: ['.implode(', ', $tools).']',
        );

        return $this;
    }

    /**
     * Assert a tool was used exactly N times (optionally with closure constraint).
     *
     * @param  Closure(ToolInvocation): bool|null  $constraint
     */
    public function assertToolUsedTimes(string $tool, int $count, ?Closure $constraint = null): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $this->countToolMatches($r, $tool, $constraint) === $count,
            "Expected tool '{$tool}' to be used exactly {$count} time(s)",
        );

        return $this;
    }

    /**
     * Assert a tool was used at least N times (optionally with closure constraint).
     *
     * @param  Closure(ToolInvocation): bool|null  $constraint
     */
    public function assertToolUsedAtLeast(string $tool, int $count, ?Closure $constraint = null): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $this->countToolMatches($r, $tool, $constraint) >= $count,
            "Expected tool '{$tool}' to be used at least {$count} time(s)",
        );

        return $this;
    }

    /**
     * Assert a tool was used at most N times (optionally with closure constraint).
     *
     * @param  Closure(ToolInvocation): bool|null  $constraint
     */
    public function assertToolUsedAtMost(string $tool, int $count, ?Closure $constraint = null): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $this->countToolMatches($r, $tool, $constraint) <= $count,
            "Expected tool '{$tool}' to be used at most {$count} time(s)",
        );

        return $this;
    }

    // --- Private Helpers ---

    /**
     * Check if a ToolInvocation matches a tool identifier (name or FQCN).
     */
    private function toolMatches(ToolInvocation $invocation, string $tool): bool
    {
        return $invocation->toolName === $tool
            || $invocation->toolClass === $tool;
    }

    /**
     * Check if a ToolInvocation matches a tool AND a constraint.
     *
     * @param  array<string, mixed>|Closure(ToolInvocation): bool|null  $constraint
     */
    private function toolMatchesConstraint(
        ToolInvocation $invocation,
        string $tool,
        array|Closure|null $constraint,
    ): bool {
        if (! $this->toolMatches($invocation, $tool)) {
            return false;
        }

        if ($constraint === null) {
            return true;
        }

        if (is_array($constraint)) {
            return $this->argumentsMatchSubset($invocation->arguments, $constraint);
        }

        return (bool) $constraint($invocation);
    }

    /**
     * Check whether actual arguments contain the expected subset.
     *
     * Associative arrays match by key/value subset, while list arrays remain exact
     * so ordered argument lists do not silently gain different semantics.
     *
     * @param  array<mixed>  $actual
     * @param  array<mixed>  $expected
     */
    private function argumentsMatchSubset(array $actual, array $expected): bool
    {
        if (array_is_list($expected)) {
            return $actual === $expected;
        }

        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                return false;
            }

            if (is_array($value)) {
                if (! is_array($actual[$key])) {
                    return false;
                }

                if (! $this->argumentsMatchSubset($actual[$key], $value)) {
                    return false;
                }

                continue;
            }

            if ($actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Count tool invocations matching a tool and optional constraint.
     *
     * @param  Closure(ToolInvocation): bool|null  $constraint
     */
    private function countToolMatches(EvalResult $result, string $tool, ?Closure $constraint): int
    {
        return $result->toolInvocations->filter(
            fn (ToolInvocation $inv): bool => $this->toolMatchesConstraint($inv, $tool, $constraint),
        )->count();
    }

    /**
     * Check that the expected tool sequence appears as a subsequence within actual invocations.
     *
     * @param  array<int, string>  $expectedSequence
     */
    private function checkToolSequence(EvalResult $result, array $expectedSequence): bool
    {
        $seqIndex = 0;
        $total = count($expectedSequence);

        foreach ($result->toolInvocations as $invocation) {
            if ($seqIndex >= $total) {
                break;
            }

            if ($this->toolMatches($invocation, $expectedSequence[$seqIndex])) {
                $seqIndex++;
            }
        }

        return $seqIndex === $total;
    }
}
