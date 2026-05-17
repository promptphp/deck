# Upgrade Guide

- [Update Composer](#update-composer)
- [Update namespaces](#update-namespaces)
- [Update Laravel AI SDK trait imports](#update-laravel-ai-sdk-trait-imports)
- [Update config publishing](#update-config-publishing)
- [Update environment variables](#update-environment-variables)
- [Clear Laravel caches](#clear-laravel-caches)
- [Database notes](#database-notes)

## Upgrading from Prompt Deck `v0.3.x` to Deck `v0.4.0`

Deck `v0.4.0` is a breaking release.

The package has moved from `veeqtoh/prompt-deck` to `promptphp/deck`, and the PHP namespace has changed from `Veeqtoh\PromptDeck` to `PromptPHP\Deck`.

## 1. Update Composer

Remove the old package:

```bash
composer remove veeqtoh/prompt-deck
```

Install the new package:

```bash
composer require promptphp/deck:^0.4
```

## 2. Update namespaces

Replace:

```php
Veeqtoh\PromptDeck
```

with:

```php
PromptPHP\Deck
```

Common replacements:

```php
use Veeqtoh\PromptDeck\Facades\PromptDeck;
```

becomes:

```php
use PromptPHP\Deck\Facades\Deck;
```

And usage changes from:

```php
$prompt = PromptDeck::get('order-summary');
```

to:

```php
$prompt = Deck::get('order-summary');
```

## 3. Update Laravel AI SDK trait imports

Replace: 

```php
use Veeqtoh\PromptDeck\Concerns\HasPromptTemplate;
```

with:

```php
use PromptPHP\Deck\Concerns\HasPromptTemplate;
```

## 4. Update config publishing

>> [!IMPORTANT]
> The config file is now changed from **prompt-deck.php** to **deck.php**.

If you previously published the config file, simply republish the config:

```bash
php artisan vendor:publish --tag=deck-config
```

or rename from

```bash
config/prompt-deck.php
```

to:

```bash
config/deck.php
```

## 5. Update environment variables

Replace old `PROMPTDECK_*` variables with `DECK_*`.

```txt
PROMPTDECK_CACHE_ENABLED=true
PROMPTDECK_CACHE_STORE=file
PROMPTDECK_CACHE_TTL=3600
PROMPTDECK_CACHE_PREFIX=prompt-deck:
PROMPTDECK_TRACKING_ENABLED=true
PROMPTDECK_DB_CONNECTION=null
PROMPTDECK_SCAFFOLD_ON_MAKE_AGENT=true
```

becomes:

```txt
DECK_CACHE_ENABLED=true
DECK_CACHE_STORE=file
DECK_CACHE_TTL=3600
DECK_CACHE_PREFIX=deck:
DECK_TRACKING_ENABLED=true
DECK_DB_CONNECTION=null
DECK_SCAFFOLD_ON_MAKE_AGENT=true
```

## 6. Clear Laravel caches

```bash
php artisan optimize:clear
composer dump-autoload
```

## 7. Database notes

No database table migration is required for the rename.

The existing tracking tables remain:

```txt
prompt_versions
prompt_executions
```

These table names describe the domain concept, not the old package name, so they are intentionally unchanged.

### Summary of changes

| Old                         | New                   |
| --------------------------- | --------------------- |
| `veeqtoh/prompt-deck`       | `promptphp/deck`      |
| `Veeqtoh\PromptDeck`        | `PromptPHP\Deck`      |
| `PromptDeck` facade         | `Deck` facade         |
| `PromptDeckServiceProvider` | `DeckServiceProvider` |
| `config/prompt-deck.php`    | `config/deck.php`     |
| `config('prompt-deck.*')`   | `config('deck.*')`    |
| `PROMPTDECK_*` env vars     | `DECK_*` env vars     |
