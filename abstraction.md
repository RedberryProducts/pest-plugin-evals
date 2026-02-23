# Abstraction Layer — Design Plan

The abstraction layer defines the **shapes**, **contracts**, and **extension points** that the core and outer API build upon. It knows nothing about PEST, nothing about fluent builders, nothing about how agents are resolved or invoked. It answers one question: *what are the primitives of an evaluation system?*

---

## Guiding Principles

1. **Contracts are tiny.** A Judge has one method. A Rubric has one required method. If it can't be explained in a sentence, it's too big.
2. **Value objects are final.** Data flows through the system in immutable, well-typed containers. No inheritance games.
3. **The abstraction imports nothing from PEST.** It depends on `laravel/ai` for response types and `illuminate/support` for collections — that's it.
4. **Extension happens through interfaces, not base classes.** The only abstract class is `Rubric`, and that's because it carries a sensible default.

---

## File Map

```
src/
├── Contracts/
│   ├── Judge.php
│   ├── LoadsDatasets.php
│   └── Rubric.php
├── EvalCase.php
├── EvalContext.php
├── EvalResult.php
├── JudgeResult.php
├── ToolInvocation.php
└── SampleResults.php
```

---

## 1. `Contracts\Judge`

The single extension point for custom evaluation logic. A Judge receives the full evaluation context and returns a verdict. That's it.

```php
namespace Redberry\Evals\Contracts;

use Redberry\Evals\EvalContext;
use Redberry\Evals\JudgeResult;

interface Judge
{
    /**
     * Evaluate the agent's output and return a verdict.
     */
    public function evaluate(EvalContext $context): JudgeResult;
}
```

**Why an interface and not an abstract class?** Because judges have wildly different internals — one might call an LLM, another might compute cosine similarity, a third might count words. There's no shared behavior to inherit.

**Usage in core:** The core's built-in LLM judge implements this interface. `assertMeets('string')` creates an anonymous LLM judge internally. `assertPasses(new CustomJudge())` accepts any `Judge` implementation directly.

---

## 2. `Contracts\Rubric`

A Rubric is a **reusable description of evaluation criteria** that gets fed to the built-in LLM judge. It does not evaluate anything itself — it just describes what "good" looks like.

```php
namespace Redberry\Evals\Contracts;

abstract class Rubric
{
    /**
     * Describe the evaluation criteria in natural language.
     * This prompt is sent to the LLM judge.
     */
    abstract public function description(): string;

    /**
     * Whether the LLM should return a 0-100 score
     * instead of a binary pass/fail.
     */
    public function scored(): bool
    {
        return false;
    }
}
```

**Why an abstract class?** Because `scored()` has a sensible default that 90% of rubrics will keep. Forcing every rubric to implement two methods when they only care about one is noise.

**Design decision — Rubric is not a Judge.** Rubrics don't evaluate. They describe. The core's LLM judge consumes rubrics to build its evaluation prompt. This keeps the Rubric surface area to a single `description()` method, which is all a developer should think about:

```php
class ProfessionalTone extends Rubric
{
    public function description(): string
    {
        return 'The response maintains a professional, business-appropriate tone...';
    }
}
```

**Design decision — no `threshold()` on Rubric.** Thresholds belong at the assertion call site (`assertMeets(new ProfessionalTone, 80)`), not baked into the rubric definition. A rubric is reusable across contexts with different standards.

**Design decision — call-site threshold always wins.** When a threshold is provided at the assertion call site, scored mode is activated regardless of `Rubric::scored()`. This means the same rubric works in binary mode (`assertMeets(new ProfessionalTone)`) and scored mode (`assertMeets(new ProfessionalTone, 80)`) without modification. The core's LLM judge detects the threshold and requests a 0–100 score automatically. For plain string criteria, `assertMeets('Be professional', 80)` works the same way — the core creates an anonymous rubric internally with `scored()` returning `true`.

---

## 3. `Contracts\LoadsDatasets`

The contract for dataset loading — how evaluation cases are loaded from external files. The abstraction defines the shape; the core provides the implementation.

```php
namespace Redberry\Evals\Contracts;

use Redberry\Evals\EvalCase;

interface LoadsDatasets
{
    /**
     * Load a single case from a JSON file.
     */
    public function fromJson(string $path): EvalCase;

    /**
     * Load multiple cases from an XML file.
     *
     * @return array<string, EvalCase>
     */
    public function fromXml(string $path): array;

    /**
     * Auto-discover *.case.json and *.case.xml files in a directory.
     *
     * @return array<string, EvalCase>
     */
    public function fromDirectory(string $dir): array;
}
```

**Why an interface?** Because the abstraction doesn't care how files are parsed — only that a loader returns `EvalCase` instances. The core provides a `DatasetLoader` class that implements this contract. Third-party packages can implement their own loaders (YAML, CSV, database-backed) through the same interface.

**Why instance methods instead of static?** So the contract is enforceable through PHP's type system. A consumer can type-hint `LoadsDatasets` and swap implementations. The core's `DatasetLoader` may additionally offer static convenience methods, but the contract is instance-based.

---

## 4. `EvalCase`

The input to an evaluation — what you're asking the agent and what you expect back. This is the only abstraction-layer class that end users create directly.

```php
namespace Redberry\Evals;

final class EvalCase
{
    public string $prompt = '';
    public mixed $expected = null;
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

    public function attachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }
}
```

**Why public properties instead of getters?** Because this is a data carrier, not a service. The design doc accesses `$case->prompt`, `$case->expected` directly in test assertions. Fighting that with getters adds ceremony for no benefit.

**Why `final`?** `EvalCase` is a pure data carrier. Dataset loading (`fromJson`, `fromXml`, `fromDirectory`) belongs on a separate `DatasetLoader` in the core — it implements the `LoadsDatasets` contract defined in this layer. Making `EvalCase` final ensures a single, predictable shape across the system.

**What about `provider`, `model`, `timeout` on EvalCase?** They don't belong here. `EvalCase` represents _what_ to evaluate, not _how_ to run the agent. Execution configuration belongs on the evaluation builder (outer API). A case file shared across teams shouldn't dictate which model runs it.

---

## 5. `EvalResult`

The normalized output from running an agent. This is what flows downstream to judges and assertions.

```php
namespace Redberry\Evals;

use ArrayAccess;
use Illuminate\Support\Collection;
use Laravel\Ai\AgentResponse;
use Stringable;

final class EvalResult implements ArrayAccess, Stringable
{
    /**
     * @param  string  $text  The agent's text output.
     * @param  array|null  $structured  Parsed structured output (if agent implements HasStructuredOutput).
     * @param  Collection<int, ToolInvocation>  $toolInvocations  Normalized tool calls with results.
     * @param  AgentResponse  $response  The raw response for escape-hatch access.
     */
    public function __construct(
        public readonly string $text,
        public readonly ?array $structured,
        public readonly Collection $toolInvocations,
        public readonly AgentResponse $response,
    ) {}

    /**
     * Whether the agent produced structured output.
     */
    public function isStructured(): bool
    {
        return $this->structured !== null;
    }

    /**
     * Get the output as an array (structured) or null (text-only).
     */
    public function toArray(): ?array
    {
        return $this->structured;
    }

    // --- ArrayAccess (delegates to structured output) ---

    public function offsetExists(mixed $offset): bool { /* delegates to $this->structured */ }
    public function offsetGet(mixed $offset): mixed { /* delegates to $this->structured */ }
    public function offsetSet(mixed $offset, mixed $value): void { /* immutable, throws */ }
    public function offsetUnset(mixed $offset): void { /* immutable, throws */ }

    // --- Stringable ---

    public function __toString(): string
    {
        return $this->text;
    }
}
```

**Why `final`?** EvalResult is a value object. Extending it breaks the contract that every component in the system can rely on the same shape.

**Why `ArrayAccess`?** So that `expect($result)->toMatchArray(...)` and `$result['name']` work naturally for structured output agents. The outer API's `->run()` returns an `EvalResult`, and PEST expectations need to interact with it as an array.

**Why `Stringable`?** So that `(string) $result` gives you the text, and assertions like `assertContains` can work on it naturally.

**Why `ToolInvocation` collection instead of raw `ToolCall`/`ToolResult`?** Raw Laravel AI types split tool data across two collections with ID-based pairing. `ToolInvocation` pre-joins them into a single object per call — name, arguments, result — which is what assertions actually need.

**Why keep the raw `AgentResponse`?** Escape hatch. Power users may need token usage, meta, steps, conversation IDs. We normalize the 95% case; the `response` property covers the rest.

---

## 6. `EvalContext`

Everything a Judge needs to render its verdict. Passed to `Judge::evaluate()`.

```php
namespace Redberry\Evals;

final class EvalContext
{
    public function __construct(
        public readonly string $input,
        public readonly string $output,
        public readonly mixed $expected,
        public readonly EvalResult $result,
    ) {}
}
```

| Property   | Description |
|------------|-------------|
| `input`    | The prompt that was sent to the agent. |
| `output`   | The agent's text output (`$result->text`). Convenience alias — judges almost always need this as a string. |
| `expected` | The expected value from `EvalCase`, or `null` if none was set. Type is `mixed` because expectations can be strings, arrays, or any structure. |
| `result`   | The full `EvalResult` for judges that need structured output, tool invocations, or the raw response. |

**Why duplicate `output` when `result->text` exists?** Because 90% of judges only look at the text. Forcing `$context->result->text` everywhere is unnecessary friction. `$context->output` is the obvious reach.

**What about agent class name / metadata?** Not included. A judge evaluates _output quality_, not _who produced it_. If a custom judge needs meta, it can accept it through its constructor.

---

## 7. `JudgeResult`

The verdict from a judge evaluation.

```php
namespace Redberry\Evals;

final class JudgeResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly ?int $score,
        public readonly string $reasoning,
    ) {}
}
```

| Property    | Description |
|-------------|-------------|
| `passed`    | Binary verdict. For scored judges, this is derived from the threshold comparison. |
| `score`     | Integer 0–100. `null` for binary-only judges that don't produce scores. |
| `reasoning` | The judge's explanation. For LLM judges, this is the model's reasoning. For deterministic judges, a human-readable description of what failed. |

**Why `?int` for score instead of always present?** Because binary judges (pass/fail) shouldn't be forced to invent a score. `null` means "this judge doesn't score — it just decides."

**Why `int` instead of `float`?** Scores are for human consumption and threshold comparison. Integer precision is sufficient and avoids floating-point comparison pitfalls.

---

## 8. `ToolInvocation`

A single tool call, pre-joined with its result. Wraps the data the LLM chose to send to a tool, with magic property access for ergonomic assertions.

```php
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
```

| Property    | Description |
|-------------|-------------|
| `toolName`  | The tool's registered name (e.g., `'web_search'`). Always available. |
| `toolClass` | FQCN of the tool class (e.g., `WebSearch::class`). `null` when only the string name is known. |
| `arguments` | The arguments the LLM passed to the tool, as an associative array. |
| `result`    | The return value from the tool's `handle()` method. |

**Why `__get` magic?** Because `$tool->query` reads better than `$tool->arguments['query']` in assertion closures. This is a read-only data object used in test assertions — ergonomics matter more than purity here.

**How is `toolClass` resolved?** The core's agent runner maps tool names to their FQCN by inspecting the agent's `tools()` array. This is a core concern, not abstraction.

---

## 9. `SampleResults`

A pure data container for repeated sampling. Holds the raw `EvalResult` objects from running an agent N times, plus the `minimum` threshold that was requested.

```php
namespace Redberry\Evals;

use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

final class SampleResults implements Countable, IteratorAggregate
{
    /**
     * @param  Collection<int, EvalResult>  $results  The raw results from each sample run.
     * @param  int|null  $minimum  Minimum samples that must pass. null = all.
     */
    public function __construct(
        protected Collection $results,
        protected ?int $minimum = null,
    ) {}

    public function count(): int { return $this->results->count(); }

    public function outputs(): Collection { return $this->results; }

    public function first(): EvalResult { return $this->results->first(); }

    public function last(): EvalResult { return $this->results->last(); }

    public function getIterator(): Traversable { return $this->results->getIterator(); }

    public function minimum(): ?int { return $this->minimum; }

    public function each(callable $callback): static
    {
        $this->results->each($callback);

        return $this;
    }
}
```

**Why no judge aggregation here?** Sampling and evaluation are two distinct phases with different shapes. A single sample can be evaluated by multiple assertions — some deterministic (`assertContains`), some LLM-based (`assertMeets`). Tracking the 2D grid of samples × assertions is an orchestration concern that belongs in the core, not in a data container. The abstraction provides the raw material (`Collection<EvalResult>` + `minimum`); the core builds per-sample verdict tracking, pass rates, and aggregate scoring on top.

**Why keep `minimum` here?** Because it's declared at the call site (`->samples(5, minimum: 4)`) and flows through the entire pipeline. The core needs it to determine pass/fail, so it belongs on the container that carries the samples.

---

## Dependency Graph

```
EvalCase ──────────────────┐
                           ▼
                     [ Core: Agent Runner ]
                           │
                           ▼
                      EvalResult
                      │        │
            ┌─────────┘        └──────────┐
            ▼                              ▼
     ToolInvocation                   EvalContext
                                          │
                                          ▼
                                    Judge::evaluate()
                                          │
                                          ▼
                                     JudgeResult

     SampleResults
   (Collection<EvalResult>
    + minimum)
```

Each box is an abstraction-layer type. The only "core" component in this graph is the agent runner, which bridges `EvalCase` → `EvalResult`.

---

## What's Deliberately NOT Here

| Concern | Why it's excluded | Layer |
|---------|-------------------|-------|
| `evaluate()` function | Outer API entry point. The abstraction doesn't know about PEST. | Outer |
| Fluent builder / assertion chain | Developer experience wrapper. May change. | Outer |
| Agent resolution & invocation | How you turn `Agent::class` into a response is an implementation detail. | Core |
| LLM judge implementation | The built-in judge that interprets strings and Rubrics via an LLM call. | Core |
| Similarity judge | A specific Judge implementation using embeddings or LLM comparison. | Core |
| Sample verdict aggregation | Per-sample × per-assertion tracking, pass rates, average scores. | Core |
| Dataset loading implementation (`DatasetLoader`) | File parsing logic. Core implements the `LoadsDatasets` contract. | Core |
| Configuration (`config/evals.php`) | Runtime configuration. Core reads it, abstraction doesn't care. | Core |
| CLI output formatting | Presentation concern. | Core |
| Assertion methods (`assertMeets`, `assertContains`, etc.) | Outer API that uses Judges and EvalResults internally. | Outer |
| BDD aliases (`toMeet`, `toBe`, etc.) | Outer API sugar. | Outer |

---

## Implementation Order

1. **Value objects first:** `JudgeResult` → `ToolInvocation` → `EvalResult` → `EvalContext` → `EvalCase` → `SampleResults`
2. **Contracts last:** `Judge` → `Rubric` → `LoadsDatasets`

Value objects have zero dependencies on each other (except `EvalContext` → `EvalResult` and `SampleResults` → `EvalResult`). Contracts depend on value objects (`LoadsDatasets` depends on `EvalCase`). Ship them in dependency order and the abstraction layer compiles from the first commit.

---

## Namespace Summary

| FQCN | Kind | One-liner |
|------|------|-----------|
| `Redberry\Evals\Contracts\Judge` | Interface | Custom evaluation logic. One method: `evaluate(EvalContext): JudgeResult`. |
| `Redberry\Evals\Contracts\Rubric` | Abstract class | Reusable LLM evaluation criteria. One required method: `description(): string`. |
| `Redberry\Evals\Contracts\LoadsDatasets` | Interface | Dataset loading contract. Three methods: `fromJson`, `fromXml`, `fromDirectory`. |
| `Redberry\Evals\EvalCase` | Value object (final) | What to send to the agent — prompt, expected output, attachments. |
| `Redberry\Evals\EvalResult` | Value object | What came back — text, structured data, tool invocations, raw response. |
| `Redberry\Evals\EvalContext` | Value object | What the judge sees — input, output, expected, full result. |
| `Redberry\Evals\JudgeResult` | Value object | What the judge decided — passed, score, reasoning. |
| `Redberry\Evals\ToolInvocation` | Value object | A single tool call — name, class, arguments, result. |
| `Redberry\Evals\SampleResults` | Collection (final) | Pure container of `EvalResult` objects + `minimum` threshold. No judge aggregation. |
