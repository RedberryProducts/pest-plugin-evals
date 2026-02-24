<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\Agent;
use Redberry\Evals\EvalBuilder;

if (! function_exists('evaluate')) {
    /**
     * Create an evaluation builder for the given agent.
     *
     * @param  string|Agent|Closure(): Agent  $agent
     * @param  array<string, mixed>  $constructorArgs
     */
    function evaluate(
        string|Agent|Closure $agent,
        array $constructorArgs = [],
    ): EvalBuilder {
        return new EvalBuilder($agent, $constructorArgs);
    }
}
