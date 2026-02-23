# Future Enhancements

Ideas for future versions of the PEST Evals Plugin.

---

## Cost & Token Assertions

Add assertions to help developers monitor efficiency and keep costs under control:

```php
evaluate(SalesCoach::class)
    ->prompt('Analyze this transcript...')
    ->assertCostLessThan(0.05)           // USD — fail if the call costs more
    ->assertTokenCountLessThan(1000)     // Total tokens (input + output)
    ->assertInputTokensLessThan(500)
    ->assertOutputTokensLessThan(500);
```

---

## Output Caching

Cache agent responses to avoid repeated LLM calls during iterative test development. Useful when refining assertions without changing the prompt or agent:

```php
evaluate(SalesCoach::class)
    ->prompt('Analyze this transcript...')
    ->cache()                            // Re-use cached response if prompt hasn't changed
    ->assertMeets('Constructive feedback');
```

---

## Failover Assertions

Assert that the primary provider failed and a fallback was used — useful for testing resilience configurations:

```php
evaluate(SalesCoach::class)
    ->prompt('Analyze this transcript...')
    ->assertFallbackUsed()                       // Any fallback was triggered
    ->assertFallbackUsed(Lab::Anthropic);        // Specific fallback provider was used
```

---

## Model & Provider Comparison

Run the same eval across multiple models or providers and compare results side-by-side — useful for benchmarking, migration decisions, and cost/quality trade-offs:

```php
evaluate(SalesCoach::class)
    ->prompt('Analyze this transcript...')
    ->compare([
        Lab::OpenAI   => 'gpt-4o',
        Lab::Anthropic => 'claude-sonnet-4-20250514',
        Lab::Gemini   => 'gemini-2.0-flash',
    ])
    ->assertAllMeet('The feedback is constructive');   // Every model must pass

// Access comparison results
$comparison = evaluate(SalesCoach::class)
    ->prompt('Analyze this transcript...')
    ->compare([
        Lab::OpenAI    => 'gpt-4o',
        Lab::Anthropic => 'claude-sonnet-4-20250514',
    ])
    ->judge('Is the response helpful?');

$comparison->results();          // Keyed by provider — scores, pass/fail, reasoning
$comparison->winner();           // Provider with highest average score
$comparison->ranking();          // Ordered list from best to worst
$comparison->scoreDiff();        // Score delta between best and worst
```
