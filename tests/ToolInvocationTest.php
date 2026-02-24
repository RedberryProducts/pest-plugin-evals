<?php

use Redberry\Evals\ToolInvocation;

it('stores tool invocation data', function () {
    $invocation = new ToolInvocation(
        toolName: 'search',
        toolClass: 'App\Tools\SearchTool',
        arguments: ['query' => 'test', 'limit' => 10],
        result: 'search results',
    );

    expect($invocation->toolName)->toBe('search')
        ->and($invocation->toolClass)->toBe('App\Tools\SearchTool')
        ->and($invocation->arguments)->toBe(['query' => 'test', 'limit' => 10])
        ->and($invocation->result)->toBe('search results');
});

it('allows null tool class', function () {
    $invocation = new ToolInvocation(
        toolName: 'unknown_tool',
        toolClass: null,
        arguments: [],
        result: null,
    );

    expect($invocation->toolClass)->toBeNull();
});

it('provides magic access to arguments', function () {
    $invocation = new ToolInvocation(
        toolName: 'search',
        toolClass: null,
        arguments: ['query' => 'hello', 'limit' => 5],
        result: null,
    );

    expect($invocation->query)->toBe('hello') // @phpstan-ignore property.notFound
        ->and($invocation->limit)->toBe(5); // @phpstan-ignore property.notFound
});

it('returns null for missing arguments via magic access', function () {
    $invocation = new ToolInvocation(
        toolName: 'search',
        toolClass: null,
        arguments: ['query' => 'hello'],
        result: null,
    );

    expect($invocation->nonexistent)->toBeNull(); // @phpstan-ignore property.notFound
});

it('supports isset for arguments', function () {
    $invocation = new ToolInvocation(
        toolName: 'search',
        toolClass: null,
        arguments: ['query' => 'hello'],
        result: null,
    );

    expect(isset($invocation->query))->toBeTrue() // @phpstan-ignore property.notFound
        ->and(isset($invocation->missing))->toBeFalse(); // @phpstan-ignore property.notFound
});
