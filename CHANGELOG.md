# Changelog

All notable changes to Recordkeeper will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-17

### Added
- Headless audit trail for any Laravel app with zero-config model auditing
- PHP 8 attributes for declarative configuration (`#[Auditable]`, `#[Audit]`, `#[AuditJob]`, `#[AuditCommand]`, `#[AuditEvent]`, `#[AuditExclude]`, `#[Redact]`, `#[Encrypt]`, `#[TrackHttp]`)
- Incoming route and API request auditing with middleware (`audit`, `audit.api`)
- Global route auditing option for automatic coverage
- Outbound HTTP request tracking
- Job lifecycle auditing (queued, processing, completed, failed)
- Artisan command auditing with anomaly detection
- Application event auditing with payload capture
- Privacy protection: auto-redaction and AES encryption for sensitive fields
- Pattern-based auto-redaction (password, token, cvv, ssn, iban, etc.)
- Single audit and batch rollback with dry-run preview
- Batch audit grouping via `Recordkeeper::batch()`
- Manual event logging via `Recordkeeper::log()`
- Fluent audit query builder (`AuditQuery`)
- Read-through caching layer for audits
- Four storage drivers: database, redis, log, null
- Async queue support for audit writes
- Route sampling for high-traffic scenarios
- 13 Artisan commands: `install`, `sync`, `search`, `show`, `tail`, `stats`, `prune`, `rollback`, `history`, `undo`, `restore`, `export`, `wipe`
- Comprehensive test suite (61 test files, 1200+ assertions)
- CI/CD with GitHub Actions (tests, static analysis, mutation testing, security, benchmarks)
- PHPStan level 6 static analysis
- Mutation testing with Infection
- Performance benchmarks with PHPBench
- Bilingual documentation (English and Arabic)
- Support for PHP 8.2, 8.3, 8.4
- Support for Laravel 11.x and 12.x
