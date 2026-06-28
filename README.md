# Pest Plugin Evals

A Pest plugin for evaluating LLM outputs. Test your Laravel AI agents with expressive, readable assertions.

- **Effortless** — Write evals like regular Pest tests. No boilerplate, no ceremony.
- **Laravel AI Native** — Direct integration with [Laravel AI SDK](https://laravel.com/docs/12.x/ai-sdk) Agent classes.
- **Magic Judges** — Pass a plain-English string and get LLM-based evaluation. No setup required.

```php
test('sales coach provides constructive feedback', function () {
    evaluate(SalesCoach::class)
        ->whenPrompted('The customer said "too expensive" and I hung up.')
        ->toMeet('The response should offer negotiation tactics')
        ->toMeet('The tone should be encouraging, not critical');
});
```

---

## What Is This?

Pest Plugin Evals lets you evaluate the quality of your AI agent outputs inside your Pest test suite. You write a prompt, run the agent, and assert that the output is good — using plain English criteria, deterministic checks, or both.

It works with [Laravel AI SDK](https://laravel.com/docs/12.x/ai-sdk) agents. If you've built an agent class that extends Laravel's `Agent` contract, this plugin can test it. You don't need to know anything about the AI SDK internals — just pass your agent class and a prompt.

**Requirements:** PHP 8.3+, Laravel with the AI SDK, Pest 4.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [The evaluate() Function](#the-evaluate-function)
- [Sending Prompts](#sending-prompts)
  - [Simple Prompt](#simple-prompt)
  - [Provider, Model & Timeout Overrides](#provider-model--timeout-overrides)
  - [Attachments](#attachments)
  - [Using EvalCase](#using-evalcase)
- [Assertions](#assertions)
  - [LLM-as-a-Judge Assertions](#llm-as-a-judge-assertions)
  - [Deterministic Assertions](#deterministic-assertions)
  - [Tool Assertions](#tool-assertions)
  - [Structured Output Assertions](#structured-output-assertions)
- [Sampling](#sampling)
- [Datasets](#datasets)
  - [Inline EvalCase](#inline-evalcase)
  - [JSON Datasets](#json-datasets)
  - [XML Datasets](#xml-datasets)
  - [Directory Auto-Discovery](#directory-auto-discovery)
- [Custom Judges](#custom-judges)
  - [Rubric Classes](#rubric-classes)
  - [Judge Classes](#judge-classes)
  - [Custom Judge Instructions](#custom-judge-instructions)
- [Configuration](#configuration)
- [CLI Output](#cli-output)
- [Running in CI/CD](#running-in-cicd)
- [Full Examples](#full-examples)
- [API Reference](#api-reference)

---

## Installation

```bash
composer require redberry/pest-plugin-evals --dev
```

The plugin auto-registers with Pest and Laravel via the service provider.

If you want to customize the default judge, output, or sampling settings, publish the config file:

```bash
php artisan vendor:publish --tag=evals-config
```

This will create `config/evals.php` in your application.

---

## Quick Start

Create a test file (e.g. `tests/Evals/PostWriterTest.php`) and write your first eval:

```php
use App\Ai\Agents\PostWriter;

test('PostWriter writes engaging content', function () {
    evaluate(PostWriter::class)
        ->whenPrompted('Write a blog post about Laravel')
        ->toMeet('The content is engaging and informative');
});
```

That's a full, working eval. Here's what happens when you run it:

1. The plugin resolves your `PostWriter` agent from Laravel's service container.
2. It sends the prompt `"Write a blog post about Laravel"` to the agent.
3. An LLM judge reads the agent's response and decides if it meets the criterion `"The content is engaging and informative"`.
4. Pest reports pass or fail.

You can mix LLM-based checks with classic deterministic ones in the same test:

```php
test('PostWriter writes engaging content about Laravel', function () {
    evaluate(PostWriter::class)
        ->whenPrompted('Write a blog post about Laravel')
        ->toMeet('The content is engaging and informative')
        ->assertContains('Laravel')
        ->assertLengthGreaterThan(200);
});
```

> **A note on syntax:** This guide uses BDD-style methods (`whenPrompted`, `toMeet`, `toBeSimilarTo`, etc.) as the primary syntax. Every BDD method has a traditional equivalent (`prompt`, `assertMeets`, `assertSimilarTo`, etc.). We'll point these out as we go.

---

## The `evaluate()` Function

Every eval starts with `evaluate()`. It accepts your agent in several forms:

```php
use App\Ai\Agents\SalesCoach;
use App\Models\User;

// Pass the class name — resolved via Laravel's container
evaluate(SalesCoach::class);

// Pass constructor arguments for the container to inject
evaluate(SalesCoach::class, ['user' => $user]);

// Pass an already-built instance
evaluate(new SalesCoach($user));

// Pass a closure that returns an agent
evaluate(fn () => SalesCoach::make(user: $user));
```

All four forms produce the same thing: an `EvalBuilder` that you chain prompts and assertions onto.

---

## Sending Prompts

### Simple Prompt

Use `whenPrompted()` to send a prompt to your agent:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('Analyze this sales call transcript...')
    ->toMeet('The feedback is constructive');
```

> `whenPrompted()` is a BDD alias for `prompt()`. They are interchangeable.

### Provider, Model & Timeout Overrides

You can override the agent's default provider, model, or timeout. Pass them as named parameters to `prompt()`:

```php
use Laravel\Ai\Enums\Lab;

evaluate(SalesCoach::class)
    ->prompt(
        'Analyze this transcript...',
        provider: Lab::Anthropic,
        model: 'claude-3-5-sonnet',
        timeout: 120,
    )
    ->toMeet('The feedback is constructive');
```

Or use separate fluent methods. These set defaults that `prompt()` parameters can override:

```php
evaluate(SalesCoach::class)
    ->provider(Lab::Anthropic)
    ->model('claude-3-5-sonnet')
    ->timeout(120)
    ->whenPrompted('Analyze this transcript...')
    ->toMeet('The feedback is constructive');
```

### Attachments

For agents that process files or images, pass attachments inline with `prompt()` or via a separate method:

```php
use Laravel\Ai\Files;

// Inline with prompt
evaluate(DocumentAnalyzer::class)
    ->prompt(
        'Summarize this document',
        attachments: [
            Files\Document::fromStorage('contracts/agreement.pdf'),
            Files\Image::fromStorage('screenshot.png'),
        ],
    )
    ->toMeet('Summary captures key contract terms');

// Or as a separate fluent method
evaluate(DocumentAnalyzer::class)
    ->attachments([
        Files\Document::fromStorage('contracts/agreement.pdf'),
    ])
    ->whenPrompted('Summarize this document')
    ->toMeet('Summary captures key contract terms');
```

### Using EvalCase

An `EvalCase` bundles a prompt, expected output, and attachments into one reusable object. Load it with `withCase()`:

```php
use Redberry\Evals\EvalCase;

$case = EvalCase::make()
    ->prompt('Kindly ask to contact us at hello@example.com')
    ->expected('Please, contact us at hello@example.com');

evaluate(SupportAgent::class)
    ->withCase($case)
    ->toMeet('Asks the user to contact at hello@example.com')
    ->toBeSimilarTo($case->expected);
```

Only `prompt` is required. `expected` and `attachments` are optional. You'll see much more about `EvalCase` in the [Datasets](#datasets) section.

---

## Assertions

Assertions check the agent's output. There are four kinds:

1. **LLM-as-a-Judge** — An LLM reads the output and judges its quality. Powerful but costs an API call.
2. **Deterministic** — Classic checks like "contains this string" or "shorter than 280 characters". Fast, free, no AI involved.
3. **Tool** — Checks which tools (e.g., web search, database lookup) the agent called and with what arguments.
4. **Structured Output** — Checks keys, values, and shape of array/object output from agents that return structured data.

You can mix all four kinds in a single test chain.

### LLM-as-a-Judge Assertions

This is the plugin's most powerful feature. You describe what "good" looks like in plain English, and an LLM (the "judge") decides if the agent's output meets that bar.

#### Pass/Fail Check

`toMeet()` sends your criterion to the judge and expects a pass:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('The customer said "too expensive" and I hung up.')
    ->toMeet('The response should offer negotiation tactics')
    ->toMeet('The tone should be encouraging, not critical');
```

> `toMeet()` is a BDD alias for `assertMeets()`. They are interchangeable.

#### Scored Check

Pass a threshold (0-100) as the second argument. The judge scores the output, and it must meet or exceed the threshold:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('Review this call transcript...')
    ->toMeet('The feedback is constructive and actionable', 80); // Must score >= 80
```

#### Negation

Check that output does **not** meet a criterion:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('Review this call transcript...')
    ->assertDoesNotMeet('The response contains profanity or insults');
```

There is no BDD alias for `assertDoesNotMeet()` — use it directly.

#### Similarity Check

Compare the agent's output against an expected value for semantic similarity. The judge scores how similar they are:

```php
evaluate(Summarizer::class)
    ->whenPrompted('Summarize this article...')
    ->toBeSimilarTo('Expected summary mentioning key points X, Y, and Z');
```

You can customize the similarity threshold (default is 80):

```php
->toBeSimilarTo('Expected summary...', threshold: 85)
```

> `toBeSimilarTo()` is a BDD alias for `assertSimilarTo()`.

If you set the expected value separately with `->expected()`, use `toBeSimilar()` (no argument):

```php
evaluate(Summarizer::class)
    ->expected('A concise summary mentioning key points X, Y, and Z')
    ->whenPrompted('Summarize this article...')
    ->toBeSimilar(); // Compares output against the expected value set above
```

> `toBeSimilar()` is a BDD alias for `assertSimilar()`.

#### Exact Match

`toBe()` does a deterministic exact comparison. It auto-detects the type:

```php
// String comparison
evaluate(Greeter::class)
    ->whenPrompted('Say hello to John')
    ->toBe('Hello, John!');

// Array comparison (for structured output agents)
evaluate(DataExtractor::class)
    ->whenPrompted('Extract: John Doe, john@example.com')
    ->toBe([
        'name'  => 'John Doe',
        'email' => 'john@example.com',
    ]);
```

> `toBe()` uses `assertEquals()` for strings and `assertMatchesArray()` for arrays under the hood.

#### Custom Judge Classes

Use `assertPasses()` to plug in your own `Judge` implementation (see [Custom Judges](#custom-judges)):

```php
evaluate(DataExtractor::class)
    ->withCase($case)
    ->assertPasses(new SimilarityJudge(threshold: 90));
```

There is no BDD alias for `assertPasses()`.

#### Judge Result Inspection

Use the `judge()` method to run a judge and get back a `JudgeResult` object with `passed`, `score`, and `reasoning`:

```php
$result = evaluate(SalesCoach::class)
    ->whenPrompted('Review this call transcript...')
    ->judge('Is the response helpful?');

$result->passed;     // bool
$result->score;      // int|null (0-100, only for scored judges)
$result->reasoning;  // string — the judge's explanation

expect($result->score)->toBeGreaterThan(80);
expect($result->passed)->toBeTrue();
```

#### Judge Provider Override

By default, judges use the provider and model from your `config/evals.php`. Override per-test with `judgeWith()`:

```php
use Laravel\Ai\Enums\Lab;

evaluate(SalesCoach::class)
    ->judgeWith(Lab::OpenAI, 'gpt-4o-mini')
    ->whenPrompted('Review this call...')
    ->toMeet('The feedback is constructive');
```

#### Custom Judge Instructions

Append extra instructions to the built-in judge prompt with `judgeInstructions()`:

```php
evaluate(SalesCoach::class)
    ->judgeInstructions('The agent is a sales coaching tool — evaluate from a sales training perspective.')
    ->whenPrompted('Review this call...')
    ->toMeet('Professional and actionable advice');
```

---

### Deterministic Assertions

These are classic PHP checks — no LLM involved. They are fast, free, and predictable. There are no BDD aliases for these methods, but they chain freely with BDD methods.

#### String Assertions

```php
evaluate(CopyWriter::class)
    ->whenPrompted('Write a tweet about Laravel')
    ->assertContains('Laravel')                       // Contains this string
    ->assertContains(['Laravel', 'PHP'])              // Contains ALL of these
    ->assertContainsAny(['Laravel', 'Symfony'])       // Contains at least one
    ->assertNotContains('bad word')                   // Does NOT contain
    ->assertMatches('/Laravel \d+/');                  // Matches regex
```

#### Length Assertions

```php
evaluate(CopyWriter::class)
    ->whenPrompted('Write a tweet about Laravel')
    ->assertLengthLessThan(280)
    ->assertLengthGreaterThan(10)
    ->assertLengthBetween(50, 280);   // Inclusive
```

#### JSON Assertions

```php
evaluate(ApiAgent::class)
    ->whenPrompted('Return user data as JSON')
    ->assertJson()                                          // Valid JSON
    ->assertJsonPath('user.name', 'Taylor')                 // Dot-notation path
    ->assertJsonStructure(['user' => ['name', 'email']]);   // Has this shape
```

#### Type Assertions

```php
evaluate(Agent::class)
    ->whenPrompted('...')
    ->assertString()      // Plain text (no structured output)
    ->assertNotEmpty();

evaluate(StructuredAgent::class)
    ->whenPrompted('...')
    ->assertArray();      // Has structured (array) output
```

#### Equality Assertions

```php
evaluate(Agent::class)
    ->whenPrompted('What is 2+2?')
    ->assertEquals('4');

evaluate(StructuredAgent::class)
    ->whenPrompted('...')
    ->assertMatchesArray([
        'name'  => 'John Doe',
        'email' => 'john@example.com',
    ]);
```

#### Mixing BDD and Deterministic

You can freely combine them:

```php
evaluate(CopyWriter::class)
    ->whenPrompted('Write a tweet about Laravel')
    ->toMeet('The tone is enthusiastic')       // LLM judge
    ->assertContains('Laravel')                // Deterministic
    ->assertLengthLessThan(280);               // Deterministic
```

---

### Tool Assertions

For agents that call tools (e.g., web search, database lookup). These check which tools were called and with what arguments.

Tools can be referenced by **class** (recommended, type-safe) or by **string name**:

```php
use App\Ai\Tools\WebSearch;

// By class (recommended)
evaluate(ResearchAgent::class)
    ->whenPrompted('Find Laravel 12 release notes')
    ->assertToolUsed(WebSearch::class);

// By string name
evaluate(ResearchAgent::class)
    ->whenPrompted('Find Laravel 12 release notes')
    ->assertToolUsed('web_search');
```

#### Checking Tool Arguments

Pass an array for exact argument matching, or a closure for flexible inspection:

```php
use Redberry\Evals\ToolInvocation;

// Exact argument match
->assertToolUsed(WebSearch::class, ['query' => 'Laravel 12'])

// Closure — inspect arguments freely
->assertToolUsed(WebSearch::class, function (ToolInvocation $tool) {
    return str_contains($tool->query, 'Laravel 12');
})
```

The closure receives a `ToolInvocation` object. You can access tool arguments directly as properties (e.g. `$tool->query`) thanks to magic `__get`. The assertion passes when **at least one** invocation satisfies the closure.

#### Asserting a Tool Was Not Used

```php
->assertToolNotUsed(DangerousTool::class)
```

#### Tool Call Sequence

Check that tools were called in a specific order (other tools may appear between them):

```php
use App\Ai\Tools\WebSearch;
use App\Ai\Tools\Summarize;

->assertToolUseSequence([WebSearch::class, Summarize::class])
```

#### Tool Call Counts

```php
->assertToolUsedTimes(WebSearch::class, 2)           // Exactly 2 times
->assertToolUsedAtLeast(WebSearch::class, 1)          // At least once
->assertToolUsedAtMost(WebSearch::class, 5)           // No more than 5
```

Count methods also accept an optional closure as the last argument — only invocations matching the closure are counted:

```php
->assertToolUsedAtLeast(WebSearch::class, 2, function (ToolInvocation $tool) {
    return str_contains($tool->query, 'Laravel');
})
```

#### ToolInvocation Properties

When inspecting tool calls via closures, the `ToolInvocation` object provides:

| Property | Type | Description |
|---|---|---|
| `$tool->toolName` | `string` | Tool name (e.g., `'web_search'`) |
| `$tool->toolClass` | `?string` | Tool FQCN (e.g., `WebSearch::class`) |
| `$tool->arguments` | `array` | All arguments the LLM passed to the tool |
| `$tool->result` | `mixed` | The return value from the tool |
| `$tool->query` | `mixed` | Magic access — shorthand for `$tool->arguments['query']` |

---

### Structured Output Assertions

For agents that return arrays or objects instead of plain text (agents implementing `HasStructuredOutput`).

The BDD method `toBe()` handles exact matching. For more granular checks, use the `assert*` methods:

#### Check Keys Exist

```php
evaluate(DataExtractor::class)
    ->whenPrompted('Extract user info from: John Doe, john@example.com')
    ->assertHasKey('name')                      // Key exists
    ->assertHasKey('address.city')              // Supports dot notation
    ->assertHasKey('name', 'John Doe')          // Key exists with this value
    ->assertHasKeys(['name', 'email']);          // Multiple keys exist
```

`assertHasProperty()` and `assertHasProperties()` are aliases for `assertHasKey()` and `assertHasKeys()`.

#### Partial Array Match

```php
->assertMatchesArray([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
])
```

This checks that the output contains at least these key-value pairs. Extra keys are allowed.

#### Using Pest's `expect()` Directly

Call `->run()` to get the raw `EvalResult` and use Pest's native expectations for anything not covered:

```php
$result = evaluate(DataExtractor::class)
    ->whenPrompted('Extract: John Doe, john@example.com')
    ->run();

// EvalResult implements ArrayAccess — access structured keys directly
$result['name'];         // 'John Doe'
$result['email'];        // 'john@example.com'

// Or use Pest expectations
expect($result['name'])->toBe('John Doe');
expect($result->text)->not->toBeEmpty();
```

The `EvalResult` object gives you full access to the agent's response:

| Property / Method | Type | Description |
|---|---|---|
| `$result->text` | `string` | The agent's text output |
| `$result->structured` | `?array` | Parsed structured output (null for text-only agents) |
| `$result->toolInvocations` | `Collection` | All tool calls the agent made |
| `$result->response` | `AgentResponse` | Raw response for escape-hatch access |
| `$result->isStructured()` | `bool` | Whether the agent returned structured output |
| `$result->toArray()` | `?array` | Get structured output as array (or null) |
| `$result['key']` | `mixed` | ArrayAccess — shorthand for `$result->structured['key']` |
| `(string) $result` | `string` | Stringable — casts to `$result->text` |

---

## Sampling

LLMs are non-deterministic — the same prompt can produce different outputs each time. A single lucky run doesn't prove your agent is reliable. **Sampling** runs the agent multiple times with the same input and checks every output, giving you confidence that performance is consistent.

### Basic Sampling

Chain `->samples()` to run the agent N times. All samples must pass every assertion:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('Review this sales call...')
    ->samples(5)
    ->toMeet('The feedback is constructive');
```

This runs the agent **5 times**. If even one sample fails, the test fails.

If you call `->samples()` without arguments, it uses `sampling.default_samples` and `sampling.default_minimum` from `config/evals.php`. Calling `->samples(1)` still opts into sampling mode, so `->run()` and `->judge()` return `SampleResults` consistently.

### Allowing Some Variance

LLMs aren't perfect. If you're OK with occasional misses, set a `minimum`:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('Review this sales call...')
    ->samples(5, minimum: 4) // At least 4 of 5 must pass
    ->toMeet('The feedback is constructive');
```

### `repeat()` Alias

`repeat()` is an alias for `samples()` — use whichever reads better:

```php
->repeat(5)
->repeat(5, minimum: 4)
```

### Sampling with Scored Assertions

Each sample is scored independently and must individually meet the threshold:

```php
evaluate(SalesCoach::class)
    ->whenPrompted('...')
    ->samples(5, minimum: 4)
    ->toMeet('Professional tone', 80); // At least 4 of 5 must score >= 80
```

### Sampling with Deterministic Assertions

Every assertion type works with sampling. Each sample is checked individually:

```php
evaluate(CopyWriter::class)
    ->whenPrompted('Write a tweet about Laravel')
    ->samples(3)
    ->assertContains('Laravel')         // All 3 must contain "Laravel"
    ->assertLengthLessThan(280)         // All 3 must be under 280 chars
    ->toMeet('The tone is enthusiastic');  // All 3 must pass
```

### Sampling with Tool Assertions

Tool assertions under sampling check each sample independently:

```php
evaluate(ResearchAgent::class)
    ->whenPrompted('Find information about Laravel 12')
    ->samples(3, minimum: 2)
    ->assertToolUsed(WebSearch::class)                   // At least 2 of 3 must use WebSearch
    ->assertToolUsedAtMost(WebSearch::class, 3);         // Each run uses it at most 3 times
```

### Accessing Sample Results

Call `->run()` with sampling to get a `SampleResults` collection:

```php
$samples = evaluate(DataExtractor::class)
    ->whenPrompted('Extract: John, john@example.com')
    ->samples(5)
    ->run();

$samples->count();          // 5
$samples->outputs();        // Collection of all EvalResult objects
$samples->first();          // First sample result
$samples->last();           // Last sample result
```

You can also judge the samples manually and inspect aggregate results:

```php
$samples = evaluate(SalesCoach::class)
    ->whenPrompted('...')
    ->samples(5)
    ->judge('Is the response helpful?');

$samples->passRate();       // e.g. 80.0 (4 of 5 passed)
$samples->averageScore();   // e.g. 82.0
$samples->passed();         // true/false based on minimum + threshold
$samples->judgeResults();   // Collection of individual JudgeResult objects

$samples->each(function (JudgeResult $result, int $index) {
    dump("Sample #{$index}: score={$result->score}, passed={$result->passed}");
});
```

### Sampling with Datasets

Sampling composes naturally with Pest datasets — each case runs N times:

```php
it('consistently extracts emails', function (EvalCase $case) {
    evaluate(EmailExtractor::class)
        ->withCase($case)
        ->samples(3)
        ->toMeet($case->expected);
})->with('email_cases');
```

---

## Datasets

When you have multiple test cases for the same agent, datasets keep things organized. You can define cases inline, load them from JSON or XML files, or auto-discover them from a directory.

### Inline EvalCase

Create cases with `EvalCase::make()`:

```php
use Redberry\Evals\EvalCase;

dataset('sales_scenarios', [
    'angry customer' => fn () => EvalCase::make()
        ->prompt('I want a refund NOW!')
        ->expected('Calm de-escalation response'),

    'confused customer' => fn () => EvalCase::make()
        ->prompt('How do I log in?')
        ->expected('Step-by-step instructions'),
]);

it('handles customer scenarios', function (EvalCase $case) {
    evaluate(SupportBot::class)
        ->withCase($case)
        ->toMeet($case->expected);
})->with('sales_scenarios');
```

Only `prompt` is required. `expected` and `attachments` are optional:

```php
// Prompt only
EvalCase::make()
    ->prompt('Write a haiku about PHP');

// With attachments
EvalCase::make()
    ->prompt('What are the key terms in this contract?')
    ->attachments([
        Files\Document::fromStorage('contracts/agreement.pdf'),
    ])
    ->expected('Contract summary with dates and parties');
```

### JSON Datasets

JSON datasets contain **one case per file**. Use the `.case.json` extension for auto-discovery.

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

Only `"prompt"` is required. `"expected"` can be a string, object, or omitted. `"attachments"` is optional:

```json
{
    "prompt": "What are the key terms?",
    "expected": "Key terms include payment schedule and termination clause",
    "attachments": [
        {
            "type": "document",
            "source": "storage",
            "path": "evals/contracts/agreement.pdf"
        }
    ]
}
```

Attachment types: `"document"` or `"image"`. Source types: `"storage"` (Laravel storage disk) or `"path"` (absolute filesystem path).

Load a single JSON file:

```php
dataset('data_extraction', [
    'contact' => fn () => EvalCase::fromJson('evals/data-extractor/contact-info.case.json'),
    'address' => fn () => EvalCase::fromJson('evals/data-extractor/address-info.case.json'),
]);
```

### XML Datasets

XML datasets support **multiple cases per file** using an `<evalset>` container. Use the `.case.xml` extension.

```xml
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
    <case name="open-ended">
        <prompt>What is your return policy?</prompt>
    </case>
</evalset>
```

When `<expected>` contains child elements, it's deserialized as an associative array (for structured output):

```xml
<case name="contact-info">
    <prompt>Extract data from: John Doe, john@example.com</prompt>
    <expected>
        <name>John Doe</name>
        <email>john@example.com</email>
    </expected>
</case>
```

XML cases can also have attachments:

```xml
<case name="agreement-review">
    <prompt>What are the key terms?</prompt>
    <expected>Key terms include payment schedule</expected>
    <attachments>
        <attachment type="document" source="storage" path="evals/contracts/agreement.pdf" />
    </attachments>
</case>
```

Load cases from an XML file:

```php
dataset('customer_support', fn () => EvalCase::fromXml('evals/scenarios/customer-support.case.xml'));
// Returns cases keyed by name: 'refund-request' => EvalCase, 'complaint' => EvalCase, ...
```

### Directory Auto-Discovery

`EvalCase::fromDirectory()` scans a directory for all `*.case.json` and `*.case.xml` files and returns them as a keyed array:

```php
dataset('all_cases', fn () => EvalCase::fromDirectory('evals/data-extractor'));
// Discovers contact-info.case.json, address-info.case.json, edge-cases.case.xml, etc.
```

### Recommended Directory Structure

```
tests/
└── evals/
    ├── data-extractor/
    │   ├── contact-info.case.json
    │   ├── address-info.case.json
    │   └── edge-cases.case.xml
    ├── support-bot/
    │   ├── refund-request.case.json
    │   └── common-questions.case.xml
    └── contract-analyzer/
        ├── agreements.case.xml
        └── fixtures/
            └── contract.pdf
```

### Loading Methods Summary

| Method | Format | Cases per File | File Pattern |
|---|---|---|---|
| `EvalCase::fromJson($path)` | JSON | 1 | Any `.json` |
| `EvalCase::fromXml($path)` | XML | Multiple | Any `.xml` |
| `EvalCase::fromDirectory($dir)` | Both | Auto-discovery | `*.case.json`, `*.case.xml` |

---

## Custom Judges

For simple criteria, a plain string works fine: `->toMeet('The tone is professional')`. When you need reusable, structured evaluation logic, create a Rubric or Judge class.

### Rubric Classes

A Rubric defines evaluation criteria as a reusable class. Extend `Redberry\Evals\Contracts\Rubric` and implement `description()`:

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

    // Optional: return a 0-100 score instead of binary pass/fail
    public function scored(): bool
    {
        return true;
    }
}
```

Use it with `toMeet()` (or `assertMeets()`):

```php
evaluate(SalesCoach::class)
    ->whenPrompted('Review this call...')
    ->toMeet(new ProfessionalTone)
    ->toMeet(new ActionableAdvice);
```

### Judge Classes

For complete control over evaluation logic, implement the `Redberry\Evals\Contracts\Judge` interface. The `evaluate()` method receives an `EvalContext` and must return a `JudgeResult`:

```php
namespace App\Evals\Judges;

use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\EvalContext;
use Redberry\Evals\JudgeResult;

class CustomSimilarityJudge implements Judge
{
    public function __construct(
        private float $threshold = 80
    ) {}

    public function evaluate(EvalContext $context): JudgeResult
    {
        $input    = $context->input;     // The prompt sent to the agent
        $actual   = $context->output;    // The agent's text response
        $expected = $context->expected;  // The expected output (if set)
        $result   = $context->result;    // The full EvalResult object

        // Your evaluation logic here (embeddings, another LLM, etc.)
        $similarity = /* ... */ 85;

        return new JudgeResult(
            passed: $similarity >= $this->threshold,
            score: $similarity,
            reasoning: "Similarity score: {$similarity}",
        );
    }
}
```

Use it with `assertPasses()`:

```php
evaluate(DataExtractor::class)
    ->withCase($case)
    ->assertPasses(new CustomSimilarityJudge(threshold: 90));
```

### Custom Judge Instructions

For quick, one-off customization without creating a class, use `judgeInstructions()` to append extra context to the built-in LLM judge prompt:

```php
evaluate(SalesCoach::class)
    ->judgeInstructions('This agent is a sales coaching tool. Evaluate advice quality from a sales training perspective.')
    ->whenPrompted('Review this call transcript...')
    ->toMeet('Professional and actionable advice');
```

---

## Configuration

### Config File

Publish and edit `config/evals.php`:

```php
return [
    'judge' => [
        'provider' => env('EVALS_JUDGE_PROVIDER', 'openai'),
        'model' => env('EVALS_JUDGE_MODEL', 'gpt-4o-mini'),
        'default_threshold' => 80,
    ],

    'output' => [
        'verbose' => env('EVALS_VERBOSE', false),
        'show_reasoning' => env('EVALS_SHOW_REASONING', true),
    ],

    'sampling' => [
        'default_samples' => env('EVALS_DEFAULT_SAMPLES', 1),
        'default_minimum' => null, // null = all must pass
    ],
];
```

| Key | What It Controls |
|---|---|
| `judge.provider` | Which AI provider the judge uses (e.g., `openai`, `anthropic`) |
| `judge.model` | Which model the judge uses (e.g., `gpt-4o-mini`) |
| `judge.default_threshold` | Default score threshold for scored assertions |
| `output.verbose` | Enable detailed output after each test |
| `output.show_reasoning` | Include the judge's reasoning in verbose output |
| `sampling.default_samples` | Default number of samples when `->samples()` is called |
| `sampling.default_minimum` | Default minimum passing samples (`null` = all) |

### Environment Variables

Set these in `.env.testing`:

```bash
EVALS_JUDGE_PROVIDER=openai
EVALS_JUDGE_MODEL=gpt-4o-mini
EVALS_VERBOSE=true
EVALS_DEFAULT_SAMPLES=1
```

### Per-Test Overrides

Override the agent's provider/model via `prompt()` or fluent methods (see [Sending Prompts](#sending-prompts)). Override the judge's provider/model with `judgeWith()`:

```php
use Laravel\Ai\Enums\Lab;

evaluate(SalesCoach::class)
    ->judgeWith(Lab::OpenAI, 'gpt-4o-mini')
    ->whenPrompted('...')
    ->toMeet('...');
```

---

## CLI Output

### Standard Output

Evals integrate with Pest's standard output:

```
   PASS  Tests\Evals\SalesCoachTest > provides constructive feedback
   PASS  Tests\Evals\SalesCoachTest > handles rejection gracefully
   FAIL  Tests\Evals\SalesCoachTest > maintains professional tone

  Tests:    2 passed, 1 failed
  Duration: 3.42s
```

### Verbose Output

Enable verbose mode to see input, output, judge reasoning, and scores for every assertion. Turn it on with the `--evals-verbose` CLI flag or by setting `EVALS_VERBOSE=true`:

```bash
pest --evals-verbose
```

Verbose output for a failed test looks like:

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
   aggressive tone ('stop bothering us'), which is unprofessional."

  Score: 20 / 100

  ─────────────────────────────────────────────────────────────────────
```

### Sampling Output

When using `->samples()`, verbose mode shows per-sample results:

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

## Running in CI/CD

Evals make real API calls, which means they are slow, cost money, and require API keys. You'll usually want to skip them in CI pipelines and run them manually or on a schedule instead.

### Option 1: Pest Groups (Recommended)

Assign your evals to a Pest group, then exclude that group in CI.

Tag your eval tests with the `evals` group:

```php
test('PostWriter writes engaging content', function () {
    evaluate(PostWriter::class)
        ->whenPrompted('Write a blog post about Laravel')
        ->toMeet('The content is engaging and informative');
})->group('evals');
```

You can tag an entire file at once by adding this at the top:

```php
uses()->group('evals');
```

Then exclude the group in your CI pipeline:

```bash
pest --exclude-group=evals
```

Or add a dedicated composer script in `composer.json`:

```json
{
    "scripts": {
        "test": "pest --exclude-group=evals",
        "test:evals": "pest --group=evals"
    }
}
```

Now `composer test` skips evals, and `composer test:evals` runs only evals.

### Option 2: `skipOnCi()`

If you prefer not to manage groups, use Pest's built-in `skipOnCi()` method on individual tests:

```php
test('PostWriter writes engaging content', function () {
    evaluate(PostWriter::class)
        ->whenPrompted('Write a blog post about Laravel')
        ->toMeet('The content is engaging and informative');
})->skipOnCi();
```

This skips the test whenever the `CI` environment variable is set (which GitHub Actions, GitLab CI, and most CI providers set automatically).

---

## Full Examples

### Basic Agent Evaluation

```php
use App\Ai\Agents\BlogWriter;

test('BlogWriter creates engaging content', function () {
    evaluate(BlogWriter::class)
        ->whenPrompted('Write a blog post about PHP 8.4 features')
        ->toMeet('The content explains at least 3 new features')
        ->toMeet('The writing style is engaging and accessible')
        ->assertContains('PHP')
        ->assertLengthGreaterThan(500)
        ->assertDoesNotMeet('Contains factual errors about PHP');
});
```

### Structured Output Agent

```php
use App\Ai\Agents\DataExtractor;

test('DataExtractor parses contact information', function () {
    evaluate(DataExtractor::class)
        ->whenPrompted('John Smith, CEO at Acme Corp. Email: john@acme.com')
        ->toBe([
            'name'    => 'John Smith',
            'title'   => 'CEO',
            'company' => 'Acme Corp',
            'email'   => 'john@acme.com',
        ]);
});

test('DataExtractor returns expected keys', function () {
    evaluate(DataExtractor::class)
        ->whenPrompted('John Smith, CEO at Acme Corp. Email: john@acme.com')
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
use Redberry\Evals\ToolInvocation;

test('ResearchAssistant uses web search appropriately', function () {
    evaluate(ResearchAssistant::class)
        ->whenPrompted('What are the latest Laravel 12 features?')
        ->assertToolUsed(WebSearch::class)
        ->assertToolUsed(WebSearch::class, function (ToolInvocation $tool) {
            return str_contains($tool->query, 'Laravel 12');
        })
        ->assertToolUsedAtMost(WebSearch::class, 3)
        ->toMeet('Response cites sources from the web search')
        ->toMeet('Information is current and accurate');
});
```

### Dataset-Driven Evaluation with Sampling

```php
use Redberry\Evals\EvalCase;

dataset('email_extraction_cases', [
    'simple' => fn () => EvalCase::make()
        ->prompt('Extract: contact@example.com')
        ->expected(['email' => 'contact@example.com']),

    'with context' => fn () => EvalCase::make()
        ->prompt('Contact us at hello@world.com for support')
        ->expected(['email' => 'hello@world.com', 'context' => 'support']),
]);

it('reliably extracts emails', function (EvalCase $case) {
    $result = evaluate(EmailExtractor::class)
        ->withCase($case)
        ->samples(3)
        ->run();

    expect($result->first())->toMatchArray($case->expected);
})->with('email_extraction_cases');
```

### Complete Test Suite with Rubrics and Datasets

```php
use App\Ai\Agents\SalesCoach;
use App\Evals\Rubrics\ProfessionalTone;
use App\Evals\Rubrics\ActionableAdvice;
use App\Models\User;
use Redberry\Evals\EvalCase;

describe('SalesCoach Agent', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    test('analyzes transcripts and provides scores', function () {
        $result = evaluate(SalesCoach::class, ['user' => $this->user])
            ->whenPrompted('Customer: "Your price is too high." Rep: "I understand..."')
            ->run();

        expect($result)
            ->toHaveKeys(['feedback', 'score']);

        expect($result['score'])->toBeBetween(1, 10);
    });

    test('provides constructive feedback', function () {
        evaluate(SalesCoach::class, ['user' => $this->user])
            ->whenPrompted('[Sales call transcript here]')
            ->toMeet(new ProfessionalTone)
            ->toMeet(new ActionableAdvice)
            ->toMeet('Feedback references specific moments from the call');
    });

    test('consistently delivers quality feedback', function () {
        evaluate(SalesCoach::class, ['user' => $this->user])
            ->whenPrompted('Customer: "Your price is too high." Rep: "I understand..."')
            ->samples(5, minimum: 4)
            ->toMeet('The feedback is constructive and actionable')
            ->toMeet('Professional tone', 80)
            ->assertDoesNotMeet('The response is dismissive or rude');
    });

    it('handles various scenarios', function (EvalCase $case) {
        evaluate(SalesCoach::class, ['user' => $this->user])
            ->withCase($case)
            ->toMeet($case->expected)
            ->toMeet(new ProfessionalTone);
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

## API Reference

### Entry Point

| Method | Description |
|---|---|
| `evaluate($agent, $constructorArgs)` | Create an evaluation builder. Accepts class string, instance, or closure. |

### Prompting & Configuration

| Method | BDD Alias | Description |
|---|---|---|
| `prompt($prompt, ...)` | `whenPrompted($prompt)` | Send a prompt (with optional provider, model, timeout, attachments) |
| `withCase(EvalCase)` | — | Load prompt, expected, and attachments from an EvalCase |
| `expected($value)` | — | Set expected output for comparison |
| `attachments($files)` | — | Set file attachments |
| `provider($provider)` | — | Override agent provider |
| `model($model)` | — | Override agent model |
| `timeout($seconds)` | — | Override agent timeout |

### LLM-as-a-Judge Assertions

| Method | BDD Alias | Description |
|---|---|---|
| `assertMeets($criterion, $threshold?)` | `toMeet(...)` | Output meets criterion (pass/fail or scored) |
| `assertDoesNotMeet($criterion)` | — | Output does NOT meet criterion |
| `assertSimilarTo($expected, $threshold?)` | `toBeSimilarTo(...)` | Output is semantically similar to expected |
| `assertSimilar($threshold?)` | `toBeSimilar(...)` | Similar to pre-set `->expected()` value |
| `assertEquals($value)` / `assertMatchesArray($array)` | `toBe(...)` | Exact match (auto-detects string vs array) |
| `assertPasses(Judge)` | — | Output passes a custom Judge |
| `judge($criterion, $rubric?)` | — | Run judge and return `JudgeResult` |

### Deterministic Assertions

| Method | Description |
|---|---|
| `assertContains($needle)` | Contains string (or all strings if array) |
| `assertContainsAny($needles)` | Contains at least one string |
| `assertNotContains($needle)` | Does NOT contain string |
| `assertMatches($regex)` | Matches regex pattern |
| `assertLengthLessThan($max)` | Length under max |
| `assertLengthGreaterThan($min)` | Length over min |
| `assertLengthBetween($min, $max)` | Length in range (inclusive) |
| `assertJson()` | Valid JSON |
| `assertJsonPath($path, $expected)` | JSON path has value |
| `assertJsonStructure($structure)` | Matches JSON structure |
| `assertString()` | Plain string (no structured output) |
| `assertArray()` | Has structured output |
| `assertNotEmpty()` | Not empty |
| `assertEquals($expected)` | Exact equality |
| `assertMatchesArray($expected)` | Structured output subset match |

### Tool Assertions

| Method | Description |
|---|---|
| `assertToolUsed($tool, $constraint?)` | Tool was used (with optional args array or closure) |
| `assertToolNotUsed($tool)` | Tool was NOT used |
| `assertToolUseSequence($tools)` | Tools called in this order |
| `assertToolUsedTimes($tool, $count, $closure?)` | Used exactly N times |
| `assertToolUsedAtLeast($tool, $count, $closure?)` | Used at least N times |
| `assertToolUsedAtMost($tool, $count, $closure?)` | Used at most N times |

### Structured Output Assertions

| Method | Description |
|---|---|
| `assertHasKey($key, $value?)` | Key exists (dot notation), optionally with value |
| `assertHasKeys($keys)` | Multiple keys exist |
| `assertHasProperty($key, $value?)` | Alias for `assertHasKey()` |
| `assertHasProperties($properties)` | Alias for `assertHasKeys()` |

### Sampling

| Method | Alias | Description |
|---|---|---|
| `samples($count, $minimum?)` | `repeat(...)` | Run agent N times, require minimum passes |

### SampleResults (returned by `->run()` or `->judge()` when sampling)

| Method | Description |
|---|---|
| `count()` | Number of samples |
| `outputs()` | Collection of all `EvalResult` objects |
| `first()` | First sample result |
| `last()` | Last sample result |
| `minimum()` | Minimum required passes (null = all) |
| `each($callback)` | Iterate with callback |
| `judgeResults()` | Collection of `JudgeResult` objects (after `->judge()`) |
| `passRate()` | Pass rate as percentage (0-100) |
| `averageScore()` | Average score across all judge results (null if binary) |
| `passed()` | Whether enough samples passed the minimum threshold |

### Datasets

| Method | Description |
|---|---|
| `EvalCase::make()` | Create a new empty case |
| `EvalCase::fromJson($path)` | Load one case from a JSON file |
| `EvalCase::fromXml($path)` | Load multiple cases from an XML file |
| `EvalCase::fromDirectory($dir)` | Auto-discover `*.case.json` and `*.case.xml` files |

### Judge Configuration

| Method | Description |
|---|---|
| `judgeWith($provider, $model?)` | Override judge provider/model for this test |
| `judgeInstructions($text)` | Append custom instructions to the judge prompt |

### Execution

| Method | Description |
|---|---|
| `run()` | Execute the agent and return `EvalResult` (or `SampleResults` when sampling) |
