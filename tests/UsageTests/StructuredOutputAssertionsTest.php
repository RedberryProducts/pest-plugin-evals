<?php

/**
 * Structured Output Assertions - GOAL-2.md Section: Structured Output Assertions
 *
 * Tests assertHasKey, assertHasKeys, assertHasProperty, assertHasProperties,
 * assertMatchesArray, assertArray, and run() + expect() patterns.
 *
 * Consolidated: 9 tests → 2 tests, 9 API calls → 2 calls.
 */

use Redberry\Evals\EvalResult;

use function Laravel\Ai\agent;

// --- Test 1: All Structured Assertions Chained ---
// Covers: assertHasKey, assertHasKey(k,v), assertHasKeys, assertHasProperty,
//         assertHasProperties, assertMatchesArray, assertArray
test('all structured assertions chained', function () {
    evaluate(fn () => agent(
        instructions: 'Extract the name and email from the given text. Be precise.',
        schema: fn ($s) => [
            'name' => $s->string()->required(),      // @phpstan-ignore-line
            'email' => $s->string()->required(),      // @phpstan-ignore-line
        ],
    ))
        ->prompt('Extract user info from: John Doe, john@example.com')
        ->assertArray()
        ->assertHasKey('name')
        ->assertHasKey('email')
        ->assertHasKey('name', 'John Doe')
        ->assertHasKey('email', 'john@example.com')
        ->assertHasKeys(['name', 'email'])
        ->assertHasProperty('name', 'John Doe')
        ->assertHasProperties(['name', 'email'])
        ->assertMatchesArray([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
})->group('usage-tests');

// --- Test 2: run() with Native Expect ---
// Covers: run()→EvalResult, ArrayAccess, isStructured()
test('run with native pest expect and ArrayAccess', function () {
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
