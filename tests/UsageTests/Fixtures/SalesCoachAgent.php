<?php

declare(strict_types=1);

namespace Tests\UsageTests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class SalesCoachAgent implements Agent, Conversational
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sales coach. Provide constructive feedback on sales interactions. '
            .'Offer negotiation tactics and actionable suggestions. Be encouraging, not critical. '
            .'Always maintain a professional tone.';
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
