# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.8.1] - 2022-02-25
### Changed
- provide a fallback for masked fields list in case someone updated the package and didn't clear their cache (it happens)

## [2.8.0] - 2022-02-25
### Changed
- using Laravel HTTP client instead of Guzzle
- fixed missing Cache facade
- simplifed the Laravel Octane check
