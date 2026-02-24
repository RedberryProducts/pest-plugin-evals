<?php

use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Redberry\Evals\AgentRunner;
use Redberry\Evals\EvalResult;
use Redberry\Evals\SampleResults;

function makeUsage(): Usage
{
    return new Usage;
}

function makeMeta(): Meta
{
    return new Meta;
}

function makePlainResponse(string $text = 'Hello'): AgentResponse
{
    return new AgentResponse(
        invocationId: 'inv-1',
        text: $text,
        usage: makeUsage(),
        meta: makeMeta(),
    );
}

function makeStructuredResponse(array $structured, string $text = ''): StructuredAgentResponse
{
    return new StructuredAgentResponse(
        invocationId: 'inv-1',
        structured: $structured,
        text: $text,
        usage: makeUsage(),
        meta: makeMeta(),
    );
}

function mockAgent(AgentResponse $response): Agent
{
    $agent = Mockery::mock(Agent::class);
    $agent->shouldReceive('prompt')->once()->andReturn($response);

    return $agent;
}

describe('run with agent instance', function () {
    it('normalizes a plain text response', function () {
        $response = makePlainResponse('The capital is Paris');
        $agent = mockAgent($response);

        $runner = new AgentRunner;
        $result = $runner->run(
            agent: $agent,
            constructorArgs: [],
            prompt: 'What is the capital of France?',
        );

        expect($result)->toBeInstanceOf(EvalResult::class)
            ->and($result->text)->toBe('The capital is Paris')
            ->and($result->structured)->toBeNull()
            ->and($result->isStructured())->toBeFalse()
            ->and($result->toolInvocations)->toBeEmpty();
    });

    it('normalizes a structured response', function () {
        $response = makeStructuredResponse(
            structured: ['name' => 'John', 'age' => 30],
            text: 'structured output',
        );
        $agent = mockAgent($response);

        $runner = new AgentRunner;
        $result = $runner->run(
            agent: $agent,
            constructorArgs: [],
            prompt: 'Extract user info',
        );

        expect($result->isStructured())->toBeTrue()
            ->and($result->structured)->toBe(['name' => 'John', 'age' => 30])
            ->and($result['name'])->toBe('John')
            ->and($result['age'])->toBe(30);
    });

    it('normalizes tool calls with results', function () {
        $response = makePlainResponse('Done');

        $toolCall = new ToolCall(
            id: 'call-1',
            name: 'search',
            arguments: ['query' => 'test'],
        );
        $toolResult = new ToolResult(
            id: 'call-1',
            name: 'search',
            arguments: ['query' => 'test'],
            result: 'found 5 results',
        );

        $response->toolCalls = new Collection([$toolCall]);
        $response->toolResults = new Collection([$toolResult]);

        $agent = mockAgent($response);

        $runner = new AgentRunner;
        $result = $runner->run(
            agent: $agent,
            constructorArgs: [],
            prompt: 'Search for test',
        );

        expect($result->toolInvocations)->toHaveCount(1);

        $invocation = $result->toolInvocations->first();
        expect($invocation->toolName)->toBe('search')
            ->and($invocation->arguments)->toBe(['query' => 'test'])
            ->and($invocation->result)->toBe('found 5 results')
            ->and($invocation->toolClass)->toBeNull();
    });

    it('maps tool classes from HasTools agent', function () {
        $response = makePlainResponse('Done');

        $tool = Mockery::mock(Tool::class);
        $tool->shouldReceive('name')->andReturn('SearchTool');

        // Use the basename that buildToolClassMap will derive since
        // method_exists doesn't detect Mockery's dynamically mocked methods.
        $expectedToolName = class_basename($tool);

        $toolCall = new ToolCall(
            id: 'call-1',
            name: $expectedToolName,
            arguments: [],
        );
        $response->toolCalls = new Collection([$toolCall]);
        $response->toolResults = new Collection;

        // Agent that implements both Agent and HasTools
        $agent = Mockery::mock(Agent::class, HasTools::class);
        $agent->shouldReceive('prompt')->once()->andReturn($response);
        $agent->shouldReceive('tools')->once()->andReturn([$tool]);

        $runner = new AgentRunner;
        $result = $runner->run(
            agent: $agent,
            constructorArgs: [],
            prompt: 'test',
        );

        expect($result->toolInvocations)->toHaveCount(1);
        expect($result->toolInvocations->first()->toolClass)->toBe(get_class($tool));
    });

    it('preserves the raw response', function () {
        $response = makePlainResponse('test');
        $agent = mockAgent($response);

        $runner = new AgentRunner;
        $result = $runner->run(
            agent: $agent,
            constructorArgs: [],
            prompt: 'test',
        );

        expect($result->response)->toBe($response);
    });
});

describe('run with closure', function () {
    it('resolves agent from closure', function () {
        $response = makePlainResponse('From closure');
        $agent = mockAgent($response);

        $runner = new AgentRunner;
        $result = $runner->run(
            agent: fn () => $agent,
            constructorArgs: [],
            prompt: 'test',
        );

        expect($result->text)->toBe('From closure');
    });

    it('throws when closure returns non-Agent', function () {
        $runner = new AgentRunner;

        expect(fn () => $runner->run(
            agent: fn () => 'not an agent', // @phpstan-ignore return.type
            constructorArgs: [],
            prompt: 'test',
        ))->toThrow(InvalidArgumentException::class, 'Agent');
    });
});

describe('runSamples', function () {
    it('runs N times and returns SampleResults', function () {
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('prompt')
            ->times(3)
            ->andReturn(
                makePlainResponse('run 1'),
                makePlainResponse('run 2'),
                makePlainResponse('run 3'),
            );

        $runner = new AgentRunner;
        $samples = $runner->runSamples(
            agent: $agent,
            constructorArgs: [],
            prompt: 'test',
            count: 3,
        );

        expect($samples)->toBeInstanceOf(SampleResults::class)
            ->and($samples->count())->toBe(3)
            ->and($samples->first()->text)->toBe('run 1')
            ->and($samples->last()->text)->toBe('run 3');
    });

    it('passes minimum to SampleResults', function () {
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('prompt')
            ->times(3)
            ->andReturn(makePlainResponse());

        $runner = new AgentRunner;
        $samples = $runner->runSamples(
            agent: $agent,
            constructorArgs: [],
            prompt: 'test',
            count: 3,
            minimum: 2,
        );

        expect($samples->minimum())->toBe(2);
    });

    it('defaults to single sample', function () {
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('prompt')->once()->andReturn(makePlainResponse());

        $runner = new AgentRunner;
        $samples = $runner->runSamples(
            agent: $agent,
            constructorArgs: [],
            prompt: 'test',
        );

        expect($samples->count())->toBe(1);
    });
});
