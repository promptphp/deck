# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Removed

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
