<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use LogicException;
use PHPUnit\Framework\Assert;
use Redberry\Evals\Concerns\HasDeterministicAssertions;
use Redberry\Evals\Concerns\HasJudgeAssertions;
use Redberry\Evals\Concerns\HasStructuredAssertions;
use Redberry\Evals\Concerns\HasToolAssertions;
use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\Contracts\Rubric;
use Redberry\Evals\Judges\LlmJudge;

final class EvalBuilder
{
    use HasDeterministicAssertions;
    use HasJudgeAssertions;
    use HasStructuredAssertions;
    use HasToolAssertions;

    private ?string $prompt = null;

    private mixed $expected = null;

    /** @var array<int, mixed> */
    private array $attachments = [];

    private Lab|string|null $provider = null;

    private ?string $model = null;

    private ?int $timeout = null;

    /** @var Lab|string|null Judge provider override (protected so traits can read it). */
    protected Lab|string|null $judgeProvider = null;

    /** @var string|null Judge model override (protected so traits can read it). */
    protected ?string $judgeModel = null;

    /** @var string|null Custom judge instructions appended to defaults (protected so traits can read it). */
    protected ?string $judgeInstructions = null;

    private ?int $sampleCount = null;

    private ?int $sampleMinimum = null;

    private EvalResult|SampleResults|null $result = null;

    private bool $hasRun = false;

    /**
     * @param  string|\Laravel\Ai\Contracts\Agent|Closure(): \Laravel\Ai\Contracts\Agent  $agent
     * @param  array<string, mixed>  $constructorArgs
     */
    public function __construct(
        private readonly string|\Laravel\Ai\Contracts\Agent|Closure $agent,
        private readonly array $constructorArgs = [],
    ) {}

    // -------------------------------------------------------------------------
    // Configuration Methods
    // -------------------------------------------------------------------------

    /**
     * Set the prompt and optionally override provider/model/timeout/attachments.
     *
     * @param  array<int, mixed>|null  $attachments
     */
    public function prompt(
        string $prompt,
        Lab|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
        ?array $attachments = null,
    ): static {
        $this->prompt = $prompt;

        if ($provider !== null) {
            $this->provider = $provider;
        }

        if ($model !== null) {
            $this->model = $model;
        }

        if ($timeout !== null) {
            $this->timeout = $timeout;
        }

        if ($attachments !== null) {
            $this->attachments = $attachments;
        }

        return $this;
    }

    /**
     * Load prompt, expected, and attachments from an EvalCase.
     */
    public function withCase(EvalCase $case): static
    {
        $this->prompt = $case->prompt;
        $this->expected = $case->expected;

        $this->attachments = $case->attachments;

        return $this;
    }

    /**
     * Set the expected output for comparison.
     */
    public function expected(mixed $expected): static
    {
        $this->expected = $expected;

        return $this;
    }

    /**
     * Set file attachments.
     *
     * @param  array<int, mixed>  $attachments
     */
    public function attachments(array $attachments): static
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * Override the agent provider.
     */
    public function provider(Lab|string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Override the agent model.
     */
    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Override the agent timeout.
     */
    public function timeout(int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Run the agent multiple times and evaluate each sample.
     */
    public function samples(int $count, ?int $minimum = null): static
    {
        $this->sampleCount = $count;
        $this->sampleMinimum = $minimum;

        return $this;
    }

    /**
     * Alias for samples().
     */
    public function repeat(int $count, ?int $minimum = null): static
    {
        return $this->samples($count, $minimum);
    }

    /**
     * Override the judge provider and model for LLM-based assertions.
     */
    public function judgeWith(Lab|string $provider, ?string $model = null): static
    {
        $this->judgeProvider = $provider;
        $this->judgeModel = $model;

        return $this;
    }

    /**
     * Set custom instructions appended to the default judge instructions.
     */
    public function judgeInstructions(string $instructions): static
    {
        $this->judgeInstructions = $instructions;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Execute the agent and return the result.
     */
    public function run(): EvalResult|SampleResults
    {
        $this->ensureRun();

        /** @var EvalResult|SampleResults */
        return $this->result;
    }

    /**
     * Run a judge and return the raw result.
     */
    public function judge(string $criterion, ?Rubric $rubric = null): JudgeResult|SampleResults
    {
        $this->ensureRun();

        $judge = new LlmJudge(
            criterion: $rubric ?? $criterion,
            provider: $this->judgeProvider,
            model: $this->judgeModel,
            instructions: $this->judgeInstructions,
        );

        if (! $this->isSampled()) {
            $context = $this->buildContext($this->singleResult());

            return $judge->evaluate($context);
        }

        $samples = $this->sampleResults();
        /** @var Collection<int, JudgeResult> $judgeResults */
        $judgeResults = new Collection;

        foreach ($samples as $evalResult) {
            $context = $this->buildContext($evalResult);
            $judgeResults->push($judge->evaluate($context));
        }

        return $samples->withJudgeResults($judgeResults);
    }

    // -------------------------------------------------------------------------
    // BDD-Style Aliases
    // -------------------------------------------------------------------------

    /**
     * Alias for prompt().
     */
    public function whenPrompted(string $prompt): static
    {
        return $this->prompt($prompt);
    }

    /**
     * Alias for assertMeets().
     */
    public function toMeet(string|Rubric $criterion, ?int $threshold = null): static
    {
        return $this->assertMeets($criterion, $threshold);
    }

    /**
     * Alias for assertSimilarTo().
     */
    public function toBeSimilarTo(string $expected, int $threshold = 80): static
    {
        return $this->assertSimilarTo($expected, $threshold);
    }

    /**
     * Alias for assertSimilar().
     */
    public function toBeSimilar(?int $threshold = null): static
    {
        return $this->assertSimilar($threshold);
    }

    /**
     * Auto-detecting exact match: array → assertMatchesArray(), otherwise → assertEquals().
     */
    public function toBe(mixed $value): static
    {
        if (is_array($value)) {
            return $this->assertMatchesArray($value); // @phpstan-ignore argument.type
        }

        return $this->assertEquals($value);
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Ensure the agent has been executed. Lazy-runs on first call.
     */
    private function ensureRun(): void
    {
        if ($this->hasRun) {
            return;
        }

        if ($this->prompt === null || $this->prompt === '') {
            throw new LogicException(
                'No prompt set. Call ->prompt() or ->withCase() before running assertions.'
            );
        }

        $runner = new AgentRunner;

        if ($this->isSampled()) {
            $this->result = $runner->runSamples(
                agent: $this->agent,
                constructorArgs: $this->constructorArgs,
                prompt: $this->prompt,
                attachments: $this->attachments,
                provider: $this->provider,
                model: $this->model,
                timeout: $this->timeout,
                count: $this->sampleCount, // @phpstan-ignore argument.type
                minimum: $this->sampleMinimum,
            );
        } else {
            $this->result = $runner->run(
                agent: $this->agent,
                constructorArgs: $this->constructorArgs,
                prompt: $this->prompt,
                attachments: $this->attachments,
                provider: $this->provider,
                model: $this->model,
                timeout: $this->timeout,
            );
        }

        $this->hasRun = true;
    }

    private function isSampled(): bool
    {
        return $this->sampleCount !== null && $this->sampleCount > 1;
    }

    private function singleResult(): EvalResult
    {
        assert($this->result instanceof EvalResult);

        return $this->result;
    }

    private function sampleResults(): SampleResults
    {
        assert($this->result instanceof SampleResults);

        return $this->result;
    }

    /**
     * Build an EvalContext for a given result.
     */
    private function buildContext(
        EvalResult $result,
        mixed $expectedOverride = Missing::Value,
    ): EvalContext {
        return new EvalContext(
            input: $this->prompt ?? '',
            output: $result->text,
            expected: $expectedOverride !== Missing::Value
                ? $expectedOverride
                : $this->expected,
            result: $result,
        );
    }

    /**
     * Apply a boolean check to each result (single or sampled).
     *
     * @param  callable(EvalResult): bool  $check
     */
    private function assertEachResult(callable $check, string $description): void
    {
        $this->ensureRun();

        if (! $this->isSampled()) {
            $result = $this->singleResult();
            $passed = $check($result);

            EvalRecorder::record(new EvalRecord(
                assertionName: $description,
                input: $this->prompt ?? '',
                output: $result->text,
                passed: $passed,
                toolInvocations: $result->toolInvocations,
            ));

            Assert::assertTrue($passed, $description);

            return;
        }

        $samples = $this->sampleResults();
        $passCount = 0;
        $total = $samples->count();
        /** @var list<int> $failedIndices */
        $failedIndices = [];

        foreach ($samples as $i => $evalResult) {
            $passed = $check($evalResult);

            if ($passed) {
                $passCount++;
            } else {
                $failedIndices[] = $i;
            }

            EvalRecorder::record(new EvalRecord(
                assertionName: $description,
                input: $this->prompt ?? '',
                output: $evalResult->text,
                passed: $passed,
                toolInvocations: $evalResult->toolInvocations,
                sampleIndex: $i,
                sampleTotal: $total,
                sampleMinimum: $samples->minimum() ?? $total,
            ));
        }

        $required = $samples->minimum() ?? $total;
        Assert::assertTrue(
            $passCount >= $required,
            sprintf(
                '%s [%d/%d samples passed, %d required. Failed: #%s]',
                $description,
                $passCount,
                $total,
                $required,
                implode(', #', $failedIndices),
            ),
        );
    }

    /**
     * Apply a Judge to each result (single or sampled).
     */
    private function judgeEachResult(
        Judge $judge,
        string $description,
        mixed $expectedOverride = Missing::Value,
        bool $negate = false,
    ): void {
        $this->ensureRun();

        if (! $this->isSampled()) {
            $singleResult = $this->singleResult();
            $context = $this->buildContext($singleResult, $expectedOverride);
            $judgeResult = $judge->evaluate($context);
            $passed = $negate ? ! $judgeResult->passed : $judgeResult->passed;

            EvalRecorder::record(new EvalRecord(
                assertionName: $description,
                input: $this->prompt ?? '',
                output: $singleResult->text,
                passed: $passed,
                toolInvocations: $singleResult->toolInvocations,
                score: $judgeResult->score,
                reasoning: $judgeResult->reasoning,
            ));

            Assert::assertTrue(
                $passed,
                sprintf('%s — Judge: %s', $description, $judgeResult->reasoning),
            );

            return;
        }

        $samples = $this->sampleResults();
        $passCount = 0;
        $total = $samples->count();
        /** @var list<string> $failureReasons */
        $failureReasons = [];

        foreach ($samples as $i => $evalResult) {
            $context = $this->buildContext($evalResult, $expectedOverride);
            $judgeResult = $judge->evaluate($context);
            $passed = $negate ? ! $judgeResult->passed : $judgeResult->passed;

            EvalRecorder::record(new EvalRecord(
                assertionName: $description,
                input: $this->prompt ?? '',
                output: $evalResult->text,
                passed: $passed,
                toolInvocations: $evalResult->toolInvocations,
                score: $judgeResult->score,
                reasoning: $judgeResult->reasoning,
                sampleIndex: $i,
                sampleTotal: $total,
                sampleMinimum: $samples->minimum() ?? $total,
            ));

            if ($passed) {
                $passCount++;
            } else {
                $failureReasons[] = sprintf('Sample #%d: %s', $i, $judgeResult->reasoning);
            }
        }

        $required = $samples->minimum() ?? $total;
        Assert::assertTrue(
            $passCount >= $required,
            sprintf(
                "%s [%d/%d samples passed, %d required]\n%s",
                $description,
                $passCount,
                $total,
                $required,
                implode("\n", $failureReasons),
            ),
        );
    }
}
