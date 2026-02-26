# Contributing

Contributions are welcome, and are accepted via pull requests.
Please review these guidelines before submitting any pull requests.

## Process

1. Fork the project
1. Create a new branch
1. Code, test, commit and push
1. Open a pull request detailing your changes. Make sure to follow the [template](.github/PULL_REQUEST_TEMPLATE.md)

## Guidelines

* Please ensure the coding style running `composer lint`.
* Send a coherent commit history, making sure each individual commit in your pull request is meaningful.
* You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
* Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Lint your code:

```bash
composer lint
```

## Tests

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

### Usage Tests (Requires API Key)

Usage tests cover all documented use cases. They test real API calls across every feature: core API, BDD syntax, prompting, assertions (deterministic, LLM judge, tool, structured output), sampling, datasets, custom judges, and full end-to-end examples.

```bash
composer test:usage
```

Uses the same `openai-api-key.php` setup as integration tests above.

### Run Everything

```bash
composer test && composer test:integration && composer test:usage
```

### Individual Commands

| Command | Description |
|---|---|
| `composer test:unit` | Unit tests only (no API key needed) |
| `composer test:integration` | Integration tests only (requires API key) |
| `composer test:usage` | Usage tests (requires API key) |
| `composer test:types` | PHPStan static analysis |
| `composer test:lint` | Pint code style check |
| `composer test` | Full CI/CD suite (lint + types + unit) |
