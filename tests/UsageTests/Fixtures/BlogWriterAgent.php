<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class BlogWriterAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a blog writer. Write engaging, informative content. '
            .'Use clear structure with paragraphs. Be factual and accessible. '
            .'Explain technical concepts in a way that beginners can understand.';
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
