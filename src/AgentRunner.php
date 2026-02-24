<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class AgentRunner
{
    /**
     * Resolve agent, invoke prompt, and normalize response to EvalResult.
     *
     * @param  string|Agent|Closure(): Agent  $agent
     * @param  array<string, mixed>  $constructorArgs
     * @param  array<int, mixed>  $attachments
     */
    public function run(
        string|Agent|Closure $agent,
        array $constructorArgs,
        string $prompt,
        array $attachments = [],
        Lab|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): EvalResult {
        $resolvedAgent = $this->resolveAgent($agent, $constructorArgs);

        $providerStr = $provider instanceof Lab ? $provider->value : $provider;

        // The Promptable trait accepts timeout but the Agent interface doesn't declare it.
        // Only pass timeout when explicitly set to avoid issues with agents that don't support it.
        $response = $timeout !== null
            ? $resolvedAgent->prompt(prompt: $prompt, attachments: $attachments, provider: $providerStr, model: $model, timeout: $timeout) // @phpstan-ignore argument.unknown
            : $resolvedAgent->prompt(prompt: $prompt, attachments: $attachments, provider: $providerStr, model: $model);

        return $this->normalize($response, $resolvedAgent);
    }

    /**
     * Run the agent N times with identical input, returning SampleResults.
     *
     * @param  string|Agent|Closure(): Agent  $agent
     * @param  array<string, mixed>  $constructorArgs
     * @param  array<int, mixed>  $attachments
     */
    public function runSamples(
        string|Agent|Closure $agent,
        array $constructorArgs,
        string $prompt,
        array $attachments = [],
        Lab|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
        int $count = 1,
        ?int $minimum = null,
    ): SampleResults {
        /** @var Collection<int, EvalResult> $results */
        $results = new Collection;

        for ($i = 0; $i < $count; $i++) {
            $results->push($this->run(
                agent: $agent,
                constructorArgs: $constructorArgs,
                prompt: $prompt,
                attachments: $attachments,
                provider: $provider,
                model: $model,
                timeout: $timeout,
            ));
        }

        return new SampleResults($results, $minimum);
    }

    /**
     * Normalize an AgentResponse into an EvalResult.
     */
    private function normalize(AgentResponse $response, Agent $agent): EvalResult
    {
        $text = $response->text;

        /** @var array<string, mixed>|null $structured */
        $structured = $response instanceof StructuredAgentResponse
            ? $response->structured
            : null;

        $toolClassMap = $this->buildToolClassMap($agent);

        /** @var Collection<string, ToolResult> $resultMap */
        $resultMap = $response->toolResults->keyBy('id');

        /** @var Collection<int, ToolInvocation> $toolInvocations */
        $toolInvocations = $response->toolCalls->map(
            function (mixed $call) use ($toolClassMap, $resultMap): ToolInvocation {
                /** @var ToolCall $call */
                /** @var ToolResult|null $toolResult */
                $toolResult = $resultMap->get($call->id);

                return new ToolInvocation(
                    toolName: $call->name,
                    toolClass: $toolClassMap[$call->name] ?? null,
                    arguments: $call->arguments, // @phpstan-ignore argument.type
                    result: $toolResult?->result,
                );
            }
        );

        return new EvalResult(
            text: $text,
            structured: $structured,
            toolInvocations: $toolInvocations,
            response: $response,
        );
    }

    /**
     * Resolve agent from class string, instance, or closure.
     *
     * @param  string|Agent|Closure(): Agent  $agent
     * @param  array<string, mixed>  $constructorArgs
     */
    private function resolveAgent(string|Agent|Closure $agent, array $constructorArgs): Agent
    {
        if ($agent instanceof Agent) {
            return $agent;
        }

        if ($agent instanceof Closure) {
            $resolved = $agent();

            if (! $resolved instanceof Agent) { // @phpstan-ignore instanceof.alwaysTrue
                throw new InvalidArgumentException(
                    'Agent factory closure must return an instance of '.Agent::class
                );
            }

            return $resolved;
        }

        return Container::getInstance()->make($agent, $constructorArgs); // @phpstan-ignore return.type
    }

    /**
     * Build a map of tool name to tool FQCN from the agent's tools.
     *
     * @return array<string, string>
     */
    private function buildToolClassMap(Agent $agent): array
    {
        if (! $agent instanceof HasTools) {
            return [];
        }

        $map = [];

        /** @var Tool $tool */
        foreach ($agent->tools() as $tool) {
            $name = method_exists($tool, 'name')
                ? (string) $tool->name() // @phpstan-ignore cast.string
                : class_basename($tool);

            $map[$name] = $tool::class;
        }

        return $map;
    }
}
