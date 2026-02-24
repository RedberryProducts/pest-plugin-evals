<?php

use Redberry\Evals\Contracts\LoadsDatasets;
use Redberry\Evals\DatasetLoader;
use Redberry\Evals\EvalServiceProvider;

it('merges evals config', function () {
    expect(config('evals.judge.provider'))->toBe('openai')
        ->and(config('evals.judge.model'))->toBe('gpt-4o-mini')
        ->and(config('evals.judge.default_threshold'))->toBe(80);
});

it('merges output config', function () {
    expect(config('evals.output.verbose'))->toBeFalse()
        ->and(config('evals.output.show_reasoning'))->toBeTrue();
});

it('merges sampling config', function () {
    expect(config('evals.sampling.default_samples'))->toBe(1)
        ->and(config('evals.sampling.default_minimum'))->toBeNull();
});

it('binds DatasetLoader to LoadsDatasets', function () {
    $loader = app(LoadsDatasets::class);

    expect($loader)->toBeInstanceOf(DatasetLoader::class);
});

it('resolves a fresh loader instance each time', function () {
    $a = app(LoadsDatasets::class);
    $b = app(LoadsDatasets::class);

    expect($a)->not->toBe($b);
});

it('registers publishable config', function () {
    $publishes = EvalServiceProvider::$publishGroups['evals-config'] ?? [];

    expect($publishes)->not->toBeEmpty();
});
