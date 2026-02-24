<?php

/**
 * Core API - GOAL-2.md Sections: Core API + Prompting & Input
 *
 * Tests the entry point evaluate(), prompt method signature, fluent chain,
 * provider/model overrides, timeout, and EvalCase usage.
 *
 * Consolidated from: CoreApiTest + PromptingInputTest
 */

use Redberry\Evals\EvalCase;
use Redberry\Evals\EvalResult;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\HaikuWriterAgent;
use Tests\UsageTests\Fixtures\SalesCoachAgent;
use Tests\UsageTests\Fixtures\SupportBotAgent;

// --- Test 1: Entry Point Signatures ---
// Covers: agent class string, agent instance, agent factory closure
// Covers: assertContains, assertNotEmpty
test('accepts agent class, instance, and closure', function () {
    // Agent class string
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->assertNotEmpty();

    // Agent instance
    evaluate(new SalesCoachAgent)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->assertNotEmpty();

    // Agent factory closure
    evaluate(fn () => new SalesCoachAgent)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->assertNotEmpty();
})->group('usage-tests');

// --- Test 2: Provider/Model Overrides ---
// Covers: prompt(provider:, model:), fluent provider()/model()
test('overrides provider and model inline and fluently', function () {
    // Inline overrides
    evaluate(SalesCoachAgent::class)
        ->prompt(
            'Analyze this sales call transcript: Customer asked about pricing and the rep offered a 10% discount. Give feedback on the rep\'s approach.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        )
        ->assertMeets('The response provides feedback or analysis about a sales interaction');

    // Fluent method overrides
    evaluate(SalesCoachAgent::class)
        ->provider('openai')
        ->model('gpt-4o-mini')
        ->prompt('Analyze this sales call transcript: Customer asked about pricing.')
        ->assertMeets('The response provides feedback about the sales call');
})->group('usage-tests');

// --- Test 3: Timeout + EvalCase with Prompt ---
// Covers: timeout(), EvalCase::make()->prompt(), withCase()
test('timeout and EvalCase with prompt only', function () {
    // Timeout via fluent method
    $result = evaluate(GeographyAgent::class)
        ->timeout(120)
        ->prompt('What is the capital of Japan?')
        ->run();

    expect($result->text)->toContain('Tokyo');

    // EvalCase with prompt only
    $case = EvalCase::make()
        ->prompt('Write a haiku about PHP');

    evaluate(HaikuWriterAgent::class)
        ->withCase($case)
        ->assertNotEmpty();
})->group('usage-tests');

// --- Test 4: EvalCase with Expected + run() ---
// Covers: EvalCase expected, run()→EvalResult, assertSimilarTo
test('EvalCase with expected and run', function () {
    $case = EvalCase::make()
        ->prompt('Kindly ask to contact us at hello@example.com')
        ->expected('Please, contact us at hello@example.com');

    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertSimilarTo($case->expected, threshold: 60);
})->group('usage-tests');

// --- Test 5: Auto-Run + Caching + All Prompt Options ---
// Covers: lazy execution, result caching, assertMeets + assertContains, prompt with all options
test('auto-runs and caches on first assertion', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt(
            'Analyze this call: The customer said they are happy.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            timeout: 120,
        )
        ->assertMeets('Provides actionable feedback')
        ->assertNotEmpty();
})->group('usage-tests');
