<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Illuminate\Support\ServiceProvider;
use Redberry\Evals\Contracts\LoadsDatasets;

final class EvalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/evals.php', 'evals');

        $this->app->bind(LoadsDatasets::class, DatasetLoader::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/evals.php' => config_path('evals.php'),
        ], 'evals-config');
    }
}
