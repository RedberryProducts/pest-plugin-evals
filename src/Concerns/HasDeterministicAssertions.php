<?php

declare(strict_types=1);

namespace Redberry\Evals\Concerns;

use Redberry\Evals\EvalResult;

trait HasDeterministicAssertions
{
    /**
     * Assert the output contains the given string(s). When an array is passed, ALL must match.
     *
     * @param  string|array<int, string>  $needle
     */
    public function assertContains(string|array $needle): static
    {
        $needles = is_array($needle) ? $needle : [$needle];

        foreach ($needles as $n) {
            $this->assertEachResult(
                fn (EvalResult $r): bool => str_contains($r->text, $n),
                "Expected output to contain '{$n}'",
            );
        }

        return $this;
    }

    /**
     * Assert the output contains at least one of the given strings.
     *
     * @param  array<int, string>  $needles
     */
    public function assertContainsAny(array $needles): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => collect($needles)->contains(
                fn (string $n): bool => str_contains($r->text, $n),
            ),
            'Expected output to contain at least one of: '.implode(', ', array_map(fn (string $n): string => "'{$n}'", $needles)),
        );

        return $this;
    }

    /**
     * Assert the output does NOT contain the given string.
     */
    public function assertNotContains(string $needle): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => ! str_contains($r->text, $needle),
            "Expected output to NOT contain '{$needle}'",
        );

        return $this;
    }

    /**
     * Assert the output matches a regular expression.
     */
    public function assertMatches(string $regex): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => (bool) preg_match($regex, $r->text),
            "Expected output to match regex '{$regex}'",
        );

        return $this;
    }

    // --- Length Assertions ---

    /**
     * Assert the output length is less than the given maximum.
     */
    public function assertLengthLessThan(int $max): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => mb_strlen($r->text) < $max,
            "Expected output length to be less than {$max}",
        );

        return $this;
    }

    /**
     * Assert the output length is greater than the given minimum.
     */
    public function assertLengthGreaterThan(int $min): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => mb_strlen($r->text) > $min,
            "Expected output length to be greater than {$min}",
        );

        return $this;
    }

    /**
     * Assert the output length is between min and max (inclusive).
     */
    public function assertLengthBetween(int $min, int $max): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => mb_strlen($r->text) >= $min && mb_strlen($r->text) <= $max,
            "Expected output length to be between {$min} and {$max}",
        );

        return $this;
    }

    // --- JSON Assertions ---

    /**
     * Assert the output is valid JSON.
     */
    public function assertJson(): static
    {
        $this->assertEachResult(
            function (EvalResult $r): bool {
                json_decode($r->text, true);

                return json_last_error() === JSON_ERROR_NONE;
            },
            'Expected output to be valid JSON',
        );

        return $this;
    }

    /**
     * Assert a JSON path has the expected value. Uses dot-notation.
     */
    public function assertJsonPath(string $path, mixed $expected): static
    {
        $this->assertEachResult(
            function (EvalResult $r) use ($path, $expected): bool {
                /** @var array<string, mixed>|null $data */
                $data = $r->structured ?? json_decode($r->text, true);
                if (! is_array($data)) {
                    return false;
                }

                return data_get($data, $path) === $expected;
            },
            "Expected JSON path '{$path}' to equal ".json_encode($expected),
        );

        return $this;
    }

    /**
     * Assert the output matches a JSON structure.
     *
     * @param  array<int|string, mixed>  $structure
     */
    public function assertJsonStructure(array $structure): static
    {
        $this->assertEachResult(
            function (EvalResult $r) use ($structure): bool {
                /** @var array<string, mixed>|null $data */
                $data = $r->structured ?? json_decode($r->text, true);
                if (! is_array($data)) {
                    return false;
                }

                return $this->checkJsonStructure($structure, $data);
            },
            'Expected output to match JSON structure',
        );

        return $this;
    }

    // --- Type Assertions ---

    /**
     * Assert the output is a plain string (no structured output).
     */
    public function assertString(): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $r->structured === null,
            'Expected output to be a plain string (no structured output)',
        );

        return $this;
    }

    /**
     * Assert the output is structured (array).
     */
    public function assertArray(): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $r->structured !== null,
            'Expected output to be structured (array)',
        );

        return $this;
    }

    /**
     * Assert the output is not empty.
     */
    public function assertNotEmpty(): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $r->structured !== null
                ? $r->structured !== []
                : $r->text !== '',
            'Expected output to not be empty',
        );

        return $this;
    }

    // --- Equality Assertions ---

    /**
     * Assert the output exactly equals the expected value.
     */
    public function assertEquals(mixed $expected): static
    {
        $this->assertEachResult(
            fn (EvalResult $r): bool => $r->structured !== null
                ? $r->structured === $expected
                : $r->text === $expected,
            'Expected output to exactly equal '.json_encode($expected),
        );

        return $this;
    }

    /**
     * Assert the structured output contains all key-value pairs from the expected array (subset match).
     *
     * @param  array<string, mixed>  $expected
     */
    public function assertMatchesArray(array $expected): static
    {
        $this->assertEachResult(
            function (EvalResult $r) use ($expected): bool {
                $actual = $r->structured;
                if ($actual === null) {
                    return false;
                }

                foreach ($expected as $key => $value) {
                    if (! array_key_exists($key, $actual) || $actual[$key] !== $value) {
                        return false;
                    }
                }

                return true;
            },
            'Expected structured output to match array '.json_encode($expected),
        );

        return $this;
    }

    // --- Private Helpers ---

    /**
     * Recursively check that a data array matches a structure definition.
     *
     * @param  array<int|string, mixed>  $structure
     * @param  array<string, mixed>  $data
     */
    private function checkJsonStructure(array $structure, array $data): bool
    {
        foreach ($structure as $key => $value) {
            if (is_int($key)) {
                // Numeric key = check that value (as key name) exists in data
                if (! is_string($value) || ! array_key_exists($value, $data)) {
                    return false;
                }
            } else {
                // String key with potential sub-structure
                if (! array_key_exists($key, $data)) {
                    return false;
                }

                if (is_array($value)) {
                    if (! is_array($data[$key])) {
                        return false;
                    }

                    if (! $this->checkJsonStructure($value, $data[$key])) { // @phpstan-ignore argument.type
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
