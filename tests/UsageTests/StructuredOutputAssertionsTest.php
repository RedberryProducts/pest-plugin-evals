<?php

/**
 * Structured Output Assertions - GOAL-2.md Section: Structured Output Assertions
 *
 * Tests assertHasKey, assertHasKeys, assertHasProperty, assertHasProperties,
 * assertMatchesArray, and run() + expect() patterns.
 *
 * NOTE: Structured output requires agents with schema definitions.
 * These tests use the agent() helper with a schema parameter for structured output.
 */

use Redberry\Evals\EvalResult;

use function Laravel\Ai\agent;

// --- assertHasKey ---

// GOAL-2.md: assertHasKey('name')
it('asserts structured output has a key', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertHasKey('name')
        ->assertHasKey('email');
})->group('usage-tests');

// GOAL-2.md: assertHasKey('name', 'John Doe') — key with expected value
it('asserts structured output has key with value', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertHasKey('name', 'John Doe')
        ->assertHasKey('email', 'john@example.com');
})->group('usage-tests');

// --- assertHasKeys ---

// GOAL-2.md: assertHasKeys(['name', 'email'])
it('asserts structured output has multiple keys', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertHasKeys(['name', 'email']);
})->group('usage-tests');

// --- assertHasProperty / assertHasProperties ---

// GOAL-2.md: assertHasProperty (alias for assertHasKey)
it('asserts structured output has property', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertHasProperty('name', 'John Doe');
})->group('usage-tests');

// GOAL-2.md: assertHasProperties(['name', 'email'])
it('asserts structured output has multiple properties', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertHasProperties(['name', 'email']);
})->group('usage-tests');

// --- assertMatchesArray ---

// GOAL-2.md: assertMatchesArray — partial array match
it('asserts structured output matches partial array', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertMatchesArray([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
})->group('usage-tests');

// --- run() + expect() ---

// GOAL-2.md: Call ->run() and use PEST's native expect() directly
it('uses run with native pest expect', function () {
    $result = evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class);
    expect($result['name'])->toBe('John Doe');
    expect($result['email'])->toBe('john@example.com');
})->group('usage-tests');

// --- assertArray ---

// GOAL-2.md: assertArray — verify structured output exists
it('asserts output is structured array', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name from the text.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract the name from: John Doe')
        ->assertArray();
})->group('usage-tests');

// --- Fluent structured assertions ---

// GOAL-2.md: Full example from "Structured Output Assertions" section
it('chains structured assertions fluently', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertHasKey('name')
        ->assertHasKey('email')
        ->assertHasKeys(['name', 'email'])
        ->assertHasProperty('name', 'John Doe')
        ->assertHasProperties(['name', 'email'])
        ->assertMatchesArray([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
})->group('usage-tests');
