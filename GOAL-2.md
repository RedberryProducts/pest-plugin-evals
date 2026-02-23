# PEST Evals Plugin - Design Document

A PEST plugin for evaluating Laravel AI SDK agents with effortless testing and robust customization.

---

## Table of Contents

1. [Philosophy](#philosophy)
2. [Quick Start](#quick-start)
3. [Core API](#core-api)
   - [BDD-Style Syntax](#bdd-style-syntax-alternative)
4. [Prompting & Input](#prompting--input)
5. [Assertions](#assertions)
   - [Deterministic Assertions](#deterministic-assertions)
   - [LLM-as-a-Judge Assertions](#llm-as-a-judge-assertions)
   - [Tool Assertions](#tool-assertions)
   - [Structured Output Assertions](#structured-output-assertions)
6. [Sampling](#sampling)
7. [Datasets](#datasets)
   - [EvalCase](#evalcase)
   - [JSON Datasets](#json-datasets)
   - [XML Datasets](#xml-datasets)
8. [Custom Judges](#custom-judges)
9. [Configuration](#configuration)
10. [CLI Output](#cli-output)
11. [Full Examples](#full-examples)

---

## Philosophy

This plugin follows three core principles:

1. **Effortless Testing** — Writing an eval should be as simple as writing a regular PEST test
2. **Laravel AI SDK Native** — Direct integration with `Laravel\Ai\Contracts\Agent` classes
3. **Magic Judges** — Pass a string and get LLM-based evaluation without creating a class

```php
// This is all you need to evaluate an agent
test('sales coach provides constructive feedback', function () {
    evaluate(SalesCoach::class)
        ->prompt('The customer said "too expensive" and I hung up.')
        ->assertMeets('The response should offer negotiation tactics')
        ->assertMeets('The tone should be encouraging, not critical');
});
```

---

## Quick Start

### Installation

```bash
composer require redberry/pest-plugin-evals --dev
```

### Your First Eval

```php
use App\Ai\Agents\PostWriter;

test('PostWriter writes engaging content', function () {
    evaluate(PostWriter::class)
        ->prompt('Write a blog post about Laravel')
        ->assertContains('Laravel')
        ->assertMeets('The content is engaging and informative');
});
```

---

## Core API

### Entry Point: `evaluate()`

The `evaluate()` function is the entry point for all evaluations. It accepts an Agent class and resolves it via Laravel's container.

```php
use App\Ai\Agents\SalesCoach;
use App\Models\User;

// Basic usage
evaluate(SalesCoach::class);

// With constructor arguments (resolved via container)
evaluate(SalesCoach::class, ['user' => $user]);

// With agent instance
evaluate(new SalesCoach($user));

// With agent factory
evaluate(fn () => SalesCoach::make(user: $user));
```

### Prompt Method Signature

The `prompt()` method mirrors Laravel AI SDK's signature:

```php
evaluate(Agent::class)
    ->prompt(
        'Your prompt here',
        provider: Lab::Anthropic,           // Override provider
        model: 'claude-haiku-4-5-20251001', // Override model
        timeout: 120,                        // Set timeout
        attachments: [                       // Add files/images
            Files\Document::fromStorage('file.pdf'),
        ],
    )
    ->assertMeets('...')                    // LLM assertion
    ->assertContains('...');                // Deterministic assertion
```

### Fluent Chain (Alternative)

All prompt parameters are also available as separate fluent methods:

```php
evaluate(Agent::class)
    ->attachments([...])                    // Add files/images
    ->provider(Lab::Anthropic)              // Override provider
    ->model('claude-haiku-4-5-20251001')    // Override model
    ->timeout(120)                          // Set timeout
    ->prompt('Your prompt here')            // Execute with the prompt
    ->run()                                 // Execute explicitly (optional, auto-runs on assert)
    ->assertMeets('...')                    // LLM assertion
    ->assertContains('...');                // Deterministic assertion
```

> **Note:** Fluent methods set defaults that can be overridden by `prompt()` parameters.

### BDD-Style Syntax (Alternative)

For developers who prefer a more natural-language, BDD-style API, all assertions have `to*` aliases:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('The customer said "too expensive" and I hung up.')
    ->toMeet('The response should offer negotiation tactics')
    ->toBeSimilarTo('Expected response content here');
```

| Standard API | BDD-Style Alias |
|--------------|-----------------|
| `prompt()` | `whenPrompted()` |
| `assertMeets()` | `toMeet()` |
| `assertSimilarTo()` | `toBeSimilarTo()` |
| `assertSimilar()` | `toBeSimilar()` |
| `assertEquals()` / `assertMatchesArray()` | `toBe()` (exact match, auto-detects type) |

#### With Expected Value

```php
evaluate(SalesCoach::class)
    ->whenPrompted('The customer said "too expensive" and I hung up.')
    ->expected($expectedOutput)
    ->toMeet($criteria)       // Rubric check, same as assertMeets
    ->toBeSimilar();          // Similarity check, same as assertSimilar
```

#### With Structured Output (Exact Match)

For agents with structured output, `toBe()` performs a deterministic exact match:

```php
evaluate(DataExtractor::class)
    ->whenPrompted('Extract user info from: John Doe, john@example.com')
    ->toBe([
        'name'  => 'John Doe',
        'email' => 'john@example.com',
    ]);
```

#### With String Output (Exact Match)

`toBe()` also works with strings for exact text comparison:

```php
evaluate(Greeter::class)
    ->whenPrompted('Say hello to John')
    ->toBe('Hello, John!');
```

> **Note:** `toBe()` is deterministic — it checks for exact equality (uses `assertMatchesArray()` for arrays, `assertEquals()` for strings). For fuzzy matching, use `toBeSimilarTo()` or `toMeet()`.

#### Combining Styles

You can mix standard and BDD-style methods in the same chain:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('Review this call...')
    ->toMeet('Professional tone')
    ->assertContains('feedback')          // Standard assertion
    ->toBeSimilarTo($expectedResponse);
```

---

## Prompting & Input

### Simple Prompt

```php
evaluate(SalesCoach::class)
    ->prompt('Analyze this sales call transcript...');
```

### With Provider & Model Override

```php
use Laravel\Ai\Enums\Lab;

evaluate(SalesCoach::class)
    ->prompt(
        'Analyze this sales call transcript...',
        provider: Lab::Anthropic,
        model: 'claude-3-5-sonnet',
    );

// Or using fluent methods
evaluate(SalesCoach::class)
    ->provider(Lab::Anthropic)
    ->model('claude-3-5-sonnet')
    ->prompt('Analyze this sales call transcript...');
```

### With Attachments

Attachments can be passed directly to `prompt()` or via the fluent method:

```php
use Laravel\Ai\Files;

// Inline with prompt (recommended - matches Laravel AI SDK)
evaluate(DocumentAnalyzer::class)
    ->prompt(
        'Summarize this document',
        attachments: [
            Files\Document::fromStorage('contracts/agreement.pdf'),
            Files\Document::fromPath('/home/user/transcript.md'),
            Files\Image::fromStorage('screenshot.png'),
            $request->file('upload'), // Uploaded file
        ],
    )
    ->assertMeets('Summary captures key contract terms');

// Or using fluent method
evaluate(DocumentAnalyzer::class)
    ->attachments([
        Files\Document::fromStorage('contracts/agreement.pdf'),
    ])
    ->prompt('Summarize this document')
    ->assertMeets('Summary captures key contract terms');
```

### With Timeout

```php
evaluate(SlowAgent::class)
    ->prompt(
        'Process this large dataset...',
        timeout: 300, // 5 minutes
    );

// Or using fluent method
evaluate(SlowAgent::class)
    ->timeout(300)
    ->prompt('Process this large dataset...');
```

### Complete Example with All Options

```php
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files;

evaluate(SalesCoach::class)
    ->prompt(
        'Analyze this call recording',
        provider: Lab::OpenAI,
        model: 'gpt-4o',
        timeout: 120,
        attachments: [
            Files\Document::fromStorage('call-transcript.pdf'),
        ],
    )
    ->assertMeets('Provides actionable feedback');
```

### Using EvalCase

`EvalCase` requires only a `prompt`. Everything else — `expected()`, `attachments()` — is optional.

> **Note:** `expected()` is **required** when using `assertSimilar()` (similarity judges need an expected value to compare against). Alternatively, use `assertSimilarTo('expected text')` which accepts the expected output inline as its first argument. For all other assertions, `expected()` is optional but recommended as it documents intent and makes the case self-contained.

**Minimal case — prompt only:**

```php
use Redberry\Evals\EvalCase;

$case = EvalCase::make()
    ->prompt('Write a haiku about PHP');

evaluate(HaikuWriter::class)
    ->withCase($case)
    ->assertMeets('The response is a valid haiku with 5-7-5 syllables');
```

**With expectation — plain text output:**

```php
use Redberry\Evals\EvalCase;

$case = EvalCase::make()
    ->prompt('Kindly ask to contact us at hello@example.com')
    ->expected('Please, contact us at hello@example.com');

evaluate(SupportAgent::class)
    ->withCase($case)
    ->assertMeets('asks to contact at hello@example.com')
    ->assertSimilarTo($case->expected); // expectation required for similarity
```

**With expectation — structured output:**

```php
use Redberry\Evals\EvalCase;

$case = EvalCase::make()
    ->prompt('Extract the email from this text: Contact us at hello@example.com')
    ->expected(['email' => 'hello@example.com']);

$result = evaluate(DataExtractor::class)
    ->withCase($case)
    ->run();

expect($result)->toMatchArray($case->expected);
```

**With attachments:**

```php
use Redberry\Evals\EvalCase;
use Laravel\Ai\Files;

$case = EvalCase::make()
    ->prompt('What are the key terms in this contract?')
    ->attachments([
        Files\Document::fromStorage('contracts/agreement.pdf'),
    ])
    ->expected('Key terms include payment schedule, termination clause, and liability cap');

evaluate(ContractAnalyzer::class)
    ->withCase($case)
    ->assertSimilarTo($case->expected);
```

---

## Assertions

### Deterministic Assertions

Classic PHP assertions that don't require LLM calls:

```php
evaluate(CopyWriter::class)
    ->prompt('Write a tweet about Laravel')
    
    // String assertions
    ->assertContains('#Laravel')
    ->assertContains(['Laravel', 'PHP'])          // Contains all
    ->assertContainsAny(['Laravel', 'Symfony'])   // Contains at least one
    ->assertNotContains('bad word')
    ->assertMatches('/Laravel \d+/')              // Regex
    
    // Length assertions  
    ->assertLengthLessThan(280)
    ->assertLengthGreaterThan(10)
    ->assertLengthBetween(50, 280)
    
    // JSON assertions (for structured output)
    ->assertJson()
    ->assertJsonPath('user.name', 'Taylor')
    ->assertJsonStructure(['user' => ['name', 'email']])
    
    // Type assertions
    ->assertString()
    ->assertArray()
    ->assertNotEmpty();
```

### LLM-as-a-Judge Assertions

Pass a natural language expectation, and an LLM evaluates compliance:

```php
evaluate(SalesCoach::class)
    ->prompt('Review this call transcript...')
    
    // Magic string-based judges (most common)
    ->assertMeets('The feedback is constructive, not critical')        // binary pass/fail
    ->assertMeets('Specific moments from the transcript are referenced', 80)  // must score >= 80/100
    ->assertMeets('At least 3 actionable suggestions are provided')
    
    // Negation
    ->assertDoesNotMeet('The response contains profanity or insults')
    
    // Similarity judge - compares output to expected value
    ->assertSimilarTo('Expected response content here')
    ->assertSimilarTo('Expected response', threshold: 85)  // Custom threshold
    
    // Custom Judge class — for full control over evaluation logic
    ->assertPasses(new SimilarityJudge(threshold: 90));
```

### Judge Result Access

Run a judge and get back the raw `JudgeResult` for use with PEST's `expect()`:

```php
// Default LLM judge — string criterion
$result = evaluate(SalesCoach::class)
    ->prompt('...')
    ->judge('Is the response helpful?');

// With a Rubric
$result = evaluate(SalesCoach::class)
    ->prompt('...')
    ->judge('Is the tone professional?', new ProfessionalTone);

// With a custom Judge class (expects ->expected() to be set)
$result = evaluate(SalesCoach::class)
    ->prompt('...')
    ->expected('The expected response text')
    ->judge('Similarity check', new SimilarityJudge(threshold: 90));

$result->passed;     // bool
$result->score;      // int 0-100
$result->reasoning;  // string - LLM's explanation

// Use in assertions
expect($result->score)->toBeGreaterThan(80);
expect($result->passed)->toBeTrue();
```

### Tool Assertions

For agents that use tools. All tool assertions accept either a **tool class reference** or a **string name**. The second argument can be an **array** (exact argument match) or a **closure** for flexible inspection — just like `Event::assertDispatched`.

#### By String Name

Use string names when referring to tools generically or in JSON/XML datasets:

```php
evaluate(ResearchAgent::class)
    ->prompt('Find information about Laravel 12')
    
    // Tool was called
    ->assertToolUsed('web_search')
    ->assertToolUsed('web_search', ['query' => 'Laravel 12'])
    
    // Tool was not called
    ->assertToolNotUsed('dangerous_tool')
    
    // Tool call sequence
    ->assertToolUseSequence(['web_search', 'summarize'])
    
    // Tool call count
    ->assertToolUsedTimes('web_search', 2) // exact count
    ->assertToolUsedAtLeast('web_search', 2) // min
    ->assertToolUsedAtMost('web_search', 5); // max
```

#### By Tool Class

Use tool class references for type-safety and refactoring support (recommended in PHP test files):

```php
use App\Ai\Tools\WebSearch;
use App\Ai\Tools\Summarize;
use App\Ai\Tools\DangerousTool;

evaluate(ResearchAgent::class)
    ->prompt('Find information about Laravel 12')
    
    // Tool was called (by class)
    ->assertToolUsed(WebSearch::class)
    ->assertToolUsed(WebSearch::class, ['query' => 'Laravel 12'])
    
    // Tool was not called
    ->assertToolNotUsed(DangerousTool::class)
    
    // Tool call sequence (by class)
    ->assertToolUseSequence([WebSearch::class, Summarize::class])
    
    // Tool call count (by class)
    ->assertToolUsedTimes(WebSearch::class, 2)    // exact count
    ->assertToolUsedAtLeast(WebSearch::class, 2)  // min
    ->assertToolUsedAtMost(WebSearch::class, 5);  // max
```

#### Inspecting Tool Arguments with Closures

Pass a closure to inspect the arguments the agent passed to the tool. The closure receives a `ToolInvocation` whose properties map to the tool's schema arguments — just like `Event::assertDispatched`:

```php
use App\Ai\Tools\RetrievePreviousTranscripts;
use App\Ai\Tools\WebSearch;

evaluate(SalesCoach::class)
    ->prompt('Check my last 3 transcripts')
    ->assertToolUsed(RetrievePreviousTranscripts::class, function (ToolInvocation $tool) {
        return $tool->limit === 3; // Inspecting the arguments the LLM chose
    });

evaluate(ResearchAgent::class)
    ->prompt('Find recent Laravel 12 release notes')
    ->assertToolUsed(WebSearch::class, function (ToolInvocation $tool) {
        return str_contains($tool->query, 'Laravel 12');
    });
```

> **How it works:** `ToolInvocation` wraps the arguments the LLM passed to the tool. Properties like `$tool->limit` or `$tool->query` correspond to keys in the tool's `schema()`. The closure must return `true` for the assertion to pass. If the tool was called multiple times, the assertion passes when **at least one** invocation satisfies the closure (matching `Event::assertDispatched` semantics).

#### Combining Closure with Count

When you need both argument inspection and count constraints:

```php
evaluate(ResearchAgent::class)
    ->prompt('Compare Laravel and Symfony frameworks')
    // At least 2 calls must match the closure
    ->assertToolUsedAtLeast(WebSearch::class, 2, function (ToolInvocation $tool) {
        return str_contains($tool->query, 'Laravel')
            || str_contains($tool->query, 'Symfony');
    });
```

#### Method Signatures

Every tool assertion method accepts a **string name** or **class reference** as its first argument. The second argument varies:

| Method | Signature |
|--------|-----------|
| `assertToolUsed` | `(string\|class, array\|Closure\|null)` |
| `assertToolNotUsed` | `(string\|class)` |
| `assertToolUseSequence` | `(array)` — array of strings and/or class references |
| `assertToolUsedTimes` | `(string\|class, int, Closure\|null)` |
| `assertToolUsedAtLeast` | `(string\|class, int, Closure\|null)` |
| `assertToolUsedAtMost` | `(string\|class, int, Closure\|null)` |

#### ToolInvocation API

The `ToolInvocation` object passed to closures provides:

```php
$tool->query;          // Access argument by name (magic __get)
$tool->arguments;      // array — all arguments the LLM passed
$tool->toolClass;      // string — FQCN of the tool (e.g., WebSearch::class)
$tool->toolName;       // string — tool name (e.g., 'web_search')
$tool->result;         // mixed — the return value from the tool's handle()
```

### Structured Output Assertions

For agents implementing `HasStructuredOutput`, use fluent assertion methods that mirror PEST's expectations API with the `assert` prefix:

```php
evaluate(DataExtractor::class)
    ->prompt('Extract user info from: John Doe, john@example.com')

    // Key exists (array key, supports dot notation)
    ->assertHasKey('name')
    ->assertHasKey('address.city')

    // Key exists with expected value
    ->assertHasKey('name', 'John Doe')
    ->assertHasKey('address.city', 'Tbilisi')

    // Multiple keys exist (supports dot notation)
    ->assertHasKeys(['name', 'email', 'address.city'])

    // Property exists (object property)
    ->assertHasProperty('name')

    // Property with expected value
    ->assertHasProperty('name', 'John Doe')

    // Multiple properties exist
    ->assertHasProperties(['name', 'email'])

    // Partial array match
    ->assertMatchesArray([
        'name'  => 'John Doe',
        'email' => 'john@example.com',
    ]);
```

You can also call `->run()` and use PEST's native `expect()` directly:

```php
$result = evaluate(DataExtractor::class)
    ->prompt('Extract user info from: John Doe, john@example.com')
    ->run();

expect($result)->toHaveProperty('name', 'John Doe');
expect($result)->toMatchArray([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);
```

---

## Sampling

LLMs are non-deterministic — the same prompt can produce wildly different outputs. Sampling runs your agent multiple times with the same input and evaluates **each output independently**, giving you confidence that performance is **consistent**, not a lucky one-off.

> **Alias:** `->repeat()` is an alias for `->samples()` — use whichever reads better in your test.

### Basic Usage

Just chain `->samples()` (or `->repeat()`) — the plugin runs the agent N times and asserts every sample:

```php
evaluate(SalesCoach::class)
    ->prompt('Review this sales call...')
    ->samples(5)
    ->assertMeets('The feedback is constructive');

// Same thing, alternative name
evaluate(SalesCoach::class)
    ->prompt('Review this sales call...')
    ->repeat(5)
    ->assertMeets('The feedback is constructive');
```

This runs the agent **5 times** and every sample must pass. If even one fails, the test fails.

### Allowing Variance

LLMs aren't perfect. If you're OK with occasional misses, specify the minimum number of samples that must pass:

```php
evaluate(SalesCoach::class)
    ->prompt('Review this sales call...')
    ->samples(5, minimum: 4)
    ->assertMeets('The feedback is constructive');
```

This runs 5 samples and passes as long as **at least 4** meet the criterion.

### Scored Assertions

For scored assertions (those with a threshold), each sample must individually meet the threshold:

```php
evaluate(SalesCoach::class)
    ->prompt('...')
    ->samples(5)
    ->assertMeets('Professional tone', 80);  // Every sample must score >= 80
```

Combined with `minimum`, this becomes: "at least N samples must score above the threshold":

```php
evaluate(SalesCoach::class)
    ->prompt('...')
    ->samples(5, minimum: 4)
    ->assertMeets('Professional tone', 80);  // At least 4 of 5 must score >= 80
```

This catches inconsistency — a single lucky run might score 95, but sampling proves the agent reliably meets the bar.

### With Deterministic Assertions

Sampling works with every assertion type. Each sample is checked individually:

```php
evaluate(CopyWriter::class)
    ->prompt('Write a tweet about Laravel')
    ->samples(3)
    ->assertContains('Laravel')       // All 3 must contain "Laravel"
    ->assertLengthLessThan(280)       // All 3 must be under 280 chars
    ->assertMeets('The tone is enthusiastic');  // All 3 must pass
```

### With Minimum on Mixed Assertions

The `minimum` applies globally to all assertions in the chain:

```php
evaluate(CopyWriter::class)
    ->prompt('Write a tweet about Laravel')
    ->samples(5, minimum: 4)
    ->assertContains('Laravel')                  // At least 4 of 5
    ->assertMeets('Tone is enthusiastic')        // At least 4 of 5
    ->assertMeets('Mentions a specific feature', 75);  // At least 4 of 5 must score >= 75
```

### Accessing Sample Results

Call `->run()` with sampling to get a `SampleResults` collection:

```php
$samples = evaluate(DataExtractor::class)
    ->prompt('Extract: John, john@example.com')
    ->samples(5)
    ->run();

$samples->count();          // 5
$samples->outputs();        // Collection of all raw outputs
$samples->first();          // First sample output
$samples->last();           // Last sample output
```

You can also judge the samples manually and inspect aggregate results:

```php
$samples = evaluate(SalesCoach::class)
    ->prompt('...')
    ->samples(5)
    ->judge('Is the response helpful?');

$samples->passRate();       // 80 (4 of 5 passed)
$samples->averageScore();   // 82 (convenience aggregate)
$samples->passed();         // true/false based on minimum + threshold
$samples->judgeResults();   // Collection of individual JudgeResult objects

// Iterate individual results
$samples->each(function (JudgeResult $result, int $index) {
    dump("Sample #{$index}: score={$result->score}, passed={$result->passed}");
});
```

### With Tool Assertions

Tool assertions under sampling check each sample independently:

```php
use App\Ai\Tools\WebSearch;
use App\Ai\Tools\RetrievePreviousTranscripts;

evaluate(ResearchAgent::class)
    ->prompt('Find information about Laravel 12')
    ->samples(3, minimum: 2)
    ->assertToolUsed(WebSearch::class)                   // At least 2 of 3 must use WebSearch
    ->assertToolUsedAtMost(WebSearch::class, 3)          // Each run uses it at most 3 times
    ->assertToolUsed(WebSearch::class, function (ToolInvocation $tool) {
        return str_contains($tool->query, 'Laravel');    // At least 2 of 3 must match
    });
```

### With Datasets

Sampling composes naturally with PEST datasets — each case runs N times:

```php
it('consistently extracts emails', function (EvalCase $case) {
    evaluate(EmailExtractor::class)
        ->withCase($case)
        ->samples(3)
        ->assertMeets($case->expected);
})->with('email_cases');
```


---

## Datasets

### EvalCase

The `EvalCase` object provides structure for evaluation test cases:

```php
use Redberry\Evals\EvalCase;

// Inline creation
dataset('sales_scenarios', [
    'angry customer' => fn () => EvalCase::make()
        ->prompt('I want a refund NOW!')
        ->expected('Calm de-escalation response'),
        
    'confused customer' => fn () => EvalCase::make()
        ->prompt('How do I log in?')
        ->expected('Step-by-step instructions'),
]);

// Usage
it('handles customer scenarios', function (EvalCase $case) {
    evaluate(SupportBot::class)
        ->withCase($case)
        ->assertMeets($case->expected);
})->with('sales_scenarios');
```

### EvalCase with Attachments

Attachments can also be set on the case itself:

```php
EvalCase::make()
    ->attachments([
        Files\Document::fromStorage('test-files/contract.pdf'),
    ])
    ->prompt('What are the key terms?')
    ->expected('Contract summary with dates and parties');
```

### JSON Datasets

JSON datasets contain **one case per file**. Only `prompt` is required; `expected` and `attachments` are optional.

> **File naming:** Use `.case.json` extension for auto-discovery with `fromDirectory()` (e.g., `contact-info.case.json`).

**Structured output case:**

> `tests/evals/data-extractor/contact-info.case.json`

```json
{
    "prompt": "Extract data from: John Doe, john@example.com, 555-1234",
    "expected": {
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "555-1234"
    }
}
```

**Plain text output case:**

> `tests/evals/support-bot/refund-request.case.json`

```json
{
    "prompt": "I want to return this product",
    "expected": "Polite acknowledgment with return instructions and next steps"
}
```

**Prompt-only case (no expectation):**

> `tests/evals/haiku-writer/php-haiku.case.json`

```json
{
    "prompt": "Write a haiku about PHP"
}
```

**Case with attachments:**

Attachments in JSON are described as objects with a `type` and a `source`. Supported types mirror the Laravel AI SDK's `Files\*` classes:

| `type`     | `source` key     | Description                                  |
|------------|------------------|----------------------------------------------|
| `document` | `storage`        | `Storage::disk()->path(...)` — app storage   |
| `document` | `path`           | Absolute filesystem path                     |
| `image`    | `storage`        | `Storage::disk()->path(...)` — app storage   |
| `image`    | `path`           | Absolute filesystem path                     |

> `tests/evals/contract-analyzer/agreement-review.case.json`

```json
{
    "prompt": "What are the key terms and the total contract value?",
    "expected": "Key terms include payment schedule, termination clause, and liability cap",
    "attachments": [
        {
            "type": "document",
            "source": "storage",
            "path": "evals/contracts/agreement.pdf"
        },
        {
            "type": "image",
            "source": "path",
            "path": "/tmp/evals/contract-signature-page.png"
        }
    ]
}
```

#### Loading JSON Cases

**Single file — `fromJson()`:**

```php
// Load individual files
dataset('data_extraction', [
    'contact-info' => fn () => EvalCase::fromJson('evals/data-extractor/contact-info.case.json'),
    'address-info' => fn () => EvalCase::fromJson('evals/data-extractor/address-info.case.json'),
]);
```

**Directory auto-discovery — `fromDirectory()`:**

Scans a directory for `*.case.json` files and returns an array of `EvalCase` objects keyed by filename (without extension).

```php
// Auto-discovers all .case.json files in the directory
dataset('data_extraction', fn () => EvalCase::fromDirectory('evals/data-extractor'));

// Results in cases keyed by filename:
// 'contact-info' => EvalCase(...)
// 'address-info' => EvalCase(...)
```

#### Usage Examples

```php
// Usage — structured output
it('extracts data correctly', function (EvalCase $case) {
    $result = evaluate(DataExtractor::class)
        ->withCase($case)
        ->run();
    
    expect($result)->toMatchArray($case->expected);
})->with('data_extraction');

// Usage — plain text output
it('support bot responds correctly', function (EvalCase $case) {
    evaluate(SupportBot::class)
        ->withCase($case)
        ->assertMeets($case->expected)
        ->assertSimilarTo($case->expected); // requires expected
})->with('support_cases');

// Usage — with attachments
it('analyzes contracts', function (EvalCase $case) {
    evaluate(ContractAnalyzer::class)
        ->withCase($case)  // attachments are automatically forwarded
        ->assertSimilarTo($case->expected);
})->with('contract_cases');
```

### XML Datasets

XML datasets support **multiple cases per file** via the `<evalset>` container. Only `<prompt>` is required; `<expected>` and `<attachments>` are optional.

> **File naming:** Use `.case.xml` extension for auto-discovery with `fromDirectory()` (e.g., `customer-support.case.xml`).

**Plain text output cases:**

```xml
<!-- tests/evals/scenarios/customer-support.case.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<evalset name="customer-support">
    <case name="refund-request">
        <prompt>I want to return this product</prompt>
        <expected>Polite acknowledgment with return instructions</expected>
    </case>
    <case name="complaint">
        <prompt>This product is terrible!</prompt>
        <expected>Empathetic response with solution offer</expected>
    </case>
    <!-- Prompt-only — no expectation needed -->
    <case name="open-ended">
        <prompt>What is your return policy?</prompt>
    </case>
</evalset>
```

**Structured output cases:**

```xml
<!-- tests/evals/data-extractor/extraction.case.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<evalset name="data-extraction">
    <case name="contact-info">
        <prompt>Extract data from: John Doe, john@example.com, 555-1234</prompt>
        <expected>
            <name>John Doe</name>
            <email>john@example.com</email>
            <phone>555-1234</phone>
        </expected>
    </case>
</evalset>
```

> **Note:** When `<expected>` contains child elements, `EvalCase::fromXml()` deserializes it as an associative array, matching the structured output format.

**Cases with attachments:**

Attachment elements use `type` (`document` or `image`) and `source` (`storage` or `path`) attributes, mirroring the Laravel AI SDK's `Files\*` classes:

```xml
<!-- tests/evals/contract-analyzer/contracts.case.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<evalset name="contract-analysis">
    <case name="agreement-review">
        <prompt>What are the key terms and the total contract value?</prompt>
        <expected>Key terms include payment schedule, termination clause, and liability cap</expected>
        <attachments>
            <attachment type="document" source="storage" path="evals/contracts/agreement.pdf" />
            <attachment type="image" source="path" path="/tmp/evals/contract-signature-page.png" />
        </attachments>
    </case>
</evalset>
```

#### Loading XML Cases

**Single file — `fromXml()`:**

```php
// Load all cases from a single XML file
dataset('customer_support', fn () => EvalCase::fromXml('evals/scenarios/customer-support.case.xml'));

// Returns cases keyed by their `name` attribute:
// 'refund-request' => EvalCase(...)
// 'complaint' => EvalCase(...)
// 'open-ended' => EvalCase(...)
```

**Directory auto-discovery — `fromDirectory()`:**

Scans a directory for `*.case.xml` files and returns a merged array of all cases from all files.

```php
// Auto-discovers all .case.json AND .case.xml files
dataset('all_cases', fn () => EvalCase::fromDirectory('evals/contract-analyzer'));
```

#### Usage Examples

```php
// Usage — plain text output
it('support bot responds correctly', function (EvalCase $case) {
    evaluate(SupportBot::class)
        ->withCase($case)
        ->assertMeets($case->expected);
})->with('customer_support');

// Usage — with attachments
it('analyzes contracts', function (EvalCase $case) {
    evaluate(ContractAnalyzer::class)
        ->withCase($case)  // attachments are automatically forwarded
        ->assertSimilarTo($case->expected);
})->with('all_cases');
```

### Dataset Directory Structure

Recommended structure for organizing eval datasets:

```
tests/
└── evals/
    ├── data-extractor/
    │   ├── contact-info.case.json       # Single case
    │   ├── address-info.case.json       # Single case
    │   └── edge-cases.case.xml          # Multiple cases in one file
    ├── support-bot/
    │   ├── refund-request.case.json
    │   └── common-questions.case.xml
    └── contract-analyzer/
        ├── agreements.case.xml
        └── fixtures/
            └── contract.pdf             # Attachment files
```

#### Loading Methods Summary

| Method | Format | Cases | File Pattern |
|--------|--------|-------|--------------|
| `EvalCase::fromJson($path)` | JSON | 1 per file | Any `.json` |
| `EvalCase::fromXml($path)` | XML | Multiple per file or 1 per file | `*.xml`, `*.case.xml` |
| `EvalCase::fromDirectory($dir)` | Both | Auto-discovery | `*.case.json`, `*.case.xml` |


---

## Custom Judges

### Rubric Classes

For complex, reusable evaluation criteria:

```php
namespace App\Evals\Rubrics;

use Redberry\Evals\Contracts\Rubric;

class ProfessionalTone extends Rubric
{
    public function description(): string
    {
        return <<<'PROMPT'
            Evaluate if the response maintains a professional tone:
            - No slang or informal language
            - Proper grammar and punctuation
            - Respectful and courteous
            - Appropriate for business communication
        PROMPT;
    }
    
    // Optional: return score 0-100 instead pass/failed
    public function scored(): bool
    {
        return true;
    }
}
```

```php
// Usage
evaluate(SalesCoach::class)
    ->prompt('...')
    ->assertMeets(new ProfessionalTone)
    ->assertMeets(new AccuracyRubric)
    ->assertMeets(new CompletenessRubric);
```

### Judge Classes (Full Control)

For complete control over the evaluation process:

```php
namespace App\Evals\Judges;

use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\JudgeResult;
use Redberry\Evals\EvalContext;

class SimilarityJudge implements Judge
{
    public function __construct(
        private float $threshold = 80
    ) {}
    
    public function evaluate(EvalContext $context): JudgeResult
    {
        // Access evaluation context
        $input = $context->input;           // Original prompt
        $actual = $context->output;         // Agent's response
        $expected = $context->expected;     // Expected output (if provided)
        
        // Your evaluation logic here
        // Could use embeddings, another LLM, etc.
        
        return new JudgeResult(
            passed: $similarity >= $this->threshold,
            score: $similarity,
            reasoning: "Similarity score: {$similarity}", // Or comment from LLM
        );
    }
}
```

```php
// Usage
evaluate(DataExtractor::class)
    ->withCase($case)
    ->assertPasses(new SimilarityJudge(threshold: 90));
```

### Comparison Judges

For comparing actual output against expected output:

```php
evaluate(Summarizer::class)
    ->prompt('Summarize this article...')
    ->assertSimilarTo('Expected summary...', threshold: 85);  // Custom threshold

// Or using fluent chain with separate expected
evaluate(Summarizer::class)
    ->prompt('Summarize this article...')
    ->expected('A concise summary mentioning key points X, Y, and Z')
    ->assertSimilar()                    // Uses default similarity threshold
    ->assertSimilar(threshold: 85);      // Custom threshold
```

---

## Configuration

### Global Configuration

```php
// config/evals.php
return [
    /*
    |--------------------------------------------------------------------------
    | Default Judge Provider
    |--------------------------------------------------------------------------
    |
    | The AI provider used for LLM-based assertions (assertMeets).
    | Using a cheap, fast model is recommended.
    |
    */
    'judge' => [
        'provider' => env('EVALS_JUDGE_PROVIDER', 'openai'),
        'model' => env('EVALS_JUDGE_MODEL', 'gpt-4o-mini'),
        'default_threshold' => 80,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    */
    'output' => [
        'verbose' => env('EVALS_VERBOSE', false),
        'show_reasoning' => env('EVALS_SHOW_REASONING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for sampling. These apply when ->samples() is called
    | without explicit overrides.
    |
    */
    'sampling' => [
        'default_samples' => env('EVALS_DEFAULT_SAMPLES', 1),
        'default_minimum' => null, // null = all must pass
    ],
];
```

### Per-Test Configuration

Override agent provider/model in the prompt:

```php
use Laravel\Ai\Enums\Lab;

evaluate(SalesCoach::class)
    ->prompt(
        'Analyze this transcript...',
        provider: Lab::Anthropic,
        model: 'claude-3-5-sonnet',
        timeout: 120,
    )
    ->assertMeets('...');
```

Override judge provider/model:

```php
evaluate(SalesCoach::class)
    ->judgeWith(Lab::OpenAI, 'gpt-4o-mini')
    ->prompt('...')
    ->assertMeets('...');
```

### Environment Variables

```bash
# .env.testing
EVALS_JUDGE_PROVIDER=openai
EVALS_JUDGE_MODEL=gpt-4o-mini
EVALS_VERBOSE=true
EVALS_DEFAULT_SAMPLES=1
```

---

## CLI Output

### Standard Output

```
   PASS  Tests\Evals\SalesCoachTest > provides constructive feedback
   PASS  Tests\Evals\SalesCoachTest > handles rejection gracefully
   FAIL  Tests\Evals\SalesCoachTest > maintains professional tone

  Tests:    2 passed, 1 failed
  Duration: 3.42s
```

### Verbose Output (with `--verbose` or `EVALS_VERBOSE=true`)

```
   FAIL  Tests\Evals\SalesCoachTest > maintains professional tone
  ─────────────────────────────────────────────────────────────────────
  
  Assertion: assertMeets('The response maintains a professional tone')
  
  ✗ FAILED
  
  Input:
  "The customer was rude and demanded a refund"
  
  Output:
  "Whatever, just take your refund and stop bothering us."
  
  Judge Reasoning:
  "The response uses dismissive language ('Whatever') and an
   aggressive tone ('stop bothering us'), which is unprofessional
   and inappropriate for customer service."
  
  Score: 20 / 100
  
  ─────────────────────────────────────────────────────────────────────
```

### Sampling Output

When using `->samples()`, the CLI shows aggregate results:

```
   PASS  Tests\Evals\SalesCoachTest > provides constructive feedback [5 samples]
   FAIL  Tests\Evals\SalesCoachTest > maintains professional tone [5 samples, 2/5 passed]
```

### Sampling Verbose Output

```
   FAIL  Tests\Evals\SalesCoachTest > maintains professional tone [5 samples]
  ─────────────────────────────────────────────────────────────────────
  
  Assertion: assertMeets('The response maintains a professional tone')
  
  ✗ FAILED  (2 of 5 passed, minimum: 4)
  
  Sample #1: ✓ PASS  (score: 92)
  Sample #2: ✓ PASS  (score: 88)
  Sample #3: ✗ FAIL  (score: 31)
    → "Uses dismissive language and aggressive tone."
  Sample #4: ✗ FAIL  (score: 25)
    → "Response contains sarcasm inappropriate for customer service."
  Sample #5: ✗ FAIL  (score: 40)
    → "Tone is condescending rather than professional."
  
  Pass Rate: 2/5 (40%)
  
  ─────────────────────────────────────────────────────────────────────
```

---

## Full Examples

### Basic Agent Evaluation

```php
use App\Ai\Agents\BlogWriter;

test('BlogWriter creates engaging content', function () {
    evaluate(BlogWriter::class)
        ->prompt('Write a blog post about PHP 8.4 features')
        ->assertContains('PHP')
        ->assertLengthGreaterThan(500)
        ->assertMeets('The content explains at least 3 new features')
        ->assertMeets('The writing style is engaging and accessible')
        ->assertDoesNotMeet('Contains factual errors about PHP');
});
```

### Structured Output Agent

```php
use App\Ai\Agents\DataExtractor;

test('DataExtractor parses contact information', function () {
    $result = evaluate(DataExtractor::class)
        ->prompt('John Smith, CEO at Acme Corp. Email: john@acme.com')
        ->run();
    
    expect($result)->toMatchArray([
        'name' => 'John Smith',
        'title' => 'CEO',
        'company' => 'Acme Corp',
        'email' => 'john@acme.com',
    ]);
});

test('DataExtractor parses contact information (fluent)', function () {
    evaluate(DataExtractor::class)
        ->prompt('John Smith, CEO at Acme Corp. Email: john@acme.com')
        ->assertHasProperty('name', 'John Smith')
        ->assertHasProperties(['title', 'company', 'email'])
        ->assertMatchesArray([
            'name'  => 'John Smith',
            'email' => 'john@acme.com',
        ]);
});
```

### Agent with Tools

```php
use App\Ai\Agents\ResearchAssistant;
use App\Ai\Tools\WebSearch;
use Laravel\Ai\Enums\Lab;
use Redberry\Evals\ToolInvocation;

test('ResearchAssistant uses web search appropriately', function () {
    evaluate(ResearchAssistant::class)
        ->prompt(
            'What are the latest Laravel 12 features?',
            provider: Lab::OpenAI,
            model: 'gpt-4o',
            timeout: 60,
        )
        ->assertToolUsed(WebSearch::class)
        ->assertToolUsed(WebSearch::class, function (ToolInvocation $tool) {
            return str_contains($tool->query, 'Laravel 12');
        })
        ->assertToolUsedAtMost(WebSearch::class, 3)
        ->assertMeets('Response cites sources from the web search')
        ->assertMeets('Information is current and accurate');
});
```

### Agent with Attachments

```php
use App\Ai\Agents\InvoiceAnalyzer;
use Laravel\Ai\Files;

test('InvoiceAnalyzer extracts totals from PDF', function () {
    evaluate(InvoiceAnalyzer::class)
        ->prompt(
            'What is the total amount due?',
            attachments: [
                Files\Document::fromStorage('invoices/invoice-2026-001.pdf'),
            ],
        )
        ->assertContains('$')
        ->assertMeets('Correctly identifies the total amount');
});
```

### Dataset-Driven Evaluation

```php
use Redberry\Evals\EvalCase;

dataset('email_extraction_cases', [
    'simple' => fn () => EvalCase::make()
        ->prompt('Extract: contact@example.com')
        ->expected(['email' => 'contact@example.com']),
        
    'multiple' => fn () => EvalCase::make()
        ->prompt('Extract: john@a.com and jane@b.com')
        ->expected(['emails' => ['john@a.com', 'jane@b.com']]),
        
    'with_context' => fn () => EvalCase::make()
        ->prompt('Contact us at hello@world.com for support')
        ->expected(['email' => 'hello@world.com', 'context' => 'support']),
]);

it('extracts emails accurately', function (EvalCase $case) {
    $result = evaluate(EmailExtractor::class)
        ->withCase($case)
        ->run();
    
    expect($result)->toMatchArray($case->expected);
    
    // Also validate with LLM judge
    evaluate(EmailExtractor::class)
        ->withCase($case)
        ->assertMeets('All email addresses are correctly identified');
})->with('email_extraction_cases');
```

### Sampling Evaluation

```php
use App\Ai\Agents\SalesCoach;

test('SalesCoach consistently provides quality feedback', function () {
    evaluate(SalesCoach::class)
        ->prompt('Customer: "Your price is too high." Rep: "I understand..."')
        ->samples(5, minimum: 4)
        ->assertMeets('The feedback is constructive and actionable')
        ->assertMeets('Professional tone', 80)
        ->assertDoesNotMeet('The response is dismissive or rude');
});

test('SalesCoach structured output is stable across samples', function () {
    $samples = evaluate(SalesCoach::class)
        ->prompt('Customer: "Your price is too high." Rep: "I understand..."')
        ->samples(3)
        ->run();

    $samples->each(function ($result) {
        expect($result)
            ->toHaveKeys(['feedback', 'score'])
            ->and($result['score'])->toBeBetween(1, 10);
    });
});
```

### Complete E2E Flow

```php
use App\Ai\Agents\SalesCoach;
use App\Models\User;
use Redberry\Evals\EvalCase;
use Redberry\Evals\Rubrics\ProfessionalTone;
use Redberry\Evals\Rubrics\ActionableAdvice;

describe('SalesCoach Agent', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });
    
    test('analyzes transcripts and provides scores', function () {
        $result = evaluate(SalesCoach::class, ['user' => $this->user])
            ->prompt('Customer: "Your price is too high." Rep: "I understand..."')
            ->run();
        
        // Verify structured output
        expect($result)
            ->toHaveKeys(['feedback', 'score']);

        expect($result['score'])->toBeBetween(1, 10);
    });
    
    test('provides constructive feedback', function () {
        evaluate(SalesCoach::class, ['user' => $this->user])
            ->prompt('[Sales call transcript here]')
            ->assertMeets(new ProfessionalTone)
            ->assertMeets(new ActionableAdvice)
            ->assertMeets('Feedback references specific moments from the call');
    });
    
    it('handles various scenarios', function (EvalCase $case) {
        evaluate(SalesCoach::class, ['user' => $this->user])
            ->withCase($case)
            ->assertMeets($case->expected)
            ->assertMeets(new ProfessionalTone);
    })->with([
        'objection handling' => fn () => EvalCase::make()
            ->prompt('Customer raised a pricing objection')
            ->expected('Provides techniques for handling price objections'),
            
        'closing techniques' => fn () => EvalCase::make()
            ->prompt('Rep failed to close the deal')
            ->expected('Suggests specific closing techniques'),
    ]);
});
```

---

## Summary

| Feature | API |
|---------|-----|
| **Entry Point** | `evaluate(Agent::class)` |
| **Prompting** | `->prompt('...', provider:, model:, timeout:, attachments:)` |
| **Attachments** | `->prompt(..., attachments: [...])` or `->attachments([...])` |
| **Config Override** | `->prompt(..., provider:, model:)` or `->provider(...)`, `->model(...)` |
| **Deterministic** | `->assertContains()`, `->assertLength*()`, etc. |
| **LLM Judge** | `->assertMeets('...')`, `->assertDoesNotMeet('...')`, `->assertSimilarTo('...')`, `->assertPasses()` |
| **Tools** | `->assertToolUsed('name'\|Tool::class, array\|fn)`, `->assertToolNotUsed()`, `->assertToolUseSequence()`, `->assertToolUsedTimes()`, `->assertToolUsedAtLeast()`, `->assertToolUsedAtMost()` |
| **Structured** | `->assertHasKey()`, `->assertHasKeys()`, `->assertHasProperty()`, `->assertHasProperties()`, `->assertMatchesArray()` (or `->run()` + PEST's `expect()`) |
| **Datasets** | `EvalCase::make()`, `EvalCase::fromJson()`, `EvalCase::fromXml()`, `EvalCase::fromDirectory()` |
| **Sampling** | `->samples(5)` / `->repeat(5)`, `->samples(5, minimum: 4)` / `->repeat(5, minimum: 4)` |
| **Custom Judges** | `Rubric` classes, `Judge` interface, `->assertPasses()` |
| **BDD-Style Aliases** | `->whenPrompted()`, `->toMeet()`, `->toBeSimilarTo()`, `->toBeSimilar()`, `->toBe()` |

