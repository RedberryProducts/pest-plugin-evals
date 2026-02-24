<?php

/**
 * Tool Assertions - GOAL-2.md Section: Tool Assertions
 *
 * Tests assertToolNotUsed and tool invocation inspection.
 *
 * Consolidated: 3 tests → 1 test, 3 API calls → 1 call.
 */

use Tests\Integration\Fixtures\GeographyAgent;

// --- Test 1: Tool Assertions on Non-Tool Agent ---
// Covers: assertToolNotUsed(string), assertToolNotUsed(class), empty invocations
test('tool assertions on non-tool agent', function () {
    $result = evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertToolNotUsed('web_search')
        ->assertToolNotUsed('dangerous_tool')
        ->run();

    expect($result->toolInvocations)->toBeEmpty();
})->group('usage-tests');
