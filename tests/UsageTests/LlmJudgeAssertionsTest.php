<?php

/**
 * LLM-as-a-Judge Assertions - GOAL-2.md Sections: LLM-as-a-Judge + BDD-Style Syntax
 *
 * Tests assertMeets, assertDoesNotMeet, assertSimilarTo, assertSimilar, assertPasses,
 * judge result access, and BDD aliases (whenPrompted, toMeet, toBeSimilarTo, toBeSimilar).
 *
 * Consolidated from: LlmJudgeAssertionsTest + BddSyntaxTest
 */

use Redberry\Evals\JudgeResult;
use Redberry\Evals\Judges\SimilarityJudge;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Test 1: Binary + Negation + Chaining ---
// Covers: assertMeets (binary), assertDoesNotMeet, chain 2x assertMeets
test('binary judge, negation, and chaining', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Review this call transcript: Customer said "too expensive" and the rep gave up.')
        ->assertMeets('The feedback is constructive, not critical')
        ->assertMeets('At least 1 actionable suggestion is provided')
        ->assertDoesNotMeet('The response contains profanity or insults');
})->group('usage-tests');

// --- Test 2: Scored Threshold + Judge Override ---
// Covers: assertMeets(criterion, threshold), judgeWith()
test('scored threshold and judge override', function () {
    evaluate(GeographyAgent::class)
        ->judgeWith('openai', 'gpt-4o-mini')
        ->prompt('What is the capital of Japan?')
        ->assertMeets('The response correctly identifies Tokyo as the capital', 70);
})->group('usage-tests');

// --- Test 3: Similarity Assertions ---
// Covers: assertSimilarTo(text, threshold), expected() + assertSimilar
test('similarity assertions', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertSimilarTo('Paris is the capital of France', threshold: 70)
        ->expected('Paris is the capital of France')
        ->assertSimilar(threshold: 70);
})->group('usage-tests');

// --- Test 4: Judge Result Inspection + Custom Judge ---
// Covers: judge()→JudgeResult, passed/score/reasoning, assertPasses(Judge)
test('judge result inspection and custom judge', function () {
    $result = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->judge('The response correctly identifies Paris as the capital');

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeTrue();
    expect($result->reasoning)->not->toBeEmpty();

    // assertPasses with SimilarityJudge
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->assertPasses(new SimilarityJudge(threshold: 60));
})->group('usage-tests');

// --- Test 5: BDD Aliases ---
// Covers: whenPrompted, toMeet, toBeSimilarTo, toBeSimilar, combining BDD + standard
test('BDD-style aliases', function () {
    evaluate(SalesCoachAgent::class)
        ->whenPrompted('The customer said "too expensive". How should I respond?')
        ->toMeet('Professional tone')
        ->toMeet('The response provides negotiation advice');

    evaluate(GeographyAgent::class)
        ->whenPrompted('What is the capital of France?')
        ->expected('Paris is the capital of France')
        ->toMeet('The response correctly identifies Paris as the capital')
        ->toBeSimilar(threshold: 70);
})->group('usage-tests');
