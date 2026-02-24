<?php

/**
 * Full Examples - GOAL-2.md Section: Full Examples
 *
 * End-to-end tests covering complete workflows from the GOAL-2.md examples.
 *
 * Consolidated: ~15 tests (expanded) → 6 tests, ~48 API calls → ~21 calls.
 * Sampling reduced from 3 → 2.
 */

use Redberry\Evals\EvalCase;
use Redberry\Evals\EvalResult;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\ActionableAdvice;
use Tests\UsageTests\Fixtures\BlogWriterAgent;
use Tests\UsageTests\Fixtures\CopyWriterAgent;
use Tests\UsageTests\Fixtures\ProfessionalTone;
use Tests\UsageTests\Fixtures\SalesCoachAgent;
use Tests\UsageTests\Fixtures\SupportBotAgent;

use function Laravel\Ai\agent;

// --- Test 1: BlogWriter End-to-End ---
// Covers: assertContains, assertLengthGreaterThan, assertMeets, assertDoesNotMeet
test('BlogWriter creates engaging content', function () {
    evaluate(BlogWriterAgent::class)
        ->prompt('Write a blog post about modern PHP features')
        ->assertContains('PHP')
        ->assertLengthGreaterThan(100)
        ->assertMeets('The content explains at least 1 PHP feature')
        ->assertMeets('The writing style is engaging and accessible')
        ->assertDoesNotMeet('Contains offensive language or inappropriate content');
})->group('usage-tests');

// --- Test 2: DataExtractor Structured Output ---
// Covers: agent() helper, schema, assertHasProperty, assertMatchesArray, run() + expect()
test('DataExtractor structured output', function () {
    $result = evaluate(fn () => agent(
        instructions: 'Extract contact information from the text. Be precise and exact.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('John Smith, Email: john@acme.com')
        ->assertHasProperty('name', 'John Smith')
        ->assertHasProperties(['name', 'email'])
        ->assertMatchesArray([
            'name' => 'John Smith',
            'email' => 'john@acme.com',
        ])
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class);
    expect($result['name'])->toBe('John Smith');
    expect($result['email'])->toBe('john@acme.com');
})->group('usage-tests');

// --- Test 3: SalesCoach Sampling with Rubrics ---
// Covers: samples(2), assertMeets(ProfessionalTone), assertMeets(ActionableAdvice)
test('SalesCoach consistently provides quality feedback', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Customer: "Your price is too high." Rep: "I understand your concern about pricing."')
        ->samples(2, minimum: 1)
        ->assertMeets('The feedback is constructive and actionable')
        ->assertMeets('Professional tone', 60)
        ->assertDoesNotMeet('The response is dismissive or rude');
})->group('usage-tests');

// --- Test 4: SalesCoach E2E describe Block ---
// Covers: run() inspect; assertMeets(Rubric) chained; dataset (2 cases)
describe('SalesCoach Agent E2E', function () {
    test('analyzes transcripts and provides feedback with Rubrics', function () {
        $result = evaluate(SalesCoachAgent::class)
            ->prompt('Customer: "Your price is too high." Rep: "I understand your concern."')
            ->run();

        expect($result)->toBeInstanceOf(EvalResult::class)
            ->and($result->text)->not->toBeEmpty();

        evaluate(SalesCoachAgent::class)
            ->prompt('Customer was very upset about the product quality.')
            ->assertMeets(new ProfessionalTone)
            ->assertMeets(new ActionableAdvice)
            ->assertMeets('Feedback is relevant to the customer interaction');
    })->group('usage-tests');

    it('handles various scenarios', function (EvalCase $case) {
        evaluate(SalesCoachAgent::class)
            ->withCase($case)
            ->assertMeets($case->expected)
            ->assertMeets(new ProfessionalTone);
    })->with([
        'objection handling' => fn () => EvalCase::make()
            ->prompt('Customer raised a pricing objection')
            ->expected('Provides techniques for handling price objections'),

        'closing techniques' => fn () => EvalCase::make()
            ->prompt('Rep failed to close the deal')
            ->expected('Suggests specific closing techniques'),
    ])->group('usage-tests');
});

// --- Test 5: Mixed Chain + BDD Complete ---
// Covers: all assertion types in one chain; complete BDD-style evaluation
test('mixed assertions and BDD-style chain', function () {
    evaluate(GeographyAgent::class)
        ->prompt('Tell me about Paris, the capital of France. Mention key landmarks.')
        ->assertContains('Paris')
        ->assertContains('France')
        ->assertLengthGreaterThan(50)
        ->assertNotEmpty()
        ->assertMeets('The response is factually correct about Paris')
        ->assertDoesNotMeet('The response contains incorrect geographic information');

    evaluate(GeographyAgent::class)
        ->whenPrompted('What is the capital of France?')
        ->toMeet('The response correctly identifies Paris as the capital')
        ->toBeSimilarTo('Paris is the capital city of France', threshold: 60);
})->group('usage-tests');

// --- Test 6: CopyWriter + SupportBot ---
// Covers: two agents, distinct patterns; assertContains + assertMeets + EvalCase
test('CopyWriter and SupportBot end-to-end', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->assertContains('Laravel')
        ->assertLengthLessThan(500)
        ->assertMeets('The tone is enthusiastic and engaging');

    $case = EvalCase::make()
        ->prompt('I want to return this product, it does not work properly.')
        ->expected('Polite acknowledgment with return instructions');

    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertMeets($case->expected)
        ->assertMeets('The response is empathetic and helpful');
})->group('usage-tests');
