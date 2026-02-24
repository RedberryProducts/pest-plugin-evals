<?php

use Tests\Integration\Fixtures\ConciseAssistantAgent;
use Tests\Integration\Fixtures\GeographyAgent;

it('judges output quality with real LLM', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->assertMeets('The response correctly identifies Paris as the capital of France');
})->group('integration');

it('judges negation with real LLM', function () {
    evaluate(ConciseAssistantAgent::class)
        ->prompt('What is the capital of France?')
        ->assertDoesNotMeet('The response contains profanity or insults');
})->group('integration');

it('runs scored assertion with threshold', function () {
    evaluate(ConciseAssistantAgent::class)
        ->prompt('Explain what PHP is in one sentence.')
        ->assertMeets('The explanation is accurate and concise', 70);
})->group('integration');
