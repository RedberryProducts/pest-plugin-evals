<?php

/**
 * Sampling - GOAL-2.md Section: Sampling
 *
 * Tests basic sampling, variance allowance, scored assertions with sampling,
 * deterministic assertions with sampling, and sample results access.
 *
 * Consolidated: 11 tests → 4 tests, 54 API calls → ~12 calls.
 * Sampling reduced from 3 → 2.
 */

use Redberry\Evals\EvalResult;
use Redberry\Evals\JudgeResult;
use Redberry\Evals\SampleResults;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\CopyWriterAgent;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Test 1: Basic Sampling + Deterministic ---
// Covers: samples(2) assertContains; repeat() alias; assertLengthLessThan
test('basic sampling with deterministic assertions', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(2)
        ->assertContains('Paris');

    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->repeat(2)
        ->assertContains('Laravel')
        ->assertLengthLessThan(1000)
        ->assertNotEmpty();
})->group('usage-tests');

// --- Test 2: Sampling with Minimum + Scored Threshold ---
// Covers: samples(2, minimum:1), assertMeets(criterion, threshold) + assertContains
test('sampling with minimum and scored threshold', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive". How should I respond?')
        ->samples(2, minimum: 1)
        ->assertMeets('Professional tone', 70)
        ->assertNotEmpty();

    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France? Always mention the country name.')
        ->samples(2, minimum: 1)
        ->assertContains('Paris')
        ->assertMeets('The response is factually correct');
})->group('usage-tests');

// --- Test 3: SampleResults API from run() ---
// Covers: samples(2)->run(); count(), outputs(), first(), last()
test('SampleResults API from run', function () {
    $samples = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(2)
        ->run();

    expect($samples)->toBeInstanceOf(SampleResults::class);
    expect($samples->count())->toBe(2);
    expect($samples->outputs())->toHaveCount(2);
    expect($samples->first())->toBeInstanceOf(EvalResult::class);
    expect($samples->last())->toBeInstanceOf(EvalResult::class);
})->group('usage-tests');

// --- Test 4: SampleResults with Judge + Iteration ---
// Covers: samples(2)->judge(); judgeResults(), passRate(), passed(), each()
test('SampleResults with judge and iteration', function () {
    $samples = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(2)
        ->judge('The response correctly identifies Paris as the capital');

    expect($samples)->toBeInstanceOf(SampleResults::class);
    expect($samples->judgeResults())->not->toBeNull();
    expect($samples->passRate())->toBeGreaterThanOrEqual(0)
        ->and($samples->passRate())->toBeLessThanOrEqual(100);
    expect($samples->passed())->toBeTrue();

    $judgeResults = $samples->judgeResults();
    expect($judgeResults)->toHaveCount(2);

    $judgeResults->each(function (JudgeResult $result) {
        expect($result->reasoning)->not->toBeEmpty();
    });
})->group('usage-tests');
