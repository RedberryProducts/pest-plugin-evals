# Pest Plugin Development Guide

A quick reference for creating Pest plugins. Full documentation: https://pestphp.com/docs/creating-plugins

## Getting Started

1. Use the [pest-plugin-template](https://github.com/pestphp/pest-plugin-template) as a starting point
2. Update `composer.json` with your plugin's name and description
3. Ensure your `composer.json` autoloads the `Autoload.php` file:

```json
"autoload": {
    "psr-4": {
        "YourNamespace\\PluginName\\": "src/"
    },
    "files": ["src/Autoload.php"]
}
```

## Adding Test Methods (via Traits)

Create a trait with your custom methods:

```php
namespace YourNamespace\PluginName;

trait MyPluginTrait
{
    public function myMethod(): self
    {
        // Your logic here
        return $this;
    }
}
```

Register the trait in `src/Autoload.php`:

```php
use YourNamespace\PluginName\MyPluginTrait;
use Pest\Plugin;

Plugin::uses(MyPluginTrait::class);
```

Users can now call `$this->myMethod()` in their tests.

## Adding Functions

Define namespaced functions in `src/Autoload.php`:

```php
namespace YourNamespace\PluginName;

use PHPUnit\Framework\TestCase;

function myFunction(): TestCase
{
    return test(); // Returns current test instance ($this)
}
```

Users import and use them:

```php
use function YourNamespace\PluginName\myFunction;

test('example', function () {
    myFunction();
});
```

## Adding Custom Expectations

Add custom expectations in `src/Autoload.php`:

```php
expect()->extend('toBeAwesome', function () {
    return $this->toBe('awesome');
});
```

## Adding Arch Presets

Define architecture presets in `src/Autoload.php`:

```php
pest()->preset('my-preset', function () {
    return [
        expect('App\Models')->toExtend('Illuminate\Database\Eloquent\Model'),
        expect('App\Http\Controllers')->toHaveSuffix('Controller'),
    ];
});
```

Access user namespaces via the callback argument:

```php
pest()->preset('strict', function (array $userNamespaces) {
    // $userNamespaces contains PSR-4 namespaces, e.g., ['App\\']
});
```

## Publishing

1. Publish your plugin to [Packagist](https://packagist.org/)
2. Users install via: `composer require --dev your-vendor/pest-plugin-name`
