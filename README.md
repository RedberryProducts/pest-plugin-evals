# Pest Plugin Evals

A Pest plugin for evaluating LLM outputs. Built on top of [Laravel AI](https://github.com/laravel/ai) and [Pest](https://pestphp.com).

## Testing

### Prerequisites

```bash
composer install
```

### Unit Tests (CI/CD Safe)

Runs all unit tests. No API keys required, safe for CI/CD pipelines.

```bash
composer test:unit
```

### Full Static Analysis + Unit Tests

Runs rector, pint, phpstan, and unit tests in sequence.

```bash
composer test
```

### Integration Tests (Requires API Key)

Integration tests make real API calls to OpenAI. They are **excluded from the default test suite** and must be run manually.

**Setup:**

1. Create `openai-api-key.php` in the project root (this file is gitignored):

```php
<?php

return 'sk-your-openai-api-key-here';
```

2. Run integration tests:

```bash
composer test:integration
```

If the key file is missing, all integration tests are automatically skipped.

### Run Everything

```bash
composer test && composer test:integration
```

### Individual Commands

| Command | Description |
|---|---|
| `composer test:unit` | Unit tests only (no API key needed) |
| `composer test:integration` | Integration tests only (requires API key) |
| `composer test:types` | PHPStan static analysis |
| `composer test:lint` | Pint code style check |
| `composer test` | Full CI/CD suite (lint + types + unit) |
