<?php

/**
 * LLM-as-a-Judge Assertions - GOAL-2.md Section: LLM-as-a-Judge Assertions
 *
 * Tests assertMeets, assertDoesNotMeet, assertSimilarTo, assertSimilar, assertPasses, and judge result access.
 */

use Redberry\Evals\JudgeResult;
use Redberry\Evals\Judges\SimilarityJudge;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Magic String-Based Judges ---

// GOAL-2.md: assertMeets with binary pass/fail
it('asserts output meets a criterion (binary)', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Review this call transcript: Customer said "too expensive" and the rep gave up.')
        ->assertMeets('The feedback is constructive, not critical');
})->group('usage-tests');

// GOAL-2.md: assertMeets with score threshold
it('asserts output meets a criterion with threshold', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Review this call transcript: Customer said "too expensive" and the rep gave up.')
        ->assertMeets('Specific strategies for handling price objections are provided', 70);
})->group('usage-tests');

// GOAL-2.md: Multiple assertMeets in chain
it('chains multiple assertMeets assertions', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Review this call transcript: Customer said "too expensive" and the rep gave up.')
        ->assertMeets('The feedback is constructive, not critical')
        ->assertMeets('At least 1 actionable suggestion is provided');
})->group('usage-tests');

// --- Negation ---

// GOAL-2.md: assertDoesNotMeet
it('asserts output does not meet a criterion', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Review this call transcript: Customer said "too expensive". What should I do?')
        ->assertDoesNotMeet('The response contains profanity or insults');
})->group('usage-tests');

// --- Similarity Judge ---

// GOAL-2.md: assertSimilarTo with inline expected
it('asserts output is similar to expected text', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertSimilarTo('Paris is the capital of France', threshold: 70);
})->group('usage-tests');

// GOAL-2.md: assertSimilar using previously set expected value
it('asserts output is similar using expected value', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->assertSimilar(threshold: 70);
})->group('usage-tests');

// --- Custom Judge Class ---

// GOAL-2.md: assertPasses with custom Judge
it('asserts output passes a custom judge', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->assertPasses(new SimilarityJudge(threshold: 60));
})->group('usage-tests');

// --- Judge Result Access ---

// GOAL-2.md: judge() returns JudgeResult for manual inspection
it('returns judge result for manual inspection', function () {
    $result = evaluate(SalesCoachAgent::class)
        ->prompt('Review this: Customer was rude.')
        ->judge('Is the response helpful?');

    expect($result)->toBeInstanceOf(JudgeResult::class)
        ->and($result->reasoning)->not->toBeEmpty();
})->group('usage-tests');

// GOAL-2.md: Use JudgeResult in expect() assertions
it('uses judge result properties in expect assertions', function () {
    $result = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->judge('The response correctly identifies Paris as the capital');

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeTrue();
    expect($result->reasoning)->not->toBeEmpty();
})->group('usage-tests');

// --- Override Judge Provider ---

// GOAL-2.md: judgeWith() to override judge provider/model
it('overrides judge provider and model', function () {
    evaluate(GeographyAgent::class)
        ->judgeWith('openai', 'gpt-4o-mini')
        ->prompt('What is the capital of Japan?')
        ->assertMeets('The response correctly identifies Tokyo as the capital');
})->group('usage-tests');
