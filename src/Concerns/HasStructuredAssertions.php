<?php

declare(strict_types=1);

namespace Redberry\Evals\Concerns;

use Redberry\Evals\EvalResult;
use Redberry\Evals\Missing;

trait HasStructuredAssertions
{
    /**
     * Assert the structured output has a key (optionally with a specific value). Supports dot-notation.
     */
    public function assertHasKey(string $key, mixed $value = Missing::Value): static
    {
        $this->assertEachResult(
            function (EvalResult $r) use ($key, $value): bool {
                if ($r->structured === null) {
                    return false;
                }

                $actual = data_get($r->structured, $key, Missing::Value);

                if ($actual === Missing::Value) {
                    return false;
                }

                if ($value !== Missing::Value) {
                    return $actual === $value;
                }

                return true;
            },
            $value !== Missing::Value
                ? "Expected structured output to have key '{$key}' with value ".json_encode($value)
                : "Expected structured output to have key '{$key}'",
        );

        return $this;
    }

    /**
     * Assert the structured output has all the given keys. Supports dot-notation.
     *
     * @param  array<int, string>  $keys
     */
    public function assertHasKeys(array $keys): static
    {
        foreach ($keys as $key) {
            $this->assertHasKey($key);
        }

        return $this;
    }

    /**
     * Assert the structured output has a property (alias for assertHasKey).
     */
    public function assertHasProperty(string $key, mixed $value = Missing::Value): static
    {
        return $this->assertHasKey($key, $value);
    }

    /**
     * Assert the structured output has all the given properties (alias for assertHasKeys).
     *
     * @param  array<int, string>  $properties
     */
    public function assertHasProperties(array $properties): static
    {
        return $this->assertHasKeys($properties);
    }
}
