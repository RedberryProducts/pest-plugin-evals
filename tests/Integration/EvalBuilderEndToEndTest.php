<?php

use Redberry\Evals\EvalResult;
use Redberry\Evals\JudgeResult;
use Tests\Integration\Fixtures\ConciseAssistantAgent;
use Tests\Integration\Fixtures\GeographyAgent;

it('runs a complete evaluation chain', function () {
    evaluate(GeographyAgent::class)
        ->prompt('Name the capital of Japan')
        ->assertContains('Tokyo')
        ->assertMeets('The response is factually correct');
})->group('integration');

it('returns run result for manual inspection', function () {
    $result = evaluate(ConciseAssistantAgent::class)
        ->prompt('What is 2 + 2?')
        ->run();

    expect($result)->toBeInstanceOf(EvalResult::class)
        ->and($result->text)->not->toBeEmpty();
})->group('integration');

it('returns judge result for manual inspection', function () {
    $result = evaluate(ConciseAssistantAgent::class)
        ->prompt('What is the meaning of life?')
        ->judge('The response is thoughtful and nuanced');

    expect($result)->toBeInstanceOf(JudgeResult::class)
        ->and($result->reasoning)->not->toBeEmpty();
})->group('integration');

it('evaluates with sampling', function () {
    evaluate(GeographyAgent::class)
        ->prompt('What is the capital of France?')
        ->samples(3, minimum: 2)
        ->assertContains('Paris')
        ->assertMeets('The response is factually correct');
})->group('integration');

it('uses BDD-style syntax', function () {
    evaluate(GeographyAgent::class)
        ->whenPrompted('What is the capital of France?')
        ->toMeet('The response correctly identifies Paris as the capital');
})->group('integration');
