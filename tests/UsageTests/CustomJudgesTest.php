<?php

/**
 * Custom Judges - GOAL-2.md Section: Custom Judges
 *
 * Tests Rubric classes, Judge interface implementations, and comparison judges.
 */

use Redberry\Evals\Judges\SimilarityJudge;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\ActionableAdvice;
use Tests\UsageTests\Fixtures\CustomSimilarityJudge;
use Tests\UsageTests\Fixtures\ProfessionalTone;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Rubric Classes ---

// GOAL-2.md: assertMeets with Rubric instance (scored)
it('uses scored Rubric class', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive". How should I respond?')
        ->assertMeets(new ProfessionalTone);
})->group('usage-tests');

// GOAL-2.md: assertMeets with Rubric instance (binary)
it('uses binary Rubric class', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive". How should I respond?')
        ->assertMeets(new ActionableAdvice);
})->group('usage-tests');

// GOAL-2.md: Multiple Rubric assertions chained
it('chains multiple rubric assertions', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive". How should I respond?')
        ->assertMeets(new ProfessionalTone)
        ->assertMeets(new ActionableAdvice);
})->group('usage-tests');

// --- Judge Classes (Full Control) ---

// GOAL-2.md: assertPasses with custom Judge class
it('uses custom Judge class with assertPasses', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital city of France')
        ->assertPasses(new CustomSimilarityJudge(threshold: 30));
})->group('usage-tests');

// --- Comparison Judges ---

// GOAL-2.md: assertSimilarTo with custom threshold
it('uses similarity comparison with custom threshold', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertSimilarTo('Paris is the capital of France', threshold: 70);
})->group('usage-tests');

// GOAL-2.md: Fluent chain with separate expected + assertSimilar
it('uses fluent expected with assertSimilar', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->assertSimilar(threshold: 70);
})->group('usage-tests');

// GOAL-2.md: Built-in SimilarityJudge with assertPasses
it('uses built-in SimilarityJudge with assertPasses', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->assertPasses(new SimilarityJudge(threshold: 60));
})->group('usage-tests');

// --- Rubric with BDD-style ---

// GOAL-2.md: toMeet() with Rubric
it('uses Rubric with BDD-style toMeet', function () {
    evaluate(SalesCoachAgent::class)
        ->whenPrompted('The customer said "too expensive". How should I respond?')
        ->toMeet(new ProfessionalTone);
})->group('usage-tests');
