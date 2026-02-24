<?php

/**
 * Deterministic Assertions - GOAL-2.md Section: Deterministic Assertions
 *
 * Tests string, length, type, and equality assertions.
 * Consolidated: 11 tests → 3 tests, 11 API calls → 3 calls.
 */

use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\CopyWriterAgent;

// --- Test 1: String Content Assertions ---
// Covers: assertContains(string), assertContains(array), assertContainsAny,
//         assertNotContains, assertMatches
test('string content assertions', function () {
    evaluate(GeographyAgent::class)
        ->prompt('Tell me about Paris, the capital of France')
        ->assertContains('Paris')
        ->assertContains(['Paris', 'France'])
        ->assertContainsAny(['Paris', 'London', 'Berlin'])
        ->assertNotContains('Python')
        ->assertMatches('/France/i');
})->group('usage-tests');

// --- Test 2: Length and Type Assertions ---
// Covers: assertLengthLessThan, assertLengthGreaterThan, assertLengthBetween,
//         assertString, assertNotEmpty
test('length and type assertions', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->assertLengthGreaterThan(10)
        ->assertLengthLessThan(1000)
        ->assertLengthBetween(10, 1000)
        ->assertString()
        ->assertNotEmpty();
})->group('usage-tests');

// --- Test 3: Chained Assertions on Longer Output ---
// Covers: assertLengthGreaterThan(100), assertContains, assertMatches on longer content
test('chained deterministic assertions on longer output', function () {
    evaluate(GeographyAgent::class)
        ->prompt('Tell me about Paris, France. Include some detail about its landmarks and history.')
        ->assertContains('Paris')
        ->assertContains('France')
        ->assertLengthGreaterThan(50)
        ->assertNotContains('bad word')
        ->assertMatches('/Paris/i')
        ->assertString()
        ->assertNotEmpty();
})->group('usage-tests');
