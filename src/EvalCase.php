<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Redberry\Evals\Contracts\LoadsDatasets;

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

    /**
     * Load a single case from a JSON file.
     */
    public static function fromJson(string $path): self
    {
        return app(LoadsDatasets::class)->fromJson($path);
    }

    /**
     * Load multiple cases from an XML file.
     *
     * @return array<string, self>
     */
    public static function fromXml(string $path): array
    {
        return app(LoadsDatasets::class)->fromXml($path);
    }

    /**
     * Auto-discover *.case.json and *.case.xml files in a directory.
     *
     * @return array<string, self>
     */
    public static function fromDirectory(string $dir): array
    {
        return app(LoadsDatasets::class)->fromDirectory($dir);
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
