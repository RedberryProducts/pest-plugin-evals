<?php

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Redberry\Evals\EvalContext;
use Redberry\Evals\EvalResult;

it('stores input, output, expected, and result', function () {
    $evalResult = new EvalResult(
        text: 'Paris',
        structured: null,
        toolInvocations: new Collection,
        response: new AgentResponse(
            invocationId: 'inv-1',
            text: 'Paris',
            usage: new Usage,
            meta: new Meta,
        ),
    );

    $context = new EvalContext(
        input: 'What is the capital of France?',
        output: 'Paris',
        expected: 'Paris',
        result: $evalResult,
    );

    expect($context->input)->toBe('What is the capital of France?')
        ->and($context->output)->toBe('Paris')
        ->and($context->expected)->toBe('Paris')
        ->and($context->result)->toBe($evalResult);
});

it('accepts null expected value', function () {
    $evalResult = new EvalResult(
        text: 'output',
        structured: null,
        toolInvocations: new Collection,
        response: new AgentResponse(
            invocationId: 'inv-1',
            text: 'output',
            usage: new Usage,
            meta: new Meta,
        ),
    );

    $context = new EvalContext(
        input: 'prompt',
        output: 'output',
        expected: null,
        result: $evalResult,
    );

    expect($context->expected)->toBeNull();
});

it('accepts mixed expected types', function () {
    $evalResult = new EvalResult(
        text: 'output',
        structured: null,
        toolInvocations: new Collection,
        response: new AgentResponse(
            invocationId: 'inv-1',
            text: 'output',
            usage: new Usage,
            meta: new Meta,
        ),
    );

    // Array expected
    $context = new EvalContext(
        input: 'prompt',
        output: 'output',
        expected: ['name' => 'John', 'age' => 30],
        result: $evalResult,
    );

    expect($context->expected)->toBe(['name' => 'John', 'age' => 30]);
});
