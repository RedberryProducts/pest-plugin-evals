# Feasibility Report: GOAL-2 vs Laravel AI SDK

**Date:** 2026-02-18  
**SDK Source:** [github.com/laravel/ai](https://github.com/laravel/ai) (main branch)  
**Verdict:** ✅ **Fully Feasible** — the SDK provides all required building blocks.

---

## 1. Agent & Entry Point

`Laravel\Ai\Contracts\Agent` has `prompt()`, `stream()`, `queue()`. The `Promptable` trait adds `make()` with container resolution.

```php
// All four evaluate() signatures map directly:
evaluate(SalesCoach::class);                          // Container resolve
evaluate(SalesCoach::class, ['user' => $user]);       // Promptable::make() uses makeWith()
evaluate(new SalesCoach($user));                      // Direct instance
evaluate(fn () => SalesCoach::make(user: $user));     // Factory closure
```

**Status:** ✅ Ready

---

## 2. Prompt / Input Handling

`Promptable::prompt()` signature:

```php
public function prompt(
    string $prompt,
    array $attachments = [],
    Lab|array|string|null $provider = null,
    ?string $model = null,
    ?int $timeout = null
): AgentResponse
```

| GOAL-2 Feature | SDK Support |
|---|---|
| `prompt('text')` | ✅ Direct |
| `provider: Lab::Anthropic` | ✅ `Lab` enum exists |
| `model: 'claude-...'` | ✅ `?string $model` |
| `timeout: 120` | ✅ `?int $timeout` |
| `attachments: [...]` | ✅ `array $attachments` |
| `Files\Document::fromStorage()` | ✅ Classes exist |
| `Files\Image::fromPath()` | ✅ Classes exist |

**Status:** ✅ Ready

---

## 3. Response / Output Handling

| Response Type | SDK Class | Access Pattern |
|---|---|---|
| Text | `AgentResponse` → `TextResponse` | `$response->text` (string) |
| Structured | `StructuredAgentResponse` | `$response['key']`, `->toArray()` (implements `ArrayAccess`, `Arrayable`) |
| Detection | Check `$agent instanceof HasStructuredOutput` | — |

Both types expose `->toolCalls`, `->toolResults`, `->steps` collections.

**Status:** ✅ Ready

---

## 4. Tool Assertions — Implementation Strategy

### 4.1 Data Available on Response

```php
$response->toolCalls;    // Collection<ToolCall>  — {id, name, arguments}
$response->toolResults;  // Collection<ToolResult> — {id, name, arguments, result}
$response->steps;        // Collection<Step>       — each with toolCalls/toolResults
```

### 4.2 Tool Name Resolution

The SDK registers tools using `class_basename()` or `$tool->name()` if the method exists:

```php
// In AddsToolsToPrismRequests::createPrismTool():
$toolName = method_exists($tool, 'name') ? $tool->name() : class_basename($tool);
```

For `assertToolUsed(WebSearch::class)` → resolve to `'WebSearch'` and match against `$toolCall->name`.

### 4.3 Recommended Approach: Event Listeners

The SDK dispatches two events during tool execution:

```php
// Laravel\Ai\Events\InvokingTool
public string $invocationId;
public string $toolInvocationId;
public Agent $agent;
public Tool $tool;          // ← actual Tool instance (has FQCN)
public array $arguments;

// Laravel\Ai\Events\ToolInvoked
// Same as above, plus:
public mixed $result;
```

**Listen to these events during `evaluate()` execution** to capture everything `ToolInvocation` needs:

| ToolInvocation Property | Source |
|---|---|
| `$tool->toolClass` | `$event->tool::class` (FQCN) |
| `$tool->toolName` | `class_basename($event->tool)` or `$event->tool->name()` |
| `$tool->arguments` | `$event->arguments` |
| `$tool->result` | `$event->result` (from `ToolInvoked`) |
| `$tool->query` (magic `__get`) | `$event->arguments['query']` |

### 4.4 Alternative: Response-Only (Fallback)

If events aren't available (e.g., faked agents), fall back to `$response->toolCalls` + `$response->toolResults`. The only limitation: `$toolCall->name` is the short name, not FQCN. Resolve via `$agent->tools()` (if `HasTools`) to build a `name → class` map.

### 4.5 All Tool Assertions: Feasible

| Assertion | How |
|---|---|
| `assertToolUsed(Class\|string)` | Match against events or `toolCalls` collection |
| `assertToolUsed(Class, ['key' => 'val'])` | Compare `$toolCall->arguments` |
| `assertToolUsed(Class, fn(ToolInvocation) => ...)` | Build `ToolInvocation` from event data |
| `assertToolNotUsed(Class)` | Inverse check |
| `assertToolUseSequence([...])` | Check `toolCalls` order |
| `assertToolUsedTimes(Class, n)` | Count matching calls |
| `assertToolUsedAtLeast/AtMost` | Count constraints |

**Status:** ✅ Ready

---

## 5. Anonymous (Magic) Judges

Use the SDK's own `agent()` helper with structured output to implement `assertMeets()`:

```php
use function Laravel\Ai\agent;

$judge = agent(
    instructions: 'You are an evaluation judge. Evaluate if the output meets the criterion...',
    schema: fn ($s) => [
        'passed'    => $s->boolean()->required(),
        'score'     => $s->integer()->required(),
        'reasoning' => $s->string()->required(),
    ],
);

$result = $judge->prompt(
    "Criterion: {$criterion}\n\nOutput:\n{$output}",
    provider: $judgeProvider,
    model: $judgeModel,
);

// $result['passed'], $result['score'], $result['reasoning']
```

| Judge Feature | Implementation |
|---|---|
| `assertMeets('string')` | Anonymous structured agent as judge |
| `assertMeets('string', 80)` | Check `$result['score'] >= 80` |
| `assertDoesNotMeet('string')` | Negate judge result |
| `assertSimilarTo('expected')` | Similarity prompt via same mechanism |
| `assertPasses(new CustomJudge)` | Our own `Judge` interface, internally uses SDK |
| `Rubric` classes | Our abstraction, `description()` feeds the judge prompt |
| `->judgeWith(Lab::OpenAI, 'model')` | Override provider/model on judge agent |

**Status:** ✅ Ready

---

## 6. Structured Output Assertions

`StructuredAgentResponse` implements `ArrayAccess` and `Arrayable`:

```php
$response['name'];           // ArrayAccess
$response->toArray();        // Full array
data_get($response, 'a.b'); // Dot notation via Laravel helper
```

All `assertHasKey()`, `assertHasKeys()`, `assertMatchesArray()` etc. are simple wrappers.

**Status:** ✅ Ready

---

## 7. No SDK Dependency (Plugin-Only Features)

These features are entirely our own code with zero SDK friction:

- **EvalCase** — value object with `prompt`, `expected`, `attachments`
- **fromJson() / fromXml() / fromDirectory()** — file parsers
- **Sampling** — call `prompt()` N times, aggregate results
- **Rubric / Judge contracts** — our own interfaces
- **CLI output** — PEST plugin hooks
- **Config (evals.php)** — Laravel config publishing

**Status:** ✅ Ready

---

## 8. Faking for Plugin Tests

The SDK has a full fake system:

```php
AssistantAgent::fake(['First response', 'Second response']);
AssistantAgent::assertPrompted('...');
AssistantAgent::assertPrompted(fn (AgentPrompt $p) => $p->contains('...'));
```

Structured faking:

```php
StructuredAgent::fake([new StructuredTextResponse(['symbol' => 'Au'], ...)]);
```

We can use this to unit-test our plugin's assertion logic without real API calls.

**Status:** ✅ Ready

---

## 9. Risks & Considerations

| Risk | Severity | Mitigation |
|---|---|---|
| Tool name is `class_basename`, not FQCN | Low | Resolve via `$agent->tools()` mapping or events |
| `ToolCall` and `ToolResult` are separate objects | Low | Correlate by `id`, or use events which provide both |
| SDK uses `Lab` enum for providers; GOAL-2 also uses strings | None | SDK accepts `Lab\|array\|string\|null` |
| `timeout` is on `Promptable` trait, not `Agent` interface | None | All agents use `Promptable` |
| FakeTextGateway's `onToolInvocation` is a no-op | Low | For faked agents, fall back to `$response->toolCalls` |

---

## 10. Summary

| GOAL-2 Category | Feasibility | Approach |
|---|---|---|
| `evaluate()` entry point | ✅ 100% | Agent contract + container |
| Prompt/Input | ✅ 100% | Direct `Promptable::prompt()` mapping |
| Deterministic assertions | ✅ 100% | String/array checks on response |
| LLM-as-Judge | ✅ 100% | `agent()` with structured output |
| Tool assertions | ✅ 100% | `InvokingTool`/`ToolInvoked` events + response data |
| Structured output | ✅ 100% | `StructuredAgentResponse` with `ArrayAccess` |
| Datasets / EvalCase | ✅ 100% | Plugin-only, no SDK dependency |
| Sampling | ✅ 100% | Plugin-only, call `prompt()` N times |
| Custom Judges / Rubrics | ✅ 100% | Plugin-only contracts |
| Config / CLI | ✅ 100% | Plugin-only |
