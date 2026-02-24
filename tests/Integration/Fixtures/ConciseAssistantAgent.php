<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class ConciseAssistantAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a helpful assistant. Keep answers short and to the point. Respond with only the essential information.';
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
