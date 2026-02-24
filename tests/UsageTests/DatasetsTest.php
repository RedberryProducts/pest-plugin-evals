<?php

/**
 * Datasets - GOAL-2.md Section: Datasets
 *
 * Tests EvalCase inline creation, JSON datasets, XML datasets, and fromDirectory.
 *
 * Consolidated: ~12 tests (expanded) → 5 tests, ~19 API calls → ~10 calls.
 */

use Redberry\Evals\EvalCase;
use Redberry\Evals\EvalResult;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\SupportBotAgent;

// --- Test 1: Inline EvalCase Dataset ---
// Covers: EvalCase::make(), dataset(), withCase(), assertMeets(expected)
dataset('sales_scenarios', [
    'angry customer' => fn () => EvalCase::make()
        ->prompt('I want a refund NOW!')
        ->expected('Calm de-escalation response'),

    'confused customer' => fn () => EvalCase::make()
        ->prompt('How do I log in?')
        ->expected('Step-by-step instructions'),
]);

it('handles customer scenarios from inline dataset', function (EvalCase $case) {
    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertMeets($case->expected);
})->with('sales_scenarios')->group('usage-tests');

// --- Test 2: JSON Loading + Evaluation ---
// Covers: fromJson (structured + plain + prompt-only), withCase(), assertMeets
test('JSON dataset loading and evaluation', function () {
    // Structured JSON case
    $case = EvalCase::fromJson(
        __DIR__.'/Fixtures/datasets/contact-info.case.json'
    );
    expect($case)->toBeInstanceOf(EvalCase::class)
        ->and($case->prompt)->not->toBeEmpty()
        ->and($case->expected)->toBeArray();

    // Prompt-only JSON case
    $promptOnly = EvalCase::fromJson(
        __DIR__.'/Fixtures/datasets/php-haiku.case.json'
    );
    expect($promptOnly->prompt)->toBe('Write a haiku about PHP')
        ->and($promptOnly->expected)->toBeNull();

    // Evaluate with a JSON case
    $refundCase = EvalCase::fromJson(
        __DIR__.'/Fixtures/datasets/refund-request.case.json'
    );
    evaluate(SupportBotAgent::class)
        ->withCase($refundCase)
        ->assertMeets($refundCase->expected);
})->group('usage-tests');

// --- Test 3: XML Loading + Evaluation ---
// Covers: fromXml→array, count, keys; evaluate 1 case with assertMeets
test('XML dataset loading and evaluation', function () {
    $cases = EvalCase::fromXml(
        __DIR__.'/Fixtures/datasets/customer-support.case.xml'
    );

    expect($cases)->toBeArray()
        ->and($cases)->toHaveCount(3)
        ->and($cases)->toHaveKeys(['refund-request', 'complaint', 'open-ended']);

    foreach ($cases as $case) {
        expect($case)->toBeInstanceOf(EvalCase::class)
            ->and($case->prompt)->not->toBeEmpty();
    }

    expect($cases['refund-request']->expected)->not->toBeNull();
    expect($cases['open-ended']->expected)->toBeNull();

    // Evaluate one XML case
    evaluate(SupportBotAgent::class)
        ->withCase($cases['refund-request'])
        ->assertMeets($cases['refund-request']->expected);
})->group('usage-tests');

// --- Test 4: Directory Loading + Run ---
// Covers: fromDirectory, verify structure, evaluate 1 case with run()
test('directory dataset loading and run', function () {
    $cases = EvalCase::fromDirectory(
        __DIR__.'/Fixtures/datasets'
    );

    expect($cases)->toBeArray()
        ->and($cases)->not->toBeEmpty();

    foreach ($cases as $key => $case) {
        expect($case)->toBeInstanceOf(EvalCase::class)
            ->and($case->prompt)->not->toBeEmpty();
    }

    // Evaluate the first case
    $firstCase = reset($cases);
    $result = evaluate(SupportBotAgent::class)
        ->withCase($firstCase)
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->group('usage-tests');

// --- Test 5: EvalCase with Run + Expect ---
// Covers: EvalCase with expected, run()→EvalResult, assertContains
test('EvalCase with run and expect', function () {
    $case = EvalCase::make()
        ->prompt('What is the capital of France? Reply with just the city name.')
        ->expected('Paris');

    $result = evaluate(GeographyAgent::class)
        ->withCase($case)
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->toContain('Paris');
})->group('usage-tests');
