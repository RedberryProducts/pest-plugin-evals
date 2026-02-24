<?php

use Redberry\Evals\Contracts\LoadsDatasets;
use Redberry\Evals\DatasetLoader;
use Redberry\Evals\EvalCase;

it('creates an empty instance via make()', function () {
    $case = EvalCase::make();

    expect($case)->toBeInstanceOf(EvalCase::class)
        ->and($case->prompt)->toBe('')
        ->and($case->expected)->toBeNull()
        ->and($case->attachments)->toBe([]);
});

it('sets prompt fluently', function () {
    $case = EvalCase::make()->prompt('What is AI?');

    expect($case->prompt)->toBe('What is AI?');
});

it('sets expected fluently', function () {
    $case = EvalCase::make()->expected('Artificial Intelligence');

    expect($case->expected)->toBe('Artificial Intelligence');
});

it('sets attachments fluently', function () {
    $attachments = ['file1', 'file2'];
    $case = EvalCase::make()->attachments($attachments);

    expect($case->attachments)->toBe($attachments);
});

it('supports fluent chaining', function () {
    $case = EvalCase::make()
        ->prompt('Question?')
        ->expected('Answer')
        ->attachments(['file']);

    expect($case->prompt)->toBe('Question?')
        ->and($case->expected)->toBe('Answer')
        ->and($case->attachments)->toBe(['file']);
});

it('delegates fromJson to DatasetLoader via container', function () {
    $fixturePath = __DIR__.'/Fixtures/datasets/with-expected.case.json';

    $case = EvalCase::fromJson($fixturePath);

    expect($case)->toBeInstanceOf(EvalCase::class)
        ->and($case->prompt)->toBe('What is the capital of France?')
        ->and($case->expected)->toBe('Paris');
});

it('delegates fromXml to DatasetLoader via container', function () {
    $fixturePath = __DIR__.'/Fixtures/datasets/cases.case.xml';

    $cases = EvalCase::fromXml($fixturePath);

    expect($cases)->toBeArray()
        ->and($cases)->toHaveCount(2)
        ->and($cases)->toHaveKeys(['capital-france', 'capital-japan']);
});

it('delegates fromDirectory to DatasetLoader via container', function () {
    $tmpDir = sys_get_temp_dir().'/eval_test_evalcase_'.uniqid();
    mkdir($tmpDir);
    copy(__DIR__.'/Fixtures/datasets/simple.case.json', $tmpDir.'/test.case.json');

    $cases = EvalCase::fromDirectory($tmpDir);

    expect($cases)->toBeArray()
        ->and($cases)->toHaveKey('test');

    unlink($tmpDir.'/test.case.json');
    rmdir($tmpDir);
});

it('resolves LoadsDatasets from container', function () {
    $loader = app(LoadsDatasets::class);

    expect($loader)->toBeInstanceOf(DatasetLoader::class);
});
