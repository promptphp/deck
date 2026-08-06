# Upgrade Guide

## Upgrading to `v0.4.6`

`v0.4.6` is not a breaking release, but two changes need your attention if you use database tracking.

### Tracking now defaults to off

Tracking previously defaulted to **on** whenever `APP_DEBUG` was false, which meant a production deploy would enable it before its tables existed — and every prompt load then threw `no such table: prompt_versions`. It now defaults to off:

```php
'enabled' => env('DECK_TRACKING_ENABLED', false),
```

**If you published `config/deck.php`**, your file is unchanged and so is your behaviour. Nothing to do.

**If you did not publish it and you rely on tracking**, it will switch off silently on upgrade. Turn it back on explicitly:

```dotenv
DECK_TRACKING_ENABLED=true
```

Deck no longer fails when tracking is enabled without its tables. It falls back to `metadata.json` and logs a warning once, so check your logs if tracking data stops appearing.

### A new migration, if you use tracking

`activate()` now records the active version in `prompt_versions` — previously it only ever ran an `UPDATE` that matched no rows, so the table was never populated. Inserting requires `user_prompt` to be nullable:

```bash
php artisan vendor:publish --tag=deck-migrations
php artisan migrate
```

Skip this if you do not use tracking.

### Activation precedence changed in practice

Because the table is now genuinely populated, the documented rule that the database takes precedence over `metadata.json` starts to have an effect. After the first `Deck::activate()` in an environment, editing `active_version` in `metadata.json` and deploying will no longer change which version is served. Activation is environment state; the file is the bootstrap default. To return a prompt to file-based control, delete its rows from `prompt_versions`.

### Prompt names are validated

Names may contain letters, numbers, dots, dashes, and underscores, and may not begin with a dot. Anything else throws `InvalidPromptNameException`.

**If you organised prompts into subdirectories**, such as `support/reply`, this is a breaking change: those names no longer load. `make:prompt` now refuses to create them too, rather than writing a prompt the package cannot read back.

Nested prompts never worked properly. `prompt:list` scans only the top level, so `support/reply` was listed as a broken `support` entry at `v0` and never as itself. Flatten the names to restore them:

```bash
mv resources/prompts/support/reply resources/prompts/support-reply
```

Then update any `Deck::get('support/reply')` call to `Deck::get('support-reply')`.

---

## Upgrading from Prompt Deck `v0.3.x` to Deck `v0.4.0`

- [Update Composer](#update-composer)
- [Update namespaces](#update-namespaces)
- [Update Laravel AI SDK trait imports](#update-laravel-ai-sdk-trait-imports)
- [Update config publishing](#update-config-publishing)
- [Update environment variables](#update-environment-variables)
- [Clear Laravel caches](#clear-laravel-caches)
- [Database notes](#database-notes)

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
