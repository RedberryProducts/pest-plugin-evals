<?php

use Redberry\Evals\JudgeResult;

it('stores passed, score, and reasoning', function () {
    $result = new JudgeResult(passed: true, score: 85, reasoning: 'Good match');

    expect($result->passed)->toBeTrue()
        ->and($result->score)->toBe(85)
        ->and($result->reasoning)->toBe('Good match');
});

it('allows null score for binary judges', function () {
    $result = new JudgeResult(passed: false, score: null, reasoning: 'Did not meet criteria');

    expect($result->passed)->toBeFalse()
        ->and($result->score)->toBeNull()
        ->and($result->reasoning)->toBe('Did not meet criteria');
});

it('is readonly', function () {
    $result = new JudgeResult(passed: true, score: 90, reasoning: 'Great');

    expect(fn () => $result->passed = false)->toThrow(Error::class); // @phpstan-ignore assign.propertyReadOnly
});
