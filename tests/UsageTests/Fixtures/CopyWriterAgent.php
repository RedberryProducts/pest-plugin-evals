<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class CopyWriterAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a copywriter specializing in short-form content like tweets and social media posts. '
            .'Keep responses concise, under 280 characters when possible. '
            .'Use an enthusiastic and engaging tone. Always include relevant hashtags.';
    }

    public function messages(): iterable
    {
        return [];
    }
}
