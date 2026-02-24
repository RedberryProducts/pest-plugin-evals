<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Redberry\Evals\EvalServiceProvider;

class TestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EvalServiceProvider::class,
        ];
    }
}
