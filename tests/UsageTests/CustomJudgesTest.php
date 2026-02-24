<?php

/**
 * Custom Judges - GOAL-2.md Section: Custom Judges
 *
 * Tests Rubric classes, Judge interface implementations, and comparison judges.
 *
 * Consolidated: 8 tests → 3 tests, ~17 API calls → ~10 calls.
 */

use Redberry\Evals\Judges\SimilarityJudge;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\ActionableAdvice;
use Tests\UsageTests\Fixtures\CustomSimilarityJudge;
use Tests\UsageTests\Fixtures\ProfessionalTone;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Test 1: Scored + Binary Rubric Classes ---
// Covers: assertMeets(ScoredRubric), assertMeets(BinaryRubric) chained
test('scored and binary Rubric classes chained', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive". How should I respond?')
        ->assertMeets(new ProfessionalTone)
        ->assertMeets(new ActionableAdvice);
})->group('usage-tests');

// --- Test 2: Custom Judge + SimilarityJudge ---
// Covers: assertPasses(CustomJudge), assertPasses(SimilarityJudge), assertSimilarTo
test('custom Judge and SimilarityJudge', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital city of France')
        ->assertPasses(new CustomSimilarityJudge(threshold: 30))
        ->assertPasses(new SimilarityJudge(threshold: 60))
        ->assertSimilarTo('Paris is the capital of France', threshold: 70);
})->group('usage-tests');

// --- Test 3: Rubric with BDD + Fluent Expected ---
// Covers: toMeet(Rubric), expected() + assertSimilar
test('Rubric with BDD style and fluent expected', function () {
    evaluate(SalesCoachAgent::class)
        ->whenPrompted('The customer said "too expensive". How should I respond?')
        ->toMeet(new ProfessionalTone);

    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->assertSimilar(threshold: 70);
})->group('usage-tests');
