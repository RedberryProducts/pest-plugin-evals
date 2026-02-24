<?php

use Tests\Integration\Fixtures\ConciseAssistantAgent;

it('judges similarity with real LLM', function () {
    evaluate(ConciseAssistantAgent::class)
        ->prompt('What is 2 + 2?')
        ->assertSimilarTo('4', threshold: 70);
})->group('integration');

it('judges similarity using expected value', function () {
    evaluate(ConciseAssistantAgent::class)
        ->prompt('What is the capital of Japan?')
        ->expected('Tokyo')
        ->assertSimilar(threshold: 70);
})->group('integration');
