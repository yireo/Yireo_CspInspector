# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.8] - 25 November 2026
### Changed
- Console commands only load the CSP fetcher (and Guzzle) when they actually run, so other `bin/magento` commands are no longer slowed down
- Require `guzzlehttp/guzzle` 7.4.5 or newer, excluding vulnerable Guzzle 6 releases
- Enforced and report-only policies are reported together, with the first occurrence of a directive winning
### Fixed
- TLS certificates are now verified when fetching CSP headers; use `--insecure` (`-k`) to opt out
- Redirects are no longer followed by default; use `--follow-redirects` to follow them
- Remote header values and user input are escaped in console output, preventing console formatter tag injection
- Requests now time out, and non-200 responses are reported instead of being inspected silently
- Fetch errors and a missing default store view are reported gracefully instead of crashing the command
- Relative URLs are only resolved against a store that can build URLs, avoiding a fatal error with other store implementations

## [1.0.7] - 06 November 2026
### Fixed
- Compare CLI output with browser CSP headers for Luma and Hyva
- Pass explicit characters to trim() for PHP 8.6 compatibility
- Set dependency with `symfony/console` to v5, v6 or v7

## [1.0.6] - 01 June 2026
### Fixed
- Make commands Symfony 7 compliant

## [1.0.5] - 22 October 2025
### Fixed
- Copy generic CI/CD files
- Add CHANGELOG
- Fix wrong import of Console InputArgument, should be Symfony clas
- Remove int return type from Symfony commands for Symfony 5 compat
- Properly check for URL
