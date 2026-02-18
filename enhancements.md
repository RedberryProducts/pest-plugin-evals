# Future Enhancements

Ideas for future versions of the PEST Evals Plugin.

---

## Cost & Token Assertions

Add assertions to help developers monitor efficiency and keep costs under control:

```php
assess(SalesCoach::class)
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
assess(SalesCoach::class)
    ->prompt('Analyze this transcript...')
    ->cache()                            // Re-use cached response if prompt hasn't changed
    ->assertMeets('Constructive feedback');
```

---

## Failover Assertions

Assert that the primary provider failed and a fallback was used — useful for testing resilience configurations:

```php
assess(SalesCoach::class)
    ->prompt('Analyze this transcript...')
    ->assertFallbackUsed()                       // Any fallback was triggered
    ->assertFallbackUsed(Lab::Anthropic);        // Specific fallback provider was used
```
