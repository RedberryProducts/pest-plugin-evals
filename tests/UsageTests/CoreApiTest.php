<?php

/**
 * Core API - GOAL-2.md Section: Core API
 *
 * Tests the entry point evaluate(), prompt method signature, and fluent chain.
 */

use Redberry\Evals\EvalResult;
use Tests\UsageTests\Fixtures\SalesCoachAgent;

// --- Entry Point: evaluate() ---

// GOAL-2.md: "Basic usage — evaluate(SalesCoach::class)"
it('accepts agent class string', function () {
    $result = evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->group('usage-tests');

// GOAL-2.md: "With agent instance — evaluate(new SalesCoach($user))"
it('accepts agent instance', function () {
    $agent = new SalesCoachAgent;

    $result = evaluate($agent)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->group('usage-tests');

// GOAL-2.md: "With agent factory — evaluate(fn () => SalesCoach::make(user: $user))"
it('accepts agent factory closure', function () {
    $result = evaluate(fn () => new SalesCoachAgent)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->group('usage-tests');

// --- Prompt Method Signature ---

// GOAL-2.md: prompt() with provider/model overrides
it('supports prompt with provider and model overrides', function () {
    $result = evaluate(SalesCoachAgent::class)
        ->prompt(
            'Analyze this sales call transcript: Customer said they need to think about it.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        )
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->group('usage-tests');

// --- Fluent Chain ---

// GOAL-2.md: All prompt parameters available as separate fluent methods
it('supports fluent chain configuration', function () {
    $result = evaluate(SalesCoachAgent::class)
        ->provider('openai')
        ->model('gpt-4o-mini')
        ->prompt('The customer wants a discount. How should I respond?')
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->group('usage-tests');

// GOAL-2.md: Fluent chain with assertions (auto-runs on assert)
it('auto-runs on first assertion', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->assertMeets('The response should offer negotiation tactics')
        ->assertMeets('The tone should be encouraging, not critical');
})->group('usage-tests');
