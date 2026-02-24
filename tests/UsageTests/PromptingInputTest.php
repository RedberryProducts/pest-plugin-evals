<?php

/**
 * Prompting & Input - GOAL-2.md Section: Prompting & Input
 *
 * Tests simple prompt, provider/model override, timeout, and EvalCase usage.
 */

use Redberry\Evals\EvalCase;
use Redberry\Evals\EvalResult;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\HaikuWriterAgent;
use Tests\UsageTests\Fixtures\SalesCoachAgent;
use Tests\UsageTests\Fixtures\SupportBotAgent;

// --- Simple Prompt ---

// GOAL-2.md: evaluate(SalesCoach::class)->prompt('Analyze this sales call transcript...')
it('uses simple prompt', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Analyze this sales call transcript: The rep offered a 10% discount and closed the deal.')
        ->assertMeets('The response analyzes the sales interaction');
})->group('usage-tests');

// --- With Provider & Model Override ---

// GOAL-2.md: prompt() with provider and model inline
it('overrides provider and model inline', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt(
            'Analyze this sales call transcript: Customer asked about pricing.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        )
        ->assertMeets('The response provides feedback about the sales call');
})->group('usage-tests');

// GOAL-2.md: Or using fluent methods
it('overrides provider and model via fluent methods', function () {
    evaluate(SalesCoachAgent::class)
        ->provider('openai')
        ->model('gpt-4o-mini')
        ->prompt('Analyze this sales call transcript: Customer asked about pricing.')
        ->assertMeets('The response provides feedback about the sales call');
})->group('usage-tests');

// --- With Timeout ---

// GOAL-2.md: timeout inline or fluent
it('sets timeout via fluent method', function () {
    $result = evaluate(GeographyAgent::class)
        ->timeout(120)
        ->prompt('What is the capital of Japan?')
        ->run();

    expect($result->text)->toContain('Tokyo');
})->group('usage-tests');

// --- Using EvalCase ---

// GOAL-2.md: Minimal case — prompt only
it('uses EvalCase with prompt only', function () {
    $case = EvalCase::make()
        ->prompt('Write a haiku about PHP');

    evaluate(HaikuWriterAgent::class)
        ->withCase($case)
        ->assertMeets('The response is a valid haiku with 5-7-5 syllables');
})->group('usage-tests');

// GOAL-2.md: With expectation — plain text output
it('uses EvalCase with expected plain text', function () {
    $case = EvalCase::make()
        ->prompt('Kindly ask to contact us at hello@example.com')
        ->expected('Please, contact us at hello@example.com');

    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertMeets('asks to contact at hello@example.com')
        ->assertSimilarTo($case->expected, threshold: 60);
})->group('usage-tests');

// GOAL-2.md: Complete example with all options
it('uses prompt with all options combined', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt(
            'Analyze this call: The customer said they are happy.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            timeout: 120,
        )
        ->assertMeets('Provides actionable feedback');
})->group('usage-tests');

// --- EvalCase with expected — structured output ---

// GOAL-2.md: EvalCase with structured expected and ->run()
it('uses EvalCase with run for manual inspection', function () {
    $case = EvalCase::make()
        ->prompt('What is the capital of Japan? Answer concisely.')
        ->expected('Tokyo');

    $result = evaluate(GeographyAgent::class)
        ->withCase($case)
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->toContain('Tokyo');
})->group('usage-tests');
