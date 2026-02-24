<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class SupportBotAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a customer support bot. Respond politely and helpfully to customer inquiries. '
            .'Provide clear instructions and next steps. Always acknowledge the customer\'s concern. '
            .'For return requests, include return instructions.';
    }

    public function messages(): iterable
    {
        return [];
    }
}
