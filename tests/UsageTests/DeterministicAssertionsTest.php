<?php

/**
 * Deterministic Assertions - GOAL-2.md Section: Deterministic Assertions
 *
 * Tests string, length, JSON, type, and equality assertions.
 */

use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\CopyWriterAgent;

// --- String Assertions ---

// GOAL-2.md: assertContains with single string
it('asserts output contains a string', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->assertContains('Laravel');
})->group('usage-tests');

// GOAL-2.md: assertContains with array (all must match)
it('asserts output contains all strings', function () {
    evaluate(GeographyAgent::class)
        ->prompt('Tell me about Paris, the capital of France')
        ->assertContains(['Paris', 'France']);
})->group('usage-tests');

// GOAL-2.md: assertContainsAny (at least one)
it('asserts output contains any of the given strings', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about a popular PHP framework')
        ->assertContainsAny(['Laravel', 'Symfony', 'CodeIgniter']);
})->group('usage-tests');

// GOAL-2.md: assertNotContains
it('asserts output does not contain a string', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertNotContains('Python');
})->group('usage-tests');

// GOAL-2.md: assertMatches (regex)
it('asserts output matches regex', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France? Include the country name.')
        ->assertMatches('/France/i');
})->group('usage-tests');

// --- Length Assertions ---

// GOAL-2.md: assertLengthLessThan
it('asserts output length is less than max', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel. Keep it very short.')
        ->assertLengthLessThan(500);
})->group('usage-tests');

// GOAL-2.md: assertLengthGreaterThan
it('asserts output length is greater than min', function () {
    evaluate(GeographyAgent::class)
        ->prompt('Tell me about Paris, France. Include some detail.')
        ->assertLengthGreaterThan(10);
})->group('usage-tests');

// GOAL-2.md: assertLengthBetween
it('asserts output length is between bounds', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->assertLengthBetween(10, 1000);
})->group('usage-tests');

// --- Type Assertions ---

// GOAL-2.md: assertString — plain text output
it('asserts output is a plain string', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertString();
})->group('usage-tests');

// GOAL-2.md: assertNotEmpty
it('asserts output is not empty', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertNotEmpty();
})->group('usage-tests');

// --- Combined Deterministic Assertions ---

// GOAL-2.md: Full example combining all deterministic assertion types
it('chains multiple deterministic assertions', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->assertContains('Laravel')
        ->assertNotContains('bad word')
        ->assertLengthGreaterThan(10)
        ->assertLengthLessThan(1000)
        ->assertString()
        ->assertNotEmpty();
})->group('usage-tests');
