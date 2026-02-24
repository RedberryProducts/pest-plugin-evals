<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Judge Provider
    |--------------------------------------------------------------------------
    |
    | The AI provider used for LLM-based assertions (assertMeets).
    | Using a cheap, fast model is recommended.
    |
    */
    'judge' => [
        'provider' => env('EVALS_JUDGE_PROVIDER', 'openai'),
        'model' => env('EVALS_JUDGE_MODEL', 'gpt-4o-mini'),
        'default_threshold' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    */
    'output' => [
        'verbose' => env('EVALS_VERBOSE', false),
        'show_reasoning' => env('EVALS_SHOW_REASONING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for sampling. These apply when ->samples() is called
    | without explicit overrides.
    |
    */
    'sampling' => [
        'default_samples' => env('EVALS_DEFAULT_SAMPLES', 1),
        'default_minimum' => null, // null = all must pass
    ],
];
