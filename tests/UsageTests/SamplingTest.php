<?php

/**
 * Sampling - GOAL-2.md Section: Sampling
 *
 * Tests basic sampling, variance allowance, scored assertions with sampling,
 * deterministic assertions with sampling, mixed assertions, and sample results access.
 */

use Redberry\Evals\EvalResult;
use Redberry\Evals\JudgeResult;
use Redberry\Evals\SampleResults;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\CopyWriterAgent;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Basic Usage ---

// GOAL-2.md: samples(N) — run agent N times, all must pass
it('runs basic sampling with all must pass', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3)
        ->assertContains('Paris');
})->group('usage-tests');

// GOAL-2.md: repeat() as alias for samples()
it('uses repeat alias for samples', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->repeat(3)
        ->assertContains('Paris');
})->group('usage-tests');

// --- Allowing Variance ---

// GOAL-2.md: samples(5, minimum: 4) — at least 4 of 5 must pass
it('allows variance with minimum', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3, minimum: 2)
        ->assertContains('Paris');
})->group('usage-tests');

// --- Scored Assertions with Sampling ---

// GOAL-2.md: Each sample must individually meet the threshold
it('uses scored assertions with sampling', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive". How should I respond?')
        ->samples(3)
        ->assertMeets('Professional tone', 70);
})->group('usage-tests');

// GOAL-2.md: Combined minimum + threshold
it('combines minimum with scored threshold', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive". How should I respond?')
        ->samples(3, minimum: 2)
        ->assertMeets('Professional tone', 70);
})->group('usage-tests');

// --- With Deterministic Assertions ---

// GOAL-2.md: Sampling works with every assertion type
it('uses deterministic assertions with sampling', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->samples(3)
        ->assertContains('Laravel')
        ->assertLengthLessThan(1000)
        ->assertNotEmpty();
})->group('usage-tests');

// --- With Minimum on Mixed Assertions ---

// GOAL-2.md: minimum applies globally to all assertions in the chain
it('applies minimum globally to mixed assertions', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France? Always mention the country name.')
        ->samples(3, minimum: 2)
        ->assertContains('Paris')
        ->assertMeets('The response is factually correct');
})->group('usage-tests');

// --- Accessing Sample Results ---

// GOAL-2.md: run() with sampling returns SampleResults
it('returns SampleResults from run with sampling', function () {
    $samples = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3)
        ->run();

    expect($samples)->toBeInstanceOf(SampleResults::class);
    expect($samples->count())->toBe(3);
    expect($samples->outputs())->toHaveCount(3);
    expect($samples->first())->toBeInstanceOf(EvalResult::class);
    expect($samples->last())->toBeInstanceOf(EvalResult::class);
})->group('usage-tests');

// GOAL-2.md: judge() with sampling returns SampleResults with JudgeResults
it('returns SampleResults with judge results', function () {
    $samples = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3)
        ->judge('The response correctly identifies Paris as the capital');

    expect($samples)->toBeInstanceOf(SampleResults::class);
    expect($samples->judgeResults())->not->toBeNull();
    expect($samples->passRate())->toBeGreaterThanOrEqual(0);
    expect($samples->passed())->toBeTrue();
})->group('usage-tests');

// GOAL-2.md: Iterate individual JudgeResult objects
it('iterates individual judge results', function () {
    $samples = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3)
        ->judge('The response correctly identifies Paris');

    expect($samples)->toBeInstanceOf(SampleResults::class);

    $judgeResults = $samples->judgeResults();
    expect($judgeResults)->not->toBeNull()
        ->and($judgeResults)->toHaveCount(3);

    $judgeResults->each(function (JudgeResult $result) {
        expect($result->reasoning)->not->toBeEmpty();
    });
})->group('usage-tests');

// --- SampleResults API ---

// GOAL-2.md: passRate(), averageScore(), passed()
it('accesses sample results aggregate API', function () {
    $samples = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3)
        ->judge('The response correctly identifies Paris as the capital');

    expect($samples)->toBeInstanceOf(SampleResults::class);

    // passRate is a percentage 0-100
    expect($samples->passRate())->toBeGreaterThanOrEqual(0)
        ->and($samples->passRate())->toBeLessThanOrEqual(100);

    // passed() checks against minimum threshold
    expect($samples->passed())->toBeBool();
})->group('usage-tests');
