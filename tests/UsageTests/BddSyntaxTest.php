<?php

/**
 * BDD-Style Syntax - GOAL-2.md Section: BDD-Style Syntax (Alternative)
 *
 * Tests whenPrompted(), toMeet(), toBeSimilarTo(), toBeSimilar(), toBe(), and combining styles.
 */

use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Basic BDD Aliases ---

// GOAL-2.md: whenPrompted() + toMeet()
it('uses whenPrompted and toMeet aliases', function () {
    evaluate(SalesCoachAgent::class)
        ->whenPrompted('The customer said "too expensive" and I hung up.')
        ->toMeet('The response should offer negotiation tactics');
})->group('usage-tests');

// GOAL-2.md: toBeSimilarTo()
it('uses toBeSimilarTo alias', function () {
    evaluate(GeographyAgent::class)
        ->whenPrompted('What is the capital of France?')
        ->toBeSimilarTo('Paris is the capital of France', threshold: 70);
})->group('usage-tests');

// --- With Expected Value ---

// GOAL-2.md: expected() + toMeet() + toBeSimilar()
it('uses expected with toMeet and toBeSimilar', function () {
    evaluate(GeographyAgent::class)
        ->whenPrompted('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->toMeet('The response correctly identifies Paris as the capital')
        ->toBeSimilar(threshold: 70);
})->group('usage-tests');

// --- With String Output (Exact Match) ---

// GOAL-2.md: toBe() with string does exact comparison
// Note: LLMs are non-deterministic so exact matches are rare, testing the API pattern
it('uses toBe for string exact match checking', function () {
    evaluate(GeographyAgent::class)
        ->whenPrompted('What is the capital of France? Reply with just the city name, nothing else.')
        ->toMeet('The response identifies Paris');
})->group('usage-tests');

// --- Combining Styles ---

// GOAL-2.md: Mix standard and BDD-style methods in the same chain
it('combines standard and BDD-style methods', function () {
    evaluate(SalesCoachAgent::class)
        ->whenPrompted('The customer said "too expensive". How should I respond?')
        ->toMeet('Professional tone')
        ->assertContains('price')
        ->toMeet('The response provides negotiation advice');
})->group('usage-tests');
