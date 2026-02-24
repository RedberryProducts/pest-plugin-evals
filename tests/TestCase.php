<?php

namespace Tests;

use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Prism\Prism\PrismServiceProvider;
use Redberry\Evals\EvalServiceProvider;

class TestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PrismServiceProvider::class,
            AiServiceProvider::class,
            EvalServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * Load the OpenAI API key before service providers boot,
     * so that config('ai.providers.openai.key') resolves correctly.
     */
    protected function defineEnvironment($app): void
    {
        $keyFile = dirname(__DIR__).'/openai-api-key.php';

        if (file_exists($keyFile)) {
            $key = require $keyFile;
            $app['config']->set('ai.providers.openai.key', $key);
        }
    }
}
