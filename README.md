<p align="center"><img src="/docs/logo/banner.svg" alt="Prompt Deck Logo"></p>

<p align="center">
  <a href="https://packagist.org/packages/promptphp/deck">
    <img src="https://img.shields.io/packagist/v/promptphp/deck?style=flat-square" alt="Latest Version on Packagist">
  </a>
  <a href="https://packagist.org/packages/promptphp/deck">
    <img src="https://img.shields.io/packagist/php-v/promptphp/deck?style=flat-square" alt="PHP from Packagist">
  </a>
  <a href="https://github.com/promptphp/deck/blob/master/LICENSE">
    <img src="https://img.shields.io/github/license/promptphp/deck?style=flat-square" alt="GitHub license">
  </a>
  <a href="https://packagist.org/packages/promptphp/deck">
    <img src="https://img.shields.io/packagist/dt/veeqtoh/prompt-deck?style=flat-square" alt="Total Downloads on Packagist">
  </a>
  <a href="https://laravel-news.com/prompt-deck-manage-ai-prompts-as-versioned-files-in-laravel/">
    <img src="https://img.shields.io/badge/Featured%20in%20Laravel%20News-F9322C?style=flat-square&logo=laravel&logoColor=white" alt="Featured in Laravel News">
  </a>
</p>

## Introduction

Deck, formerly Prompt Deck, provides AI prompt management for Laravel and PHP.

Organise your AI agent instructions as versioned files, compare prompt performance, and activate the right version across your app with variable interpolation, tracking, A/B testing, and Laravel AI SDK integration.

> [!IMPORTANT]
> Prompt Deck is now **Deck by PromptPHP**.
>
> From `v0.4.0`, the package moved from `veeqtoh/prompt-deck` to `promptphp/deck`, and the namespace changed from `Veeqtoh\PromptDeck` to `PromptPHP\Deck`.
>
> Upgrading from `v0.3.x`? See the [upgrade guide](UPGRADE.md).

## Quick Start

### Installation

```bash
composer require promptphp/deck
```

Publish the config and migrations

```bash
php artisan vendor:publish --provider="PromptPHP\Deck\Providers\DeckServiceProvider"

# Run migrations.
php artisan migrate
```

### Creating a Prompt

Use the Artisan command to create a versioned prompt

```bash
php artisan make:prompt order-summary
```

This creates the following structure

```txt
resources/prompts/order-summary/
├── metadata.json
├── v1/
│   ├── system.md
│   └── user.md
└── v2/
    ├── system.md
    └── user.md
```

Edit `resources/prompts/order-summary/v1/system.md` with your prompt content. Use `{{ $variable }}` syntax for dynamic values:

```markdown
You are a {{ $tone }} customer service agent.
Summarise the following order for the customer: {{ $order }}.
```

### Using a Prompt

Load and render prompts with the `Deck` facade

```php
use PromptPHP\Deck\Facades\Deck;

// Load the active version of a prompt.
$prompt = Deck::get('order-summary');

// Render a role with variables.
$prompt->system(['tone' => 'friendly', 'order' => $orderDetails]);
// "You are a friendly customer service agent. Summarise the following order..."

// Build a messages array ready for any chat-completion API.
$messages = $prompt->toMessages(['tone' => 'friendly', 'order' => $orderDetails]);
// [['role' => 'system', 'content' => '...']]
```

### Versioning

Create a new version of an existing prompt

```bash
php artisan make:prompt order-summary
# Automatically creates v2, v3, etc.
```

Activate a specific version

```bash
php artisan prompt:activate order-summary v2

# or

php artisan prompt:activate order-summary 2
```

Or load a specific version programmatically

```php
$prompt = Deck::get('order-summary', 'v2');
```

### Laravel AI SDK Integration

If you use the [Laravel AI SDK](https://laravel.com/docs/ai-sdk), add the `HasPromptTemplate` trait to your agents. This way, you do not need to define the `instructions()` method as it is provided automatically.

```php
use PromptPHP\Deck\Concerns\HasPromptTemplate;

class OrderAgent extends Agent
{
    use HasPromptTemplate;

    // instructions() and promptMessages() are provided automatically.
}
```

Running `make:agent` will also auto-scaffold a matching prompt directory.

For the complete guide, see the [full documentation](#documentation) below.

---

## Documentation

Full documentation can be found at [https://deck.promptphp.com/](https://deck.promptphp.com/) or the [docs](docs/) directory on GitHub.

## Contributing

Thank you for considering contributing to Deck by PromptPHP. Please open an issue or submit a pull request on [GitHub](https://github.com/promptphp/deck).

## Code of Conduct

We follow the Laravel [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct). We expect you to abide by these guidelines as well.

## Security Vulnerabilities

If you discover a security vulnerability within Deck by PromptPHP, please email Victor Ukam at [victorjohnukam@gmail.com](victorjohnukam@gmail.com). All security vulnerabilities will be addressed promptly.

## License

Deck by PromptPHP is open-sourced software licensed under the [MIT license](LICENSE).

## Support

This library is created by [Victor Ukam](https://victorukam.com) with contributions from the [Open Source Community](https://github.com/promptphp/deck/graphs/contributors). If you've found this package useful, please consider [sponsoring this project](https://github.com/sponsors/veeqtoh). It will go a long way to help with maintenance.
