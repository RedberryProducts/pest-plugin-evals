<?php

use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\EvalBuilder;
use Redberry\Evals\EvalCase;
use Redberry\Evals\EvalContext;
use Redberry\Evals\EvalResult;
use Redberry\Evals\JudgeResult;
use Redberry\Evals\SampleResults;

// ──────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────

function plainResponse(string $text = 'Hello'): AgentResponse
{
    return new AgentResponse(
        invocationId: 'inv-1',
        text: $text,
        usage: new Usage,
        meta: new Meta,
    );
}

function structuredResponse(array $structured, string $text = ''): StructuredAgentResponse
{
    return new StructuredAgentResponse(
        invocationId: 'inv-1',
        structured: $structured,
        text: $text,
        usage: new Usage,
        meta: new Meta,
    );
}

function responseWithTools(string $text, array $toolCalls, array $toolResults = []): AgentResponse
{
    $response = plainResponse($text);
    $response->toolCalls = new Collection(
        array_map(fn ($tc) => new ToolCall(
            id: $tc['id'],
            name: $tc['name'],
            arguments: $tc['arguments'] ?? [],
        ), $toolCalls),
    );
    $response->toolResults = new Collection(
        array_map(fn ($tr) => new ToolResult(
            id: $tr['id'],
            name: $tr['name'],
            arguments: $tr['arguments'] ?? [],
            result: $tr['result'] ?? null,
        ), $toolResults),
    );

    return $response;
}

function fakeAgent(AgentResponse $response): Agent
{
    $agent = Mockery::mock(Agent::class);
    $agent->shouldReceive('prompt')->once()->andReturn($response);

    return $agent;
}

function fakeAgentTimes(int $times, AgentResponse ...$responses): Agent
{
    $agent = Mockery::mock(Agent::class);
    $agent->shouldReceive('prompt')->times($times)->andReturn(...$responses);

    return $agent;
}

function builder(AgentResponse $response): EvalBuilder
{
    return new EvalBuilder(fakeAgent($response));
}

function sampledBuilder(int $count, AgentResponse ...$responses): EvalBuilder
{
    return (new EvalBuilder(fakeAgentTimes($count, ...$responses)))
        ->samples($count);
}

// ──────────────────────────────────────────────────────────────────
// Configuration
// ──────────────────────────────────────────────────────────────────

describe('configuration', function () {
    it('sets prompt', function () {
        $result = builder(plainResponse('Paris'))
            ->prompt('What is the capital of France?')
            ->run();

        expect($result)->toBeInstanceOf(EvalResult::class)
            ->and($result->text)->toBe('Paris');
    });

    it('throws LogicException when no prompt is set', function () {
        $agent = Mockery::mock(Agent::class);
        $b = new EvalBuilder($agent);

        expect(fn () => $b->run())->toThrow(LogicException::class, 'No prompt set');
    });

    it('sets expected value', function () {
        $b = builder(plainResponse('Paris'));
        $b->prompt('test')->expected('Paris');

        expect($b->run()->text)->toBe('Paris');
    });

    it('loads from EvalCase', function () {
        $case = EvalCase::make()
            ->prompt('What is AI?')
            ->expected('Artificial Intelligence')
            ->attachments(['file1']);

        $b = builder(plainResponse('AI answer'));
        $b->withCase($case);

        expect($b->run()->text)->toBe('AI answer');
    });

    it('sets provider and model', function () {
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('prompt')
            ->once()
            ->withArgs(function (string $prompt, array $attachments, ?string $provider, ?string $model) {
                return $provider === 'openai' && $model === 'gpt-4o';
            })
            ->andReturn(plainResponse());

        $b = new EvalBuilder($agent);
        $b->prompt('test')
            ->provider('openai')
            ->model('gpt-4o')
            ->run();
    });

    it('sets provider via Lab enum', function () {
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('prompt')
            ->once()
            ->withArgs(function (string $prompt, array $attachments, ?string $provider) {
                return $provider === Lab::OpenAI->value;
            })
            ->andReturn(plainResponse());

        $b = new EvalBuilder($agent);
        $b->prompt('test')
            ->provider(Lab::OpenAI)
            ->run();
    });

    it('accepts closure as agent', function () {
        $agent = fakeAgent(plainResponse('From closure'));

        $b = new EvalBuilder(fn () => $agent);
        $result = $b->prompt('test')->run();

        expect($result->text)->toBe('From closure');
    });

    it('samples returns SampleResults', function () {
        $b = sampledBuilder(
            3,
            plainResponse('a'),
            plainResponse('b'),
            plainResponse('c'),
        );

        $result = $b->prompt('test')->run();

        expect($result)->toBeInstanceOf(SampleResults::class)
            ->and($result->count())->toBe(3);
    });

    it('samples with one run still returns SampleResults', function () {
        $result = sampledBuilder(1, plainResponse('a'))
            ->prompt('test')
            ->run();

        expect($result)->toBeInstanceOf(SampleResults::class)
            ->and($result->count())->toBe(1);
    });

    it('uses configured sampling defaults when arguments are omitted', function () {
        config()->set('evals.sampling.default_samples', 2);
        config()->set('evals.sampling.default_minimum', 1);

        $result = (new EvalBuilder(fakeAgentTimes(2, plainResponse('a'), plainResponse('b'))))
            ->samples()
            ->prompt('test')
            ->run();

        expect($result)->toBeInstanceOf(SampleResults::class)
            ->and($result->count())->toBe(2)
            ->and($result->minimum())->toBe(1);
    });

    it('does not apply configured minimum when an explicit sample count is provided', function () {
        config()->set('evals.sampling.default_minimum', 1);

        $result = (new EvalBuilder(fakeAgentTimes(2, plainResponse('a'), plainResponse('b'))))
            ->samples(2)
            ->prompt('test')
            ->run();

        expect($result)->toBeInstanceOf(SampleResults::class)
            ->and($result->count())->toBe(2)
            ->and($result->minimum())->toBeNull();
    });

    it('clamps zero sample count to at least one run', function () {
        $result = (new EvalBuilder(fakeAgentTimes(1, plainResponse('a'))))
            ->samples(0)
            ->prompt('test')
            ->run();

        expect($result)->toBeInstanceOf(SampleResults::class)
            ->and($result->count())->toBe(1);
    });

    it('repeat is alias for samples', function () {
        $agent = fakeAgentTimes(2, plainResponse('a'), plainResponse('b'));
        $b = (new EvalBuilder($agent))->repeat(2);

        $result = $b->prompt('test')->run();

        expect($result)->toBeInstanceOf(SampleResults::class)
            ->and($result->count())->toBe(2);
    });

    it('whenPrompted is alias for prompt', function () {
        $result = builder(plainResponse('answer'))
            ->whenPrompted('question')
            ->run();

        expect($result->text)->toBe('answer');
    });

    it('caches result on subsequent calls', function () {
        // Agent expects exactly one call — second run() should use cached result
        $b = builder(plainResponse('once'));
        $b->prompt('test');

        $r1 = $b->run();
        $r2 = $b->run();

        expect($r1)->toBe($r2);
    });

    it('sets judge instructions fluently', function () {
        $result = builder(plainResponse('ok'))
            ->prompt('test')
            ->judgeInstructions('Custom evaluation context')
            ->run();

        expect($result->text)->toBe('ok');
    });

    it('sets attachments via dedicated method', function () {
        $result = builder(plainResponse('ok'))
            ->prompt('test')
            ->attachments(['file1.pdf'])
            ->run();

        expect($result->text)->toBe('ok');
    });

    it('sets attachments via prompt parameter', function () {
        $result = builder(plainResponse('ok'))
            ->prompt('test', attachments: ['file1.pdf'])
            ->run();

        expect($result->text)->toBe('ok');
    });
});

// ──────────────────────────────────────────────────────────────────
// Deterministic Assertions — String Content
// ──────────────────────────────────────────────────────────────────

describe('assertContains', function () {
    it('passes when output contains string', function () {
        builder(plainResponse('The capital of France is Paris'))
            ->prompt('test')
            ->assertContains('Paris');
    });

    it('fails when output does not contain string', function () {
        expect(fn () => builder(plainResponse('The capital of France is Paris'))
            ->prompt('test')
            ->assertContains('London'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('accepts array and requires all to match', function () {
        builder(plainResponse('Paris is the capital of France'))
            ->prompt('test')
            ->assertContains(['Paris', 'France']);
    });

    it('fails when not all array items match', function () {
        expect(fn () => builder(plainResponse('Paris is the capital of France'))
            ->prompt('test')
            ->assertContains(['Paris', 'London']))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

describe('assertContainsAny', function () {
    it('passes when at least one needle matches', function () {
        builder(plainResponse('The answer is Paris'))
            ->prompt('test')
            ->assertContainsAny(['London', 'Paris', 'Berlin']);
    });

    it('fails when none match', function () {
        expect(fn () => builder(plainResponse('The answer is Paris'))
            ->prompt('test')
            ->assertContainsAny(['London', 'Berlin']))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

describe('assertNotContains', function () {
    it('passes when output does not contain string', function () {
        builder(plainResponse('The answer is Paris'))
            ->prompt('test')
            ->assertNotContains('London');
    });

    it('fails when output contains the string', function () {
        expect(fn () => builder(plainResponse('The answer is Paris'))
            ->prompt('test')
            ->assertNotContains('Paris'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

describe('assertMatches', function () {
    it('passes when output matches regex', function () {
        builder(plainResponse('The answer is 42'))
            ->prompt('test')
            ->assertMatches('/\d+/');
    });

    it('fails when output does not match regex', function () {
        expect(fn () => builder(plainResponse('no numbers here'))
            ->prompt('test')
            ->assertMatches('/\d+/'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

// ──────────────────────────────────────────────────────────────────
// Deterministic Assertions — Length
// ──────────────────────────────────────────────────────────────────

describe('length assertions', function () {
    it('assertLengthLessThan passes with short output', function () {
        builder(plainResponse('Hi'))
            ->prompt('test')
            ->assertLengthLessThan(10);
    });

    it('assertLengthLessThan fails with long output', function () {
        expect(fn () => builder(plainResponse('Hello World'))
            ->prompt('test')
            ->assertLengthLessThan(5))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertLengthGreaterThan passes with long output', function () {
        builder(plainResponse('Hello World'))
            ->prompt('test')
            ->assertLengthGreaterThan(5);
    });

    it('assertLengthGreaterThan fails with short output', function () {
        expect(fn () => builder(plainResponse('Hi'))
            ->prompt('test')
            ->assertLengthGreaterThan(10))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertLengthBetween passes within range', function () {
        builder(plainResponse('Hello'))
            ->prompt('test')
            ->assertLengthBetween(3, 10);
    });

    it('assertLengthBetween fails outside range', function () {
        expect(fn () => builder(plainResponse('Hi'))
            ->prompt('test')
            ->assertLengthBetween(5, 10))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

// ──────────────────────────────────────────────────────────────────
// Deterministic Assertions — JSON
// ──────────────────────────────────────────────────────────────────

describe('JSON assertions', function () {
    it('assertJson passes with valid JSON', function () {
        builder(plainResponse('{"name": "John"}'))
            ->prompt('test')
            ->assertJson();
    });

    it('assertJson fails with invalid JSON', function () {
        expect(fn () => builder(plainResponse('not json'))
            ->prompt('test')
            ->assertJson())
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertJsonPath passes with matching value', function () {
        builder(plainResponse('{"user": {"name": "John", "age": 30}}'))
            ->prompt('test')
            ->assertJsonPath('user.name', 'John');
    });

    it('assertJsonPath uses structured output when available', function () {
        builder(structuredResponse(['user' => ['name' => 'John']]))
            ->prompt('test')
            ->assertJsonPath('user.name', 'John');
    });

    it('assertJsonPath fails with wrong value', function () {
        expect(fn () => builder(plainResponse('{"name": "John"}'))
            ->prompt('test')
            ->assertJsonPath('name', 'Jane'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertJsonStructure passes with matching structure', function () {
        builder(plainResponse('{"name": "John", "address": {"city": "Paris"}}'))
            ->prompt('test')
            ->assertJsonStructure([
                'name',
                'address' => ['city'],
            ]);
    });

    it('assertJsonStructure fails when key missing', function () {
        expect(fn () => builder(plainResponse('{"name": "John"}'))
            ->prompt('test')
            ->assertJsonStructure(['name', 'age']))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertJsonPath fails with non-array output', function () {
        expect(fn () => builder(plainResponse('just a string'))
            ->prompt('test')
            ->assertJsonPath('key', 'value'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertJsonStructure fails with non-array output', function () {
        expect(fn () => builder(plainResponse('not json at all'))
            ->prompt('test')
            ->assertJsonStructure(['key']))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertJsonStructure fails when nested key is missing', function () {
        expect(fn () => builder(plainResponse('{"user": {"name": "John"}}'))
            ->prompt('test')
            ->assertJsonStructure(['user' => ['name', 'email']]))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertJsonStructure fails when nested value is not array', function () {
        expect(fn () => builder(plainResponse('{"user": "not-an-object"}'))
            ->prompt('test')
            ->assertJsonStructure(['user' => ['name']]))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertJsonStructure fails with deeply nested structure mismatch', function () {
        expect(fn () => builder(plainResponse('{"user": {"address": {"city": "Paris"}}}'))
            ->prompt('test')
            ->assertJsonStructure(['user' => ['address' => ['city', 'zip']]]))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

// ──────────────────────────────────────────────────────────────────
// Deterministic Assertions — Type
// ──────────────────────────────────────────────────────────────────

describe('type assertions', function () {
    it('assertString passes for plain text', function () {
        builder(plainResponse('text'))
            ->prompt('test')
            ->assertString();
    });

    it('assertString fails for structured output', function () {
        expect(fn () => builder(structuredResponse(['key' => 'val']))
            ->prompt('test')
            ->assertString())
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertArray passes for structured output', function () {
        builder(structuredResponse(['key' => 'val']))
            ->prompt('test')
            ->assertArray();
    });

    it('assertArray fails for plain text', function () {
        expect(fn () => builder(plainResponse('text'))
            ->prompt('test')
            ->assertArray())
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertNotEmpty passes for non-empty text', function () {
        builder(plainResponse('content'))
            ->prompt('test')
            ->assertNotEmpty();
    });

    it('assertNotEmpty passes for non-empty structured', function () {
        builder(structuredResponse(['key' => 'val']))
            ->prompt('test')
            ->assertNotEmpty();
    });

    it('assertNotEmpty fails for empty text', function () {
        expect(fn () => builder(plainResponse(''))
            ->prompt('test')
            ->assertNotEmpty())
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

// ──────────────────────────────────────────────────────────────────
// Deterministic Assertions — Equality
// ──────────────────────────────────────────────────────────────────

describe('equality assertions', function () {
    it('assertEquals passes with exact text match', function () {
        builder(plainResponse('Paris'))
            ->prompt('test')
            ->assertEquals('Paris');
    });

    it('assertEquals fails with text mismatch', function () {
        expect(fn () => builder(plainResponse('Paris'))
            ->prompt('test')
            ->assertEquals('London'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertEquals passes with exact structured match', function () {
        builder(structuredResponse(['a' => 1, 'b' => 2]))
            ->prompt('test')
            ->assertEquals(['a' => 1, 'b' => 2]);
    });

    it('assertMatchesArray passes with subset match', function () {
        builder(structuredResponse(['name' => 'John', 'age' => 30, 'city' => 'Paris']))
            ->prompt('test')
            ->assertMatchesArray(['name' => 'John', 'age' => 30]);
    });

    it('assertMatchesArray fails when key missing', function () {
        expect(fn () => builder(structuredResponse(['name' => 'John']))
            ->prompt('test')
            ->assertMatchesArray(['name' => 'John', 'age' => 30]))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertMatchesArray fails for non-structured output', function () {
        expect(fn () => builder(plainResponse('text'))
            ->prompt('test')
            ->assertMatchesArray(['name' => 'John']))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

// ──────────────────────────────────────────────────────────────────
// Tool Assertions
// ──────────────────────────────────────────────────────────────────

describe('tool assertions', function () {
    it('assertToolUsed passes when tool was called', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search', 'arguments' => ['q' => 'test']],
        ], [
            ['id' => 'c1', 'name' => 'search', 'arguments' => ['q' => 'test'], 'result' => 'found'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUsed('search');
    });

    it('assertToolUsed fails when tool was not called', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
        ]);

        expect(fn () => builder($response)
            ->prompt('test')
            ->assertToolUsed('calculator'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertToolUsed with array constraint passes on exact args match', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search', 'arguments' => ['query' => 'PHP']],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUsed('search', ['query' => 'PHP']);
    });

    it('assertToolUsed with closure constraint', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search', 'arguments' => ['query' => 'PHP']],
        ], [
            ['id' => 'c1', 'name' => 'search', 'arguments' => ['query' => 'PHP'], 'result' => 'found'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUsed('search', fn ($inv) => $inv->arguments['query'] === 'PHP');
    });

    it('assertToolNotUsed passes when tool was not called', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolNotUsed('calculator');
    });

    it('assertToolNotUsed fails when tool was called', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
        ]);

        expect(fn () => builder($response)
            ->prompt('test')
            ->assertToolNotUsed('search'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertToolUseSequence passes with correct order', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
            ['id' => 'c2', 'name' => 'fetch'],
            ['id' => 'c3', 'name' => 'summarize'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUseSequence(['search', 'fetch', 'summarize']);
    });

    it('assertToolUseSequence passes with subsequence (interleaved tools)', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
            ['id' => 'c2', 'name' => 'log'],
            ['id' => 'c3', 'name' => 'fetch'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUseSequence(['search', 'fetch']);
    });

    it('assertToolUseSequence fails with wrong order', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'fetch'],
            ['id' => 'c2', 'name' => 'search'],
        ]);

        expect(fn () => builder($response)
            ->prompt('test')
            ->assertToolUseSequence(['search', 'fetch']))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertToolUseSequence breaks early when all matched with trailing invocations', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
            ['id' => 'c2', 'name' => 'fetch'],
            ['id' => 'c3', 'name' => 'log'],
            ['id' => 'c4', 'name' => 'cleanup'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUseSequence(['search', 'fetch']);
    });

    it('assertToolUsedTimes passes with exact count', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
            ['id' => 'c2', 'name' => 'search'],
            ['id' => 'c3', 'name' => 'fetch'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUsedTimes('search', 2);
    });

    it('assertToolUsedTimes fails with wrong count', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
        ]);

        expect(fn () => builder($response)
            ->prompt('test')
            ->assertToolUsedTimes('search', 2))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertToolUsedAtLeast passes', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
            ['id' => 'c2', 'name' => 'search'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUsedAtLeast('search', 1);
    });

    it('assertToolUsedAtMost passes', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
        ]);

        builder($response)
            ->prompt('test')
            ->assertToolUsedAtMost('search', 2);
    });

    it('assertToolUsedAtLeast fails when count too low', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
        ]);

        expect(fn () => builder($response)
            ->prompt('test')
            ->assertToolUsedAtLeast('search', 5))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertToolUsedAtMost fails when count too high', function () {
        $response = responseWithTools('Done', [
            ['id' => 'c1', 'name' => 'search'],
            ['id' => 'c2', 'name' => 'search'],
            ['id' => 'c3', 'name' => 'search'],
        ]);

        expect(fn () => builder($response)
            ->prompt('test')
            ->assertToolUsedAtMost('search', 1))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

// ──────────────────────────────────────────────────────────────────
// Structured Assertions
// ──────────────────────────────────────────────────────────────────

describe('structured assertions', function () {
    it('assertHasKey passes for existing key', function () {
        builder(structuredResponse(['name' => 'John', 'age' => 30]))
            ->prompt('test')
            ->assertHasKey('name');
    });

    it('assertHasKey passes with value check', function () {
        builder(structuredResponse(['name' => 'John']))
            ->prompt('test')
            ->assertHasKey('name', 'John');
    });

    it('assertHasKey fails for missing key', function () {
        expect(fn () => builder(structuredResponse(['name' => 'John']))
            ->prompt('test')
            ->assertHasKey('age'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertHasKey fails with wrong value', function () {
        expect(fn () => builder(structuredResponse(['name' => 'John']))
            ->prompt('test')
            ->assertHasKey('name', 'Jane'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertHasKey fails for non-structured output', function () {
        expect(fn () => builder(plainResponse('text'))
            ->prompt('test')
            ->assertHasKey('name'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertHasKey supports dot-notation', function () {
        builder(structuredResponse(['user' => ['name' => 'John']]))
            ->prompt('test')
            ->assertHasKey('user.name', 'John');
    });

    it('assertHasKeys passes with all keys present', function () {
        builder(structuredResponse(['name' => 'John', 'age' => 30, 'city' => 'Paris']))
            ->prompt('test')
            ->assertHasKeys(['name', 'age', 'city']);
    });

    it('assertHasProperty is alias for assertHasKey', function () {
        builder(structuredResponse(['name' => 'John']))
            ->prompt('test')
            ->assertHasProperty('name', 'John');
    });

    it('assertHasProperties is alias for assertHasKeys', function () {
        builder(structuredResponse(['name' => 'John', 'age' => 30]))
            ->prompt('test')
            ->assertHasProperties(['name', 'age']);
    });
});

// ──────────────────────────────────────────────────────────────────
// Judge Assertions (with mock Judge)
// ──────────────────────────────────────────────────────────────────

describe('assertPasses with custom judge', function () {
    it('passes when judge returns true', function () {
        $judge = Mockery::mock(Judge::class);
        $judge->shouldReceive('evaluate')
            ->once()
            ->andReturn(new JudgeResult(passed: true, score: 90, reasoning: 'Good'));

        builder(plainResponse('test output'))
            ->prompt('test')
            ->assertPasses($judge);
    });

    it('fails when judge returns false', function () {
        $judge = Mockery::mock(Judge::class);
        $judge->shouldReceive('evaluate')
            ->once()
            ->andReturn(new JudgeResult(passed: false, score: 20, reasoning: 'Bad'));

        expect(fn () => builder(plainResponse('test output'))
            ->prompt('test')
            ->assertPasses($judge))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });
});

// ──────────────────────────────────────────────────────────────────
// BDD Aliases
// ──────────────────────────────────────────────────────────────────

describe('BDD aliases', function () {
    it('toBe with scalar delegates to assertEquals', function () {
        builder(plainResponse('Paris'))
            ->prompt('test')
            ->toBe('Paris');
    });

    it('toBe with array delegates to assertMatchesArray', function () {
        builder(structuredResponse(['name' => 'John', 'age' => 30, 'extra' => true]))
            ->prompt('test')
            ->toBe(['name' => 'John', 'age' => 30]);
    });
});

// ──────────────────────────────────────────────────────────────────
// Fluent Chaining
// ──────────────────────────────────────────────────────────────────

describe('fluent chaining', function () {
    it('chains multiple assertions on same result', function () {
        builder(plainResponse('The capital of France is Paris'))
            ->prompt('test')
            ->assertContains('Paris')
            ->assertContains('France')
            ->assertNotContains('London')
            ->assertMatches('/Paris/')
            ->assertLengthGreaterThan(10)
            ->assertLengthLessThan(100)
            ->assertString()
            ->assertNotEmpty();
    });

    it('chains configuration methods fluently', function () {
        $result = builder(plainResponse('ok'))
            ->prompt('test')
            ->expected('ok')
            ->provider('openai')
            ->model('gpt-4o')
            ->judgeWith('anthropic', 'claude-3-haiku')
            ->run();

        expect($result->text)->toBe('ok');
    });
});

// ──────────────────────────────────────────────────────────────────
// Sampling Mode
// ──────────────────────────────────────────────────────────────────

describe('sampling mode', function () {
    it('assertContains passes when all samples match', function () {
        sampledBuilder(3, plainResponse('Paris'), plainResponse('Paris'), plainResponse('Paris'))
            ->prompt('test')
            ->assertContains('Paris');
    });

    it('assertContains fails when not enough samples match', function () {
        expect(fn () => sampledBuilder(
            3,
            plainResponse('Paris'),
            plainResponse('London'),
            plainResponse('Paris'),
        )
            ->prompt('test')
            ->assertContains('Paris'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('minimum threshold allows partial passes', function () {
        $agent = fakeAgentTimes(3, plainResponse('Paris'), plainResponse('London'), plainResponse('Paris'));
        $b = (new EvalBuilder($agent))->samples(3, minimum: 2);

        $b->prompt('test')->assertContains('Paris');
    });

    it('minimum threshold fails when not met', function () {
        $agent = fakeAgentTimes(3, plainResponse('Paris'), plainResponse('London'), plainResponse('Berlin'));
        $b = (new EvalBuilder($agent))->samples(3, minimum: 2);

        expect(fn () => $b->prompt('test')->assertContains('Paris'))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('assertPasses works in sampled mode', function () {
        $judge = Mockery::mock(Judge::class);
        $judge->shouldReceive('evaluate')
            ->times(2)
            ->andReturn(
                new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
                new JudgeResult(passed: true, score: 85, reasoning: 'Good'),
            );

        sampledBuilder(2, plainResponse('a'), plainResponse('b'))
            ->prompt('test')
            ->assertPasses($judge);
    });

    it('assertPasses fails in sampled mode when not enough pass', function () {
        $judge = Mockery::mock(Judge::class);
        $judge->shouldReceive('evaluate')
            ->times(2)
            ->andReturn(
                new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
                new JudgeResult(passed: false, score: 20, reasoning: 'Bad'),
            );

        expect(fn () => sampledBuilder(2, plainResponse('a'), plainResponse('b'))
            ->prompt('test')
            ->assertPasses($judge))
            ->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('sampled tool assertions work', function () {
        $r1 = responseWithTools('Done', [['id' => 'c1', 'name' => 'search']]);
        $r2 = responseWithTools('Done', [['id' => 'c2', 'name' => 'search']]);

        sampledBuilder(2, $r1, $r2)
            ->prompt('test')
            ->assertToolUsed('search');
    });

    it('sampled structured assertions work', function () {
        sampledBuilder(
            2,
            structuredResponse(['name' => 'John']),
            structuredResponse(['name' => 'Jane']),
        )
            ->prompt('test')
            ->assertHasKey('name');
    });
});

// ──────────────────────────────────────────────────────────────────
// evaluate() Global Function
// ──────────────────────────────────────────────────────────────────

describe('evaluate() function', function () {
    it('returns an EvalBuilder instance', function () {
        $agent = Mockery::mock(Agent::class);

        $b = evaluate($agent);

        expect($b)->toBeInstanceOf(EvalBuilder::class);
    });

    it('works with closure agent', function () {
        $agent = fakeAgent(plainResponse('via evaluate'));

        $result = evaluate(fn () => $agent)
            ->prompt('test')
            ->run();

        expect($result->text)->toBe('via evaluate');
    });
});

// ──────────────────────────────────────────────────────────────────
// judge() execution method
// ──────────────────────────────────────────────────────────────────

describe('judge() method', function () {
    it('builds context with expected value', function () {
        $judge = Mockery::mock(Judge::class);
        $judge->shouldReceive('evaluate')
            ->once()
            ->withArgs(function (EvalContext $ctx) {
                return $ctx->input === 'test prompt'
                    && $ctx->output === 'agent output'
                    && $ctx->expected === 'expected val';
            })
            ->andReturn(new JudgeResult(passed: true, score: 90, reasoning: 'Good'));

        builder(plainResponse('agent output'))
            ->prompt('test prompt')
            ->expected('expected val')
            ->assertPasses($judge);
    });
});
