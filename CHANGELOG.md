# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Removed

## [0.4.6] - 2026-08-04

### Fixed

- Fixed tracking making prompt rendering fail. Tracking defaulted to on whenever `APP_DEBUG` was false, but its tables are published in a separate opt-in step, so the natural production install threw `no such table: prompt_versions` on the first `Deck::get()`. Caching gave no protection, because the active version is resolved before the cache is consulted. Tracking now defaults to off, and every database interaction degrades to `metadata.json` with a single logged warning rather than throwing. `Deck::track()` never throws at all — it runs after a completed, paid-for AI call.
- Fixed `Deck::activate()` never recording anything in `prompt_versions`. It only ever issued an `UPDATE`, which matched no rows because nothing inserted them, so the table stayed empty and the lookup in `getActiveVersion()` could fail but never succeed. It now upserts, with explicit timestamps, making runtime version switching work as documented.
- Fixed prompt names being interpolated into filesystem paths without validation. A name containing `..` or a directory separator could read files outside the configured prompts path. Names are now validated and `InvalidPromptNameException` is thrown otherwise.
- Fixed the version directory pattern matching far more than intended. Being unanchored and applied to the full path, `/v(\d+)$/` treated directories such as `rev2`, `dev3`, and `archive-v9` as versions 2, 3, and 9 — advertised by `prompt:list --all` and then failing to load. Both `PromptManager` and `make:prompt` now anchor the match against the directory name.
- Fixed `getActiveVersion()` returning the version column unordered and uncast, which picked arbitrarily between multiple active rows and could raise a `TypeError` on drivers that return integers as strings.

### Changed

- **Tracking now defaults to off.** If you published `config/deck.php` you are unaffected. If you did not and you rely on tracking, set `DECK_TRACKING_ENABLED=true`. See [UPGRADE.md](UPGRADE.md).
- `prompt_versions.user_prompt` is now nullable, so activation can be recorded without prompt content. Existing tracking installs must re-publish and run the migrations.
- Once a version has been activated with tracking enabled, the database takes precedence over `metadata.json`. Editing `active_version` in the file and deploying no longer changes what is served. Activation is environment state; the file is the bootstrap default.

### Removed

## [0.4.5] - 2026-08-03

### Added

- Added a `quality` workflow running the Pint formatting check on every push and pull request, and asserting that every release in `CHANGELOG.md` has a matching entry in the documentation site's changelog. Formatting was previously never checked in CI, and the two changelogs could drift silently.

### Changed

- `Deck::activate()` now accepts `string|int` versions, so `'v2'`, `'2'`, and `2` are all valid. Only `Deck::get()` was widened in `0.4.2`, despite the changelog describing both.
- The `tests` workflow now runs `composer test` rather than calling `vendor/bin/pest` directly, so CI and the documented contributor command cannot diverge.

### Fixed

- Fixed an unparseable version producing a message with an empty version number, such as `Version  for prompt [order-summary] does not exist.` Both `Deck::get()` and `Deck::activate()` now throw `InvalidVersionException` naming the offending value.
- Fixed the README prompt structure diagram, which showed a `user.md` that `make:prompt` does not create without `--user`, a second version that a single run does not create, and omitted the version-level `metadata.json` added in `0.4.4`.
- Fixed the prompt structure diagrams on the introduction, configuration, prompts, and make:prompt documentation pages, which all omitted the version-level `metadata.json`.
- Fixed the README describing new versions as activating automatically. Creating a version has not changed the active version since `0.4.4`.
- Fixed the documented `Deck::get()` signature, which described `?int` rather than `string|int|null`.

### Removed

## [0.4.4] - 2026-08-03

### Fixed

- Fixed migration publishing, which silently failed on case-sensitive filesystems. `DeckServiceProvider` pointed at `src/database/migrations` while the directory is `src/Database/migrations`, so `vendor:publish --tag=deck-migrations` reported success whilst failing on Linux and macOS case-sensitive volumes.
- Fixed `make:prompt` overwriting the `active_version` key by rewriting the prompt's root `metadata.json`. Creating a new version silently promoted it to active. The file is now merged, preserving `active_version`, the existing description, the original `created_at`, a populated `variables` list, and any keys added by hand.
- Fixed prompt metadata being unreachable. `make:prompt` wrote the name, description, and roles to the prompt's root `metadata.json`, but `PromptManager` only ever read the version-level `v{n}/metadata.json`, so `PromptTemplate::metadata()` was always empty and the `prompt:list` description column was always blank. `make:prompt` now writes version-level metadata, and metadata reads merge the prompt-level file with the version-level file, version keys winning. The `active_version` key is excluded from `metadata()`.
- Fixed `prompt:test --ver=v2` silently rendering the active version instead of the one requested. `(int) 'v2'` evaluated to a falsy `0`, so the command fell through to `active()` while reporting the wrong version number in its header. The command now uses the `ResolvesVersion` trait, accepts both `2` and `v2`, and fails with a clear message on unparseable input.
- Fixed the `PromptPHP\Deck\Database\Factories\` PSR-4 mapping pointing at the non-existent lowercase `src/database/factories/`.

- Fixed the README downloads badge reporting the deprecated `veeqtoh/prompt-deck` package instead of a combined figure.
- Fixed the docs landing page linking to the pre-rename `promptphp/prompt-deck` repository, and the README licence link pointing at a `master` branch that does not exist.

### Added

- `make:prompt` now prints how to activate the version it just created when a different version is live.
- Added a scheduled `downloads badge` workflow that publishes the combined Packagist download count for `promptphp/deck` and the deprecated `veeqtoh/prompt-deck` to a shields endpoint on the orphan `badges` branch.
- Added a changelog page to the documentation site, under a new Releases group, with an RSS feed at `/changelog/rss.xml`.

### Removed

- Removed the `PromptPHP\Deck\Database\Seeders\` autoload mapping, which pointed at a directory that does not exist.

## [0.4.3] - 2026-07-29

### Fixed

- Widened `sebastian/diff` constraint to allow `v8/v9` To allow pest 5.

### Changed

### Removed

## [0.4.2] - 2026-05-27

### Fixed

- Refactored `ActivatePromptCommand` and `PromptManager` to Use `ResolvesVersion` trait for version resolution in prompt retrieval and activation and support mixed types for version signature on `get` and `activate` methods.

## [0.4.1] - 2026-05-17

### Fixed

- Refactored `ActivatePromptCommand` and `PromptManager` to support prompt version activation in formats `1` or `v1`.

## [0.4.0] - 2026-05-17

### Added

### Changed

- Renamed the package from `veeqtoh/prompt-deck` to `promptphp/deck`.
- Renamed the PHP namespace from `Veeqtoh\PromptDeck` to `PromptPHP\Deck`.
- Renamed the public package identity from Prompt Deck to Deck by PromptPHP.
- Updated installation, usage, README, badges, documentation links, and package metadata to reflect the new PromptPHP organisation.

### Removed

- Removed the old `Veeqtoh\PromptDeck` public namespace.
- Removed old Prompt Deck naming from the main public API.

## [0.3.1] - 2026-05-17

### Added

- Mintlify documentation structure under the `docs` directory. [#5](https://github.com/promptphp/deck/pull/5)
- Documentation configuration, navigation, logo assets, favicon, and landing page content for the docs site.
- Dedicated documentation pages for installation, configuration, commands, prompt management, Laravel AI SDK integration, tracking, testing, and API reference.

### Changed

- Reworked the documentation from flat markdown files into organised Mintlify MDX pages.
- Updated documentation navigation and internal links for the new docs structure.
- Updated README logo/banner display and package badges. [#6](https://github.com/promptphp/deck/pull/6)
- Updated documentation copy and spelling consistency.

### Fixed

- Fixed stale documentation links and docs navigation paths.
- Fixed README badge markup and logo references.

## [0.2.1] - 2026-03-28

### Added

- This CHANGELOG file to document project updates.
- GitHub Actions workflow for automated testing.
- `.gitattributes` file to manage text file handling and export-ignore rules.
- Laravel News feature badge in the README.

### Changed

- Refined Composer package keywords for better clarity and discoverability.

### Fixed

- README and documentation updates.

## [0.2.0] - 2026-03-27

### Added

- Support for Laravel 13. [#3](https://github.com/promptphp/deck/pull/3)
- Configuration option to toggle auto-scaffolding of prompts on agent creation.

### Fixed

- README and documentation updates.

## [0.1.0] - 2026-03-04

### Added

- First version. [#1](https://github.com/promptphp/deck/pull/1)
- Core versioned prompt management.
- File-based prompt storage using structured prompt directories.
- Variable interpolation for prompt templates.
- Artisan commands for creating, listing, testing, diffing, and activating prompts.
- Prompt execution tracking support.
- A/B testing support through versioned prompt activation and tracking.
- Optional Laravel AI SDK integration.
- README documenting the package.
- Link to the package's full documentation.
