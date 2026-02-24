<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class HaikuWriterAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a haiku writer. Write haikus in the traditional 5-7-5 syllable format. '
            .'Be creative and poetic. Only respond with the haiku itself, no explanation.';
    }

    public function messages(): iterable
    {
        return [];
    }
}
