<?php

/**
 * Datasets - GOAL-2.md Section: Datasets
 *
 * Tests EvalCase inline creation, JSON datasets, XML datasets, and fromDirectory.
 */

use Redberry\Evals\DatasetLoader;
use Redberry\Evals\EvalCase;
use Redberry\Evals\EvalResult;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\HaikuWriterAgent;
use Tests\UsageTests\Fixtures\SupportBotAgent;

// --- EvalCase Inline Creation ---

// GOAL-2.md: Inline dataset creation with EvalCase::make()
dataset('sales_scenarios', [
    'angry customer' => fn () => EvalCase::make()
        ->prompt('I want a refund NOW!')
        ->expected('Calm de-escalation response'),

    'confused customer' => fn () => EvalCase::make()
        ->prompt('How do I log in?')
        ->expected('Step-by-step instructions'),
]);

// GOAL-2.md: Usage with dataset
it('handles customer scenarios from inline dataset', function (EvalCase $case) {
    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertMeets($case->expected);
})->with('sales_scenarios')->group('usage-tests');

// --- JSON Datasets ---

// GOAL-2.md: EvalCase::fromJson() — load single case from JSON file
it('loads case from JSON file', function () {
    $case = EvalCase::fromJson(
        __DIR__.'/Fixtures/datasets/contact-info.case.json'
    );

    expect($case)->toBeInstanceOf(EvalCase::class)
        ->and($case->prompt)->not->toBeEmpty()
        ->and($case->expected)->toBeArray();
})->group('usage-tests');

// GOAL-2.md: Use JSON case with evaluate
it('evaluates with JSON-loaded case', function () {
    $case = EvalCase::fromJson(
        __DIR__.'/Fixtures/datasets/refund-request.case.json'
    );

    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertMeets($case->expected);
})->group('usage-tests');

// GOAL-2.md: Prompt-only JSON case (no expectation)
it('loads prompt-only JSON case', function () {
    $case = EvalCase::fromJson(
        __DIR__.'/Fixtures/datasets/php-haiku.case.json'
    );

    expect($case)->toBeInstanceOf(EvalCase::class)
        ->and($case->prompt)->toBe('Write a haiku about PHP')
        ->and($case->expected)->toBeNull();

    evaluate(HaikuWriterAgent::class)
        ->withCase($case)
        ->assertMeets('The response is a haiku or poem');
})->group('usage-tests');

// --- XML Datasets ---

// GOAL-2.md: EvalCase::fromXml() — load multiple cases from XML file
it('loads cases from XML file', function () {
    $cases = EvalCase::fromXml(
        __DIR__.'/Fixtures/datasets/customer-support.case.xml'
    );

    expect($cases)->toBeArray()
        ->and($cases)->toHaveCount(3)
        ->and($cases)->toHaveKeys(['refund-request', 'complaint', 'open-ended']);

    // Each case should be an EvalCase
    foreach ($cases as $case) {
        expect($case)->toBeInstanceOf(EvalCase::class)
            ->and($case->prompt)->not->toBeEmpty();
    }

    // Case with expected
    expect($cases['refund-request']->expected)->not->toBeNull();

    // Prompt-only case
    expect($cases['open-ended']->expected)->toBeNull();
})->group('usage-tests');

// GOAL-2.md: Usage with XML dataset
dataset('customer_support_xml', fn () => (new DatasetLoader)->fromXml(
    __DIR__.'/Fixtures/datasets/customer-support.case.xml'
));

it('handles support scenarios from XML dataset', function (EvalCase $case) {
    $builder = evaluate(SupportBotAgent::class)
        ->withCase($case);

    if ($case->expected !== null) {
        $builder->assertMeets($case->expected);
    } else {
        $builder->assertNotEmpty();
    }
})->with('customer_support_xml')->group('usage-tests');

// --- fromDirectory ---

// GOAL-2.md: EvalCase::fromDirectory() — auto-discovers .case.json and .case.xml files
it('loads cases from directory', function () {
    $cases = EvalCase::fromDirectory(
        __DIR__.'/Fixtures/datasets'
    );

    expect($cases)->toBeArray()
        ->and($cases)->not->toBeEmpty();

    // Should contain cases from both JSON and XML files
    foreach ($cases as $key => $case) {
        expect($case)->toBeInstanceOf(EvalCase::class)
            ->and($case->prompt)->not->toBeEmpty();
    }
})->group('usage-tests');

// GOAL-2.md: Use dataset with fromDirectory
dataset('all_fixture_cases', fn () => (new DatasetLoader)->fromDirectory(
    __DIR__.'/Fixtures/datasets'
));

it('evaluates cases from directory discovery', function (EvalCase $case) {
    $result = evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->with('all_fixture_cases')->group('usage-tests');

// --- EvalCase with Expect ---

// GOAL-2.md: EvalCase with structured expected and ->run() + expect()
it('uses EvalCase run result with expect', function () {
    $case = EvalCase::make()
        ->prompt('What is the capital of France? Reply with just the city name.')
        ->expected('Paris');

    $result = evaluate(GeographyAgent::class)
        ->withCase($case)
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->toContain('Paris');
})->group('usage-tests');
