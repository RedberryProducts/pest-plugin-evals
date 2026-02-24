<?php

/**
 * Full Examples - GOAL-2.md Section: Full Examples
 *
 * End-to-end tests covering complete workflows from the GOAL-2.md examples.
 */

use Redberry\Evals\EvalCase;
use Redberry\Evals\EvalResult;
use Redberry\Evals\JudgeResult;
use Redberry\Evals\SampleResults;
use Tests\Integration\Fixtures\GeographyAgent;
use Tests\UsageTests\Fixtures\ActionableAdvice;
use Tests\UsageTests\Fixtures\BlogWriterAgent;
use Tests\UsageTests\Fixtures\CopyWriterAgent;
use Tests\UsageTests\Fixtures\ProfessionalTone;
use Tests\UsageTests\Fixtures\SalesCoachAgent;
use Tests\UsageTests\Fixtures\SupportBotAgent;

use function Laravel\Ai\agent;

// --- Basic Agent Evaluation ---

// GOAL-2.md: BlogWriter creates engaging content
test('BlogWriter creates engaging content', function () {
    evaluate(BlogWriterAgent::class)
        ->prompt('Write a blog post about modern PHP features')
        ->assertContains('PHP')
        ->assertLengthGreaterThan(100)
        ->assertMeets('The content explains at least 1 PHP feature')
        ->assertMeets('The writing style is engaging and accessible')
        ->assertDoesNotMeet('Contains offensive language or inappropriate content');
})->group('usage-tests');

// --- Structured Output Agent ---

// GOAL-2.md: DataExtractor parses contact information (fluent assertions)
test('DataExtractor parses contact information fluently', function () {
    evaluate(fn () => agent(
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
        ]);
})->group('usage-tests');

// GOAL-2.md: DataExtractor with run() + expect()
test('DataExtractor parsing with run and expect', function () {
    $result = evaluate(fn () => agent(
        instructions: 'Extract the name and email from the text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('John Smith, Email: john@acme.com')
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class);
    expect($result['name'])->toBe('John Smith');
    expect($result['email'])->toBe('john@acme.com');
})->group('usage-tests');

// --- Dataset-Driven Evaluation ---

// GOAL-2.md: email_extraction_cases dataset
dataset('email_extraction_cases', [
    'simple' => fn () => EvalCase::make()
        ->prompt('What is the email in: contact@example.com')
        ->expected('contact@example.com'),

    'with_context' => fn () => EvalCase::make()
        ->prompt('What is the support email in: Contact us at hello@world.com for support')
        ->expected('hello@world.com'),
]);

it('extracts emails accurately', function (EvalCase $case) {
    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertContains($case->expected);
})->with('email_extraction_cases')->group('usage-tests');

// --- Sampling Evaluation ---

// GOAL-2.md: SalesCoach consistently provides quality feedback
test('SalesCoach consistently provides quality feedback', function () {
    evaluate(SalesCoachAgent::class)
        ->prompt('Customer: "Your price is too high." Rep: "I understand your concern about pricing."')
        ->samples(3, minimum: 2)
        ->assertMeets('The feedback is constructive and actionable')
        ->assertMeets('Professional tone', 60)
        ->assertDoesNotMeet('The response is dismissive or rude');
})->group('usage-tests');

// --- Complete E2E Flow with describe() ---

describe('SalesCoach Agent E2E', function () {
    // GOAL-2.md: analyzes transcripts
    test('analyzes transcripts', function () {
        $result = evaluate(SalesCoachAgent::class)
            ->prompt('Customer: "Your price is too high." Rep: "I understand your concern."')
            ->run();

        expect($result)->toBeInstanceOf(EvalResult::class)
            ->and($result->text)->not->toBeEmpty();
    })->group('usage-tests');

    // GOAL-2.md: provides constructive feedback with Rubric
    test('provides constructive feedback', function () {
        evaluate(SalesCoachAgent::class)
            ->prompt('Customer was very upset about the product quality.')
            ->assertMeets(new ProfessionalTone)
            ->assertMeets(new ActionableAdvice)
            ->assertMeets('Feedback is relevant to the customer interaction');
    })->group('usage-tests');

    // GOAL-2.md: handles various scenarios with inline dataset
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

// --- Mixed Assertions End-to-End ---

// GOAL-2.md: Combining deterministic, judge, and BDD-style in one chain
test('combines all assertion types in one chain', function () {
    evaluate(GeographyAgent::class)
        ->prompt('Tell me about Paris, the capital of France. Mention key landmarks.')
        ->assertContains('Paris')
        ->assertContains('France')
        ->assertLengthGreaterThan(50)
        ->assertNotEmpty()
        ->assertMeets('The response is factually correct about Paris')
        ->assertDoesNotMeet('The response contains incorrect geographic information');
})->group('usage-tests');

// --- BDD-Style Full Example ---

// GOAL-2.md: Complete BDD-style chain
test('complete BDD-style evaluation', function () {
    evaluate(GeographyAgent::class)
        ->whenPrompted('What is the capital of France?')
        ->toMeet('The response correctly identifies Paris as the capital')
        ->toBeSimilarTo('Paris is the capital city of France', threshold: 60);
})->group('usage-tests');

// --- Sampling with judge() for aggregate results ---

test('sampling with aggregate judge results', function () {
    $samples = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3)
        ->judge('The response correctly identifies Paris');

    expect($samples)->toBeInstanceOf(SampleResults::class);
    expect($samples->passRate())->toBeGreaterThan(50);
    expect($samples->passed())->toBeTrue();

    $samples->judgeResults()->each(function (JudgeResult $result) {
        expect($result->reasoning)->not->toBeEmpty();
    });
})->group('usage-tests');

// --- CopyWriter Short-Form Content ---

test('CopyWriter produces tweet-length content', function () {
    evaluate(CopyWriterAgent::class)
        ->prompt('Write a tweet about Laravel')
        ->assertContains('Laravel')
        ->assertLengthLessThan(500)
        ->assertMeets('The tone is enthusiastic and engaging');
})->group('usage-tests');

// --- Support Bot with EvalCase ---

test('SupportBot handles return request', function () {
    $case = EvalCase::make()
        ->prompt('I want to return this product, it does not work properly.')
        ->expected('Polite acknowledgment with return instructions');

    evaluate(SupportBotAgent::class)
        ->withCase($case)
        ->assertMeets($case->expected)
        ->assertMeets('The response is empathetic and helpful');
})->group('usage-tests');
