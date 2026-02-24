<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class GeographyAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a geography expert. Be concise and factual. Always mention the country name in your answer.';
    }

    public function model(): string
    {
        return 'gpt-4o-mini';
    }

    public function messages(): iterable
    {
        return [];
    }
}
