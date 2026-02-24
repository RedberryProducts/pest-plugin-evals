<?php

declare(strict_types=1);

namespace Redberry\Evals;

final class EvalCase
{
    public string $prompt = '';

    public mixed $expected = null;

    /** @var array<int, mixed> */
    public array $attachments = [];

    public static function make(): self
    {
        return new self;
    }

    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function expected(mixed $expected): self
    {
        $this->expected = $expected;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $attachments
     */
    public function attachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }
}
