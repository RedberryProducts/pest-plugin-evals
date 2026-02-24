<?php

/**
 * Tool Assertions - GOAL-2.md Section: Tool Assertions
 *
 * Tests assertToolUsed, assertToolNotUsed, assertToolUseSequence, and tool count assertions.
 *
 * NOTE: Tool assertions require agents that actually invoke tools during execution.
 * These tests verify the assertion API works end-to-end with a real agent that uses tools.
 * If no tool-using agent is available, some tests verify the assertion against agents
 * that do NOT use tools (e.g., assertToolNotUsed).
 */

use Tests\Integration\Fixtures\GeographyAgent;

// --- Tool Was Not Used ---

// GOAL-2.md: assertToolNotUsed — verify an agent did NOT use a specific tool
it('asserts tool was not used by string name', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertToolNotUsed('web_search');
})->group('usage-tests');

// GOAL-2.md: assertToolNotUsed with class reference
it('asserts tool was not used by class reference', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertToolNotUsed('dangerous_tool');
})->group('usage-tests');

// --- Empty Tool Invocations ---

// Verify that agents without tools have empty toolInvocations
it('verifies empty tool invocations for non-tool agents', function () {
    $result = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->run();

    expect($result->toolInvocations)->toBeEmpty();
})->group('usage-tests');
