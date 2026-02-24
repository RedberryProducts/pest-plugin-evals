<?php

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Redberry\Evals\EvalResult;
use Redberry\Evals\ToolInvocation;

function makeAgentResponse(string $text = 'Hello world'): AgentResponse
{
    return new AgentResponse(
        invocationId: 'inv-1',
        text: $text,
        usage: new Usage,
        meta: new Meta,
    );
}

it('stores text output', function () {
    $result = new EvalResult(
        text: 'Hello world',
        structured: null,
        toolInvocations: new Collection,
        response: makeAgentResponse(),
    );

    expect($result->text)->toBe('Hello world');
});

it('is Stringable and returns text', function () {
    $result = new EvalResult(
        text: 'The answer is 42',
        structured: null,
        toolInvocations: new Collection,
        response: makeAgentResponse('The answer is 42'),
    );

    expect((string) $result)->toBe('The answer is 42');
});

it('detects structured output', function () {
    $result = new EvalResult(
        text: '',
        structured: ['name' => 'John', 'age' => 30],
        toolInvocations: new Collection,
        response: makeAgentResponse(),
    );

    expect($result->isStructured())->toBeTrue()
        ->and($result->toArray())->toBe(['name' => 'John', 'age' => 30]);
});

it('returns false for non-structured output', function () {
    $result = new EvalResult(
        text: 'plain text',
        structured: null,
        toolInvocations: new Collection,
        response: makeAgentResponse(),
    );

    expect($result->isStructured())->toBeFalse()
        ->and($result->toArray())->toBeNull();
});

it('supports ArrayAccess for structured output', function () {
    $result = new EvalResult(
        text: '',
        structured: ['name' => 'John', 'city' => 'Paris'],
        toolInvocations: new Collection,
        response: makeAgentResponse(),
    );

    expect($result['name'])->toBe('John')
        ->and($result['city'])->toBe('Paris')
        ->and(isset($result['name']))->toBeTrue()
        ->and(isset($result['missing']))->toBeFalse()
        ->and($result['missing'])->toBeNull();
});

it('returns null for ArrayAccess when not structured', function () {
    $result = new EvalResult(
        text: 'plain',
        structured: null,
        toolInvocations: new Collection,
        response: makeAgentResponse(),
    );

    expect($result['anything'])->toBeNull()
        ->and(isset($result['anything']))->toBeFalse();
});

it('throws on ArrayAccess set', function () {
    $result = new EvalResult(
        text: '',
        structured: ['key' => 'value'],
        toolInvocations: new Collection,
        response: makeAgentResponse(),
    );

    expect(fn () => $result['key'] = 'new')->toThrow(LogicException::class, 'immutable');
});

it('throws on ArrayAccess unset', function () {
    $result = new EvalResult(
        text: '',
        structured: ['key' => 'value'],
        toolInvocations: new Collection,
        response: makeAgentResponse(),
    );

    expect(fn () => $result->offsetUnset('key'))->toThrow(LogicException::class, 'immutable');
});

it('stores tool invocations', function () {
    $invocations = new Collection([
        new ToolInvocation(toolName: 'search', toolClass: null, arguments: ['q' => 'test'], result: 'found'),
    ]);

    $result = new EvalResult(
        text: 'Done',
        structured: null,
        toolInvocations: $invocations,
        response: makeAgentResponse(),
    );

    expect($result->toolInvocations)->toHaveCount(1)
        ->and($result->toolInvocations->first()->toolName)->toBe('search');
});

it('preserves the raw AgentResponse', function () {
    $response = makeAgentResponse('test');

    $result = new EvalResult(
        text: 'test',
        structured: null,
        toolInvocations: new Collection,
        response: $response,
    );

    expect($result->response)->toBe($response);
});
