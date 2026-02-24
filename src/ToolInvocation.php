<?php

declare(strict_types=1);

namespace Redberry\Evals;

final class ToolInvocation
{
    public function __construct(
        public readonly string $toolName,
        public readonly ?string $toolClass,
        public readonly array $arguments,
        public readonly mixed $result,
    ) {}

    /**
     * Magic access to tool arguments by name.
     *
     *     $tool->query  ===  $tool->arguments['query']
     */
    public function __get(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->arguments[$name]);
    }
}
