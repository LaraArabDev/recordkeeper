# Recordkeeper

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laraarabdev/recordkeeper.svg?style=flat-square)](https://packagist.org/packages/laraarabdev/recordkeeper)
[![Total Downloads](https://img.shields.io/packagist/dt/laraarabdev/recordkeeper.svg?style=flat-square)](https://packagist.org/packages/laraarabdev/recordkeeper)
[![License](https://img.shields.io/packagist/l/laraarabdev/recordkeeper.svg?style=flat-square)](https://packagist.org/packages/laraarabdev/recordkeeper)
[![Tests](https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/LaraArabDev/recordkeeper/actions/workflows/tests.yml)
[![codecov](https://img.shields.io/codecov/c/github/LaraArabDev/recordkeeper?style=flat-square)](https://codecov.io/gh/LaraArabDev/recordkeeper)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/static-analysis.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/LaraArabDev/recordkeeper/actions/workflows/static-analysis.yml)
[![Security Audit](https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/security.yml?branch=main&label=security&style=flat-square)](https://github.com/LaraArabDev/recordkeeper/actions/workflows/security.yml)
[![Mutation Testing](https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/mutation-testing.yml?branch=main&label=infection&style=flat-square)](https://github.com/LaraArabDev/recordkeeper/actions/workflows/mutation-testing.yml)
[![Code Style](https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/code-style.yml?branch=main&label=pint&style=flat-square)](https://github.com/LaraArabDev/recordkeeper/actions/workflows/code-style.yml)
[![PHP](https://img.shields.io/packagist/php-v/laraarabdev/recordkeeper.svg?style=flat-square)](https://packagist.org/packages/laraarabdev/recordkeeper)
[![Laravel](https://img.shields.io/badge/laravel-11.x%20%7C%2012.x-red?style=flat-square)](https://laravel.com)
[![Filament](https://img.shields.io/badge/filament-optional-orange?style=flat-square)](https://filamentphp.com)

**Headless audit trail, rollback & data protection for Laravel**
Track every model change, route hit, queued job, Artisan command, and application event — with one-click rollback, privacy controls, and zero config arrays.

Built on [owen-it/laravel-auditing](https://laravel-auditing.com/) · PHP 8.2 – 8.4 · Laravel 11 / 12

**[LaraArabDev](https://github.com/LaraArabDev)** — We build, develop, empower, and contribute. An Arab open-source community crafting production-grade Laravel packages for the global ecosystem.  
**[LaraArabDev](https://github.com/LaraArabDev)** — نبني، نطوّر، نُمكّن، ونُساهم. مجتمع عربي مفتوح المصدر يصنع حزم Laravel احترافية وجاهزة للإنتاج للمجتمع العالمي.

[English](#what-is-recordkeeper) · [العربية](#ما-هو-recordkeeper)

---

## What is Recordkeeper?

Recordkeeper is a **Laravel package** that supercharges [owen-it/laravel-auditing](https://laravel-auditing.com/) — the most popular audit package in the Laravel ecosystem. While `laravel-auditing` tracks model changes, **Recordkeeper extends it to track everything your application does**: HTTP routes, API requests, queued jobs, Artisan commands, application events, and outbound HTTP calls.

Instead of writing long config arrays, you use **clean PHP 8 attributes** right on your classes. Instead of writing custom code for privacy, you get **automatic redaction and encryption**. Instead of manual data recovery, you get **one-click rollback** with dry-run preview. And you get all of this with **zero UI dependency** — it's fully headless, works in any Laravel app.

### What makes Recordkeeper different?

- **Drop-in enhancement** — it doesn't replace `laravel-auditing`, it builds on top of it. Your existing setup keeps working.
- **Everything is opt-in** — start with model auditing, then enable routes, jobs, commands as you need them. Features you don't use have zero overhead.
- **PHP 8 Attributes over config arrays** — declare `#[Auditable]`, `#[Redact]`, `#[Encrypt]` right on your model. No more hunting through config files.
- **Privacy by default** — sensitive fields like `password`, `token`, `ssn` are auto-redacted before they hit the database. You never store plaintext secrets.
- **Real rollback** — not just "show me the old values". Actually revert creates, updates, and deletes — single records or entire batches, atomically.
- **Full observability from CLI** — 8 Artisan commands let you search, tail, diff, rollback, prune, and view stats without writing a single line of code.

---

## Why Recordkeeper over plain laravel-auditing?

If you're already using `laravel-auditing` and wondering whether you need Recordkeeper, here's what you're missing:

| Feature                          | laravel-auditing alone                      | + Recordkeeper                                                   |
| -------------------------------- | ------------------------------------------- | ---------------------------------------------------------------- |
| Model CRUD tracking              | Yes                                         | Yes                                                              |
| Route & API request auditing     | No — you'd have to write custom middleware  | **Built-in** — just add `audit` or `audit.api` middleware        |
| Job lifecycle tracking           | No                                          | **Yes** — queued, processing, processed, failed                  |
| Artisan command auditing         | No                                          | **Yes** — exit code, memory, anomaly detection                   |
| Application event auditing       | No                                          | **Yes** — opt-in with payload capture                            |
| Outbound HTTP tracking           | No                                          | **Yes** — linked to parent job audit                             |
| Configuration                    | Verbose PHP arrays in config files          | **PHP 8 Attributes** — `#[Auditable]`, `#[Redact]`, `#[Encrypt]` |
| Auto privacy protection          | No — manual exclusions                      | **Auto-redact** passwords, tokens, API keys, SSNs automatically  |
| Field encryption in audits       | No                                          | **AES-256 encryption** with auto-decrypt on rollback             |
| One-click rollback               | `transitionTo()` — manual, no batch support | **Single + batch rollback** with dry-run preview                 |
| Batch grouping                   | No                                          | **Yes** — group related changes, roll back atomically            |
| CLI tools                        | No                                          | **8 Artisan commands** — search, tail, stats, prune, rollback    |
| Storage backends                 | Database only                               | **4 drivers** — Database, Redis, Log, Null                       |
| Sampling for high-traffic routes | No                                          | **Yes** — `sample: 0.5` captures 50% of requests                 |

**Bottom line:** `laravel-auditing` gives you model-level "what changed". Recordkeeper gives you **application-level "what happened, who did it, when, and the ability to undo it"**.

---

## How easy is it to use?

### 3 commands to install

```bash
composer require laraarabdev/recordkeeper
php artisan recordkeeper:install
php artisan migrate
```

### 1 trait to audit a model

```php
class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;  // that's it — config defaults apply automatically
}
// creates, updates, deletes tracked + password excluded + sensitive fields redacted
```

### 1 middleware to audit routes

```php
Route::middleware('audit.api')->apiResource('orders', OrderController::class);
// Every request is now logged with actor, timing, status code
```

### 1 attribute to protect sensitive data

```php
#[Redact('cvv')]
#[Encrypt('national_id')]
class Payment extends Model { ... }
// cvv stored as "***", national_id AES-encrypted — automatically
```

### 1 line to undo a mistake

```php
Recordkeeper::rollback($auditId);
// Done. The model is restored to its previous state.
```

**No boilerplate. No config files to edit. No custom middleware to write.** Just attributes, traits, and you're auditing.

---

## How does it transform your app?

|     |
| --- |
|     |

### Before Recordkeeper

- "Who changed that order status?" — grep through logs, hope you find it
- "Can we undo that bulk import?" — restore from last night's backup
- "Is our API storing credit card numbers in audit logs?" — nobody knows
- "How long did that sync command take?" — check server monitoring (if you have it)
- "What HTTP calls did that failed job make?" — add logging, deploy, wait for it to fail again

### After Recordkeeper

- `php artisan recordkeeper:search --model=Order --event=updated` — instant answer
- `Recordkeeper::rollbackBatch('bulk-import-jan')` — one line, atomic
- Auto-redacted by default. `#[Encrypt]` for fields you need to recover.
- `php artisan recordkeeper:stats` — dashboard with duration, memory, anomaly flags
- Outbound HTTP tracked automatically, linked to the job audit

### Real-world scenarios Recordkeeper solves

| Scenario                                 | How Recordkeeper helps                                                                             |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------- |
| **Compliance / GDPR audit trail**        | Every data change is tracked with who, when, and what — query by user, model, date range           |
| **Customer support: "my data changed"**  | `recordkeeper:show {id}` shows exact before/after diff with color coding                           |
| **Undo a bad deployment's data changes** | Batch rollback reverts everything atomically, with dry-run preview                                 |
| **Debugging failed queue jobs**          | Job audit shows lifecycle + all outbound HTTP calls the job made                                   |
| **Monitoring Artisan commands**          | Anomaly detection flags when a command creates 10x more records than usual                         |
| **API security audit**                   | Every API request logged with actor, guard, IP, duration — `recordkeeper:tail` for live monitoring |
| **Sensitive data in logs**               | Auto-redaction ensures passwords, tokens, SSNs never reach the audit table                         |

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [PHP 8 Attributes](#php-8-attributes)
- [Attribute Reference](#attribute-reference)
- [Config Defaults & Model Overrides](#config-defaults--model-overrides)
- [Route & API Auditing](#route--api-auditing)
- [Job, Command & Event Auditing](#job-command--event-auditing)
- [Outbound HTTP Tracking](#outbound-http-tracking)
- [Privacy & Data Protection](#privacy--data-protection)
- [Rollback](#rollback)
- [Batch Auditing](#batch-auditing)
- [Manual Logging](#manual-logging)
- [Querying Audits](#querying-audits)
- [Artisan Commands](#artisan-commands)
- [Storage Drivers](#storage-drivers)
- [Customization](#customization)
- [Configuration Reference](#configuration-reference)
- [Database Schema](#database-schema)
- [Testing & Quality](#testing--quality)
- [Performance & Benchmarks](#performance--benchmarks)
- [FAQ](#faq)
- [Security](#security)
- [Credits & License](#credits--license)

---

## Prerequisites

Recordkeeper is built **on top of** [owen-it/laravel-auditing](https://laravel-auditing.com/). It is installed automatically as a dependency — you don't need to install it separately.

| Requirement              | Version                                  |
| ------------------------ | ---------------------------------------- |
| PHP                      | 8.2, 8.3, or 8.4                         |
| Laravel                  | 11 or 12                                 |
| owen-it/laravel-auditing | ^13.0 or ^14.0 (installed automatically) |

> **Already using laravel-auditing?** Recordkeeper works alongside your existing setup. Your current auditable models will keep working. Just add the `AuditsChanges` trait to unlock Recordkeeper's features on top.

---

## Installation

### Step 1: Install the package

```bash
composer require laraarabdev/recordkeeper
```

This automatically installs `owen-it/laravel-auditing` as a dependency if you don't have it yet.

### Step 2: Publish config and migrations

```bash
php artisan recordkeeper:install
```

This publishes:

- `config/recordkeeper.php` — Recordkeeper configuration
- `config/audit.php` — laravel-auditing configuration
- Database migrations for both packages

### Step 3: Run migrations

```bash
php artisan migrate
```

This creates the `audits` table (from laravel-auditing) and adds Recordkeeper's extra columns (`guard`, `batch_id`, `context`) plus the `audit_http_requests` table.

> **Tip:** Use `php artisan recordkeeper:install --force` to overwrite previously published files.

---

## Quick Start

Add the trait to any Eloquent model — that's all you need:

```php
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;

class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

**That's it.** Just the trait. Recordkeeper automatically:

- Tracks `created`, `updated`, `deleted`, `restored` events (from config)
- Discovers fields from `$fillable` and `$casts`
- Excludes `password` and `remember_token` globally
- Auto-redacts sensitive fields (tokens, API keys, SSN, etc.)
- Uses the global retention policy

**Use `#[Auditable]` only when you need to override defaults:**

```php
use LaraArabDev\Recordkeeper\Attributes\{Auditable, Redact, Encrypt};
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;

#[Auditable(
    events: ['created', 'updated'],  // override: skip deleted/restored
    retentionDays: 90,               // override: 90 days instead of global default
    tags: ['payments'],              // add tags for filtering
)]
#[Redact('discount_code')]           // extra: redact this field
#[Encrypt('national_id')]            // extra: encrypt this field (recoverable on rollback)
class Payment extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

---

## PHP 8 Attributes

Forget config arrays. Declare audit behavior right on your classes with clean, readable attributes:

### Model Attributes

| Attribute                  | Target | Description                                                                                                          |
| -------------------------- | ------ | -------------------------------------------------------------------------------------------------------------------- |
| `#[Auditable]`             | Class  | Enable auditing with options: `events`, `only`, `exclude`, `redact`, `encrypt`, `retentionDays`, `threshold`, `tags` |
| `#[AuditExclude('field')]` | Class  | Never store this field in any audit record. Repeatable.                                                              |
| `#[Redact('field')]`       | Class  | Replace value with `***` before storage. Repeatable.                                                                 |
| `#[Encrypt('field')]`      | Class  | AES-encrypt before storage; auto-decrypted on rollback. Repeatable.                                                  |

### Non-Model Attributes

| Attribute         | Target | Description                                                                           |
| ----------------- | ------ | ------------------------------------------------------------------------------------- |
| `#[Audit]`        | Method | Configure route-level auditing: `tag`, `body`, `response`, `sample`                   |
| `#[AuditJob]`     | Class  | Opt a queued job into tracking: `queued`, `processing`, `processed`, `failed`, `tags` |
| `#[AuditCommand]` | Class  | Opt an Artisan command into tracking: `tags`                                          |
| `#[AuditEvent]`   | Class  | Opt an event into tracking: `tags`, `capturePayload`                                  |

### Non-Model Traits

| Trait           | Target        | Description                                                                    |
| --------------- | ------------- | ------------------------------------------------------------------------------ |
| `AuditsJob`     | Job class     | Opt in with overridable methods: `auditJobTags()`, `shouldAuditQueued()`, etc. |
| `AuditsCommand` | Command class | Opt in with overridable: `auditCommandTags()`                                  |
| `AuditsEvent`   | Event class   | Opt in with overridable: `auditEventTags()`, `shouldCapturePayload()`          |

> **Trait vs Attribute?** Use traits when you want to override behavior with methods (e.g., dynamic tags based on class properties). Use attributes for static, declarative config. When both are present, the attribute takes priority.

### Example — Full Model Setup

```php
use LaraArabDev\Recordkeeper\Attributes\{Auditable, AuditExclude, Redact, Encrypt};
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;

#[Auditable(
    events: ['created', 'updated', 'deleted'],
    tags: ['payments'],
    retentionDays: 180,
    threshold: 500,
)]
#[AuditExclude('internal_notes')]
#[Redact('cvv')]
#[Encrypt('national_id')]
class Payment extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

---

## Attribute Reference

Every attribute parameter explained with its type, default, and what it does.

### `#[Auditable]` — Model Audit Configuration

Applied to a model class. All parameters are optional — config defaults are used when omitted.

| Parameter       | Type           | Default           | Description                                                                                                                                                                                           |
| --------------- | -------------- | ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `events`        | `list<string>  | null`             | `null` (uses config `events`)                                                                                                                                                                         | Eloquent events to audit. Example: `['created', 'updated']`. When `null`, falls back to `config('recordkeeper.events')` which defaults to `['created', 'updated', 'deleted', 'restored']`. |
| `only`          | `list<string>` | `[]` (all fields) | **Whitelist** — only audit these attributes. When empty, all changed attributes are tracked. Example: `['status', 'total']`.                                                                          |
| `exclude`       | `list<string>` | `[]`              | **Blacklist** — never audit these attributes. Merged with `config('recordkeeper.privacy.global_exclude')` (which includes `password` and `remember_token` by default). Example: `['internal_notes']`. |
| `redact`        | `list<string>` | `[]`              | Replace these attribute values with `***` before storage. The original value is **not recoverable**. Example: `['cvv', 'ssn']`.                                                                       |
| `encrypt`       | `list<string>` | `[]`              | AES-encrypt these attribute values before storage. Auto-decrypted on rollback. Example: `['national_id']`.                                                                                            |
| `retentionDays` | `int           | null`             | `null` (uses config)                                                                                                                                                                                  | Override the global `retention.default_days` for this model. Example: `180` keeps audits for 6 months.                                                                                     |
| `threshold`     | `int           | null`             | `null` (disabled)                                                                                                                                                                                     | Maximum audit records per model instance. Oldest are pruned when exceeded. Example: `500`.                                                                                                 |
| `tags`          | `list<string>` | `[]`              | Tags applied to every audit of this model, for filtering and grouping. Example: `['billing', 'critical']`.                                                                                            |

```php
// All defaults — equivalent to just #[Auditable]
#[Auditable(
    events: null,          // uses config: ['created', 'updated', 'deleted', 'restored']
    only: [],              // track all changed fields
    exclude: [],           // only global_exclude applies (password, remember_token)
    redact: [],            // no extra redaction (auto-redaction from sensitive_patterns still applies)
    encrypt: [],           // no encryption
    retentionDays: null,   // uses config: retention.default_days
    threshold: null,       // no per-instance limit
    tags: [],              // no tags
)]
class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

### `#[AuditExclude]` — Exclude Fields

Applied to a model class. **Repeatable** — use multiple on the same class.

| Parameter        | Type                | Description                                    |
| ---------------- | ------------------- | ---------------------------------------------- |
| `...$attributes` | `string` (variadic) | Field names to exclude from all audit records. |

```php
#[AuditExclude('internal_notes', 'cache_key')]
#[AuditExclude('temp_token')]  // repeatable
class User extends Model { ... }
```

### `#[Redact]` — Redact Fields

Applied to a model class. **Repeatable.** Values are replaced with `*`\*\* (configurable via `privacy.mask`).

| Parameter        | Type                | Description                                                     |
| ---------------- | ------------------- | --------------------------------------------------------------- |
| `...$attributes` | `string` (variadic) | Field names to redact. Original values are **not recoverable**. |

```php
#[Redact('ssn', 'date_of_birth')]
class Patient extends Model { ... }
```

### `#[Encrypt]` — Encrypt Fields

Applied to a model class. **Repeatable.** Values are AES-encrypted and auto-decrypted on rollback.

| Parameter        | Type                | Description                            |
| ---------------- | ------------------- | -------------------------------------- |
| `...$attributes` | `string` (variadic) | Field names to encrypt before storage. |

```php
#[Encrypt('national_id', 'bank_account')]
class Employee extends Model { ... }
```

### `#[Audit]` — Route-Level Auditing

Applied to a **controller method**. Controls per-route audit behavior.

| Parameter  | Type    | Default | Description                                                                                                    |
| ---------- | ------- | ------- | -------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `tag`      | `string | null`   | `null`                                                                                                         | Custom tag for this route's audit records. Example: `'order-checkout'`. |
| `body`     | `bool`  | `false` | Capture the request body in the audit record.                                                                  |
| `response` | `bool`  | `false` | Capture the response body in the audit record.                                                                 |
| `sample`   | `float` | `1.0`   | Sampling rate between `0.0` and `1.0`. Example: `0.5` audits 50% of requests (useful for high-traffic routes). |

```php
#[Audit(tag: 'checkout', body: true, response: true, sample: 0.5)]
public function store(Request $request) { ... }
```

### `#[AuditJob]` — Job Lifecycle Tracking

Applied to a **queued job class**. Controls which lifecycle events to track.

| Parameter    | Type           | Default | Description                                     |
| ------------ | -------------- | ------- | ----------------------------------------------- |
| `queued`     | `bool`         | `true`  | Record when the job is dispatched to the queue. |
| `processing` | `bool`         | `true`  | Record when the job starts processing.          |
| `processed`  | `bool`         | `true`  | Record when the job completes successfully.     |
| `failed`     | `bool`         | `true`  | Record when the job fails.                      |
| `tags`       | `list<string>` | `[]`    | Tags for filtering. Example: `['billing']`.     |

```php
#[AuditJob(queued: true, processing: false, processed: true, failed: true, tags: ['payments'])]
class ChargeCustomer implements ShouldQueue { ... }
```

### `#[AuditCommand]` — Artisan Command Tracking

Applied to an **Artisan command class**.

| Parameter | Type           | Default | Description                                     |
| --------- | -------------- | ------- | ----------------------------------------------- |
| `tags`    | `list<string>` | `[]`    | Tags for filtering. Example: `['maintenance']`. |

```php
#[AuditCommand(tags: ['maintenance', 'nightly'])]
class PruneInactiveUsers extends Command { ... }
```

### `#[AuditEvent]` — Application Event Tracking

Applied to an **event class**.

| Parameter        | Type           | Default | Description                                               |
| ---------------- | -------------- | ------- | --------------------------------------------------------- |
| `tags`           | `list<string>` | `[]`    | Tags for filtering. Example: `['shipping']`.              |
| `capturePayload` | `bool`         | `false` | Store the event's public properties in the audit context. |

```php
#[AuditEvent(capturePayload: true, tags: ['shipping'])]
class OrderShipped { ... }
```

---

## Config Defaults & Model Overrides

Recordkeeper follows a **layered defaults** pattern: the config file provides sensible defaults, and models can override any setting via attributes. You don't need to configure anything on the model unless you want to change the default.

### How it works

```
config/recordkeeper.php     →  Global defaults (all models)
     ↓
#[Auditable(...)]           →  Model-level overrides (this model only)
     ↓
Auto-detection              →  Fields discovered from fillable + casts
     ↓
Auto-redaction              →  sensitive_patterns matched against field names
```

### What happens with just the trait

When you use `AuditsChanges` with no `#[Auditable]` attribute, Recordkeeper automatically:

1. **Tracks all CRUD events** — `created`, `updated`, `deleted`, `restored` (from `config('recordkeeper.events')`)
2. **Discovers model fields** — reads `$fillable` and `$casts` to know your attributes
3. **Excludes sensitive fields** — `password` and `remember_token` are excluded globally (from `config('recordkeeper.privacy.global_exclude')`)
4. **Auto-redacts dangerous patterns** — any field whose name contains `password`, `secret`, `token`, `api_key`, `authorization`, `card`, `cvv`, `ssn`, or `iban` is redacted with `*`\*\* (from `config('recordkeeper.privacy.sensitive_patterns')`)
5. **Uses global retention** — audit retention from `config('recordkeeper.retention.default_days')`

```php
// Just the trait — ALL config defaults apply automatically
class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;

    protected $fillable = ['status', 'total', 'customer_email', 'api_key'];
    // api_key is auto-redacted (matches 'api_key' pattern)
    // password is globally excluded (never stored)
    // All fields discovered from $fillable automatically
}
```

### Override defaults per model with `#[Auditable]`

```php
// #[Auditable] is only needed when you want to override config defaults
#[Auditable(
    events: ['updated', 'deleted'],    // override: skip 'created' and 'restored'
    exclude: ['internal_cache'],       // add to global excludes (password, remember_token + this)
    retentionDays: 90,                 // override: 90 days instead of global default
    tags: ['finance'],                 // add model-specific tags
)]
class Invoice extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

### Customize global defaults

Edit `config/recordkeeper.php` to change what applies to **all models**:

```php
// config/recordkeeper.php
return [
    // Change default events for all models
    'events' => ['created', 'updated', 'deleted'],  // removed 'restored'

    'privacy' => [
        // Fields excluded from ALL models (never stored in audit)
        'global_exclude' => ['password', 'remember_token', 'two_factor_secret'],

        // Field name patterns that trigger auto-redaction
        'sensitive_patterns' => [
            'password', 'secret', 'token', 'api_key',
            'authorization', 'card', 'cvv', 'ssn', 'iban',
            'social_security',  // add your own patterns
        ],
    ],

    // Default retention for all models
    'retention' => [
        'default_days' => 365,
        'per_model' => [
            'App\Models\Order' => 90,     // override for specific models
            'App\Models\Payment' => 730,
        ],
    ],
];
```

### Quick reference: where each setting comes from

| Setting            | Config key                   | `#[Auditable]` override | Auto-detected                   |
| ------------------ | ---------------------------- | ----------------------- | ------------------------------- |
| Events to track    | `events`                     | `events: [...]`         | —                               |
| Fields to audit    | —                            | `only: [...]`           | From `$fillable` + `$casts`     |
| Fields to exclude  | `privacy.global_exclude`     | `exclude: [...]`        | —                               |
| Fields to redact   | `privacy.sensitive_patterns` | `redact: [...]`         | Pattern matching on field names |
| Fields to encrypt  | —                            | `encrypt: [...]`        | —                               |
| Retention period   | `retention.default_days`     | `retentionDays: N`      | —                               |
| Per-instance limit | —                            | `threshold: N`          | —                               |
| Tags               | —                            | `tags: [...]`           | —                               |

---

## Route & API Auditing

Two middleware aliases are registered automatically — no setup needed:

| Middleware  | Guard | Actor Resolution                                      |
| ----------- | ----- | ----------------------------------------------------- |
| `audit`     | `web` | `auth()->user()`                                      |
| `audit.api` | `api` | Iterates all non-web guards (Sanctum, Passport, etc.) |

### Basic Usage

```php
// Web routes
Route::middleware('audit')->group(function () {
    Route::post('/pay', PayController::class);
    Route::put('/profile', ProfileController::class);
});

// API routes
Route::middleware(['auth:sanctum', 'audit.api'])->group(function () {
    Route::apiResource('orders', OrderApiController::class);
});
```

### Fine-Grained Control with `#[Audit]`

```php
use LaraArabDev\Recordkeeper\Attributes\Audit;

class OrderController extends Controller
{
    #[Audit(tag: 'order-update', body: true, response: true, sample: 0.5)]
    public function update(Request $request, Order $order)
    {
        // Request body & response are captured
        // Only 50% of requests are sampled (for high-traffic routes)
    }
}
```

### What Gets Recorded

```json
{
  "event": "route.put",
  "guard": "api",
  "url": "https://example.com/api/orders/42",
  "ip_address": "192.168.1.100",
  "user_type": "App\\Models\\User",
  "user_id": 7,
  "context": {
    "route": "orders.update",
    "method": "PUT",
    "status": 200,
    "duration_ms": 42
  }
}
```

---

## Job, Command & Event Auditing

Recordkeeper gives you **three ways** to opt into auditing for jobs, commands, and events. Use whichever fits your style:

| Method        | How                   | Best for                                                              |
| ------------- | --------------------- | --------------------------------------------------------------------- |
| **Trait**     | `use AuditsJob;`      | Quick opt-in with config defaults, override methods for customization |
| **Attribute** | `#[AuditJob]`         | Declarative, all config in one place                                  |
| **Config**    | `jobs.enabled = true` | Audit everything globally                                             |

**Priority:** Attribute > Trait > Config. When both an attribute and trait are present on the same class, the attribute wins.

### Job Tracking

**Option 1: Trait (recommended for most cases)**

Just add the trait — your job is now audited with sensible defaults:

```php
use LaraArabDev\Recordkeeper\Concerns\AuditsJob;

class ProcessPayment implements ShouldQueue
{
    use AuditsJob;
    // Tracks: queued -> processing -> processed/failed
}
```

Override trait methods to customize behavior per class:

```php
use LaraArabDev\Recordkeeper\Concerns\AuditsJob;

class ProcessPayment implements ShouldQueue
{
    use AuditsJob;

    public function auditJobTags(): array
    {
        return ['billing', 'payments'];
    }

    public function shouldAuditQueued(): bool
    {
        return false;  // skip the "queued" event
    }
}
```

**Option 2: Attribute**

```php
use LaraArabDev\Recordkeeper\Attributes\AuditJob;

#[AuditJob(tags: ['billing'], queued: false)]
class ProcessPayment implements ShouldQueue { ... }
```

**Option 3: Global config**

```php
// config/recordkeeper.php
'jobs' => ['enabled' => true],
```

### Command Tracking

**Option 1: Trait**

```php
use LaraArabDev\Recordkeeper\Concerns\AuditsCommand;

class PruneInactiveUsers extends Command
{
    use AuditsCommand;

    public function auditCommandTags(): array
    {
        return ['maintenance'];
    }
}
```

**Option 2: Attribute**

```php
use LaraArabDev\Recordkeeper\Attributes\AuditCommand;

#[AuditCommand(tags: ['maintenance'])]
class PruneInactiveUsers extends Command { ... }
```

Includes **anomaly detection** — flags runs where duration or audit count exceeds the historical average:

```json
{
  "command": "app:sync-orders",
  "exit_code": 0,
  "duration_ms": 842,
  "memory_peak_mb": 34.5,
  "audit_count": 217,
  "anomaly": true,
  "anomaly_reason": "audit_count 217 > 2x avg (98)"
}
```

### Event Tracking

**Option 1: Trait**

```php
use LaraArabDev\Recordkeeper\Concerns\AuditsEvent;

class OrderShipped
{
    use AuditsEvent;

    public function auditEventTags(): array
    {
        return ['shipping'];
    }

    public function shouldCapturePayload(): bool
    {
        return true;  // store event properties in the audit
    }
}
```

**Option 2: Attribute**

```php
use LaraArabDev\Recordkeeper\Attributes\AuditEvent;

#[AuditEvent(capturePayload: true, tags: ['shipping'])]
class OrderShipped { ... }
```

**Option 3: Config (no class modification needed)**

```php
// config/recordkeeper.php
'listen' => [
    \App\Events\OrderShipped::class,
    \App\Events\UserRegistered::class,
],
```

### Trait Method Reference

| Trait           | Method                    | Return  | Default | Description                   |
| --------------- | ------------------------- | ------- | ------- | ----------------------------- |
| `AuditsJob`     | `auditJobTags()`          | `array` | `[]`    | Tags for filtering            |
| `AuditsJob`     | `shouldAuditQueued()`     | `bool`  | `true`  | Record when queued            |
| `AuditsJob`     | `shouldAuditProcessing()` | `bool`  | `true`  | Record when processing starts |
| `AuditsJob`     | `shouldAuditProcessed()`  | `bool`  | `true`  | Record when completed         |
| `AuditsJob`     | `shouldAuditFailed()`     | `bool`  | `true`  | Record when failed            |
| `AuditsCommand` | `auditCommandTags()`      | `array` | `[]`    | Tags for filtering            |
| `AuditsEvent`   | `auditEventTags()`        | `array` | `[]`    | Tags for filtering            |
| `AuditsEvent`   | `shouldCapturePayload()`  | `bool`  | `false` | Store event properties        |

---

## Outbound HTTP Tracking

Track HTTP calls your jobs make to external services (payment gateways, shipping APIs, etc.):

```php
// config/recordkeeper.php
'http' => [
    'enabled'         => true,
    'capture_headers' => true,
    'capture_body'    => false,
    'exclude_hosts'   => ['internal.example.com'],
],
```

Outbound requests are stored in `audit_http_requests` and **linked to the parent job audit**, giving you a complete picture of what each job did externally. When a job fails, you can see exactly which API calls it made and what responses it got.

---

## Privacy & Data Protection

Recordkeeper ensures sensitive data is **transformed before it reaches the audit store** — never plaintext.

### Three Protection Levels

| Method                | How it works                                      | Rollback?                   |
| --------------------- | ------------------------------------------------- | --------------------------- |
| `#[Redact('field')]`  | Replaced with `***` (or custom mask)              | No — original value is gone |
| `#[Encrypt('field')]` | AES-encrypted, stored as `__encrypted:...`        | Yes — auto-decrypted        |
| Auto-redaction        | Pattern-matched fields are redacted automatically | No                          |

### Auto-Redaction Patterns

These field names are **automatically redacted** without any attribute needed:

`password` &middot; `secret` &middot; `token` &middot; `api_key` &middot; `authorization` &middot; `card` &middot; `cvv` &middot; `ssn` &middot; `iban`

> Any model attribute whose name contains one of these patterns is automatically redacted. You don't need to do anything — it's on by default.

### Per-Attribute vs Class-Level

```php
// Per-attribute (repeatable)
#[Redact('ssn', 'date_of_birth')]
#[Encrypt('api_secret')]
class Patient extends Model { ... }

// Or via #[Auditable] (same effect)
#[Auditable(redact: ['ssn', 'date_of_birth'], encrypt: ['api_secret'])]
class Patient extends Model { ... }
```

### Global Privacy Config

```php
// config/recordkeeper.php
'privacy' => [
    'mode'               => 'redact',   // 'redact' | 'encrypt' | 'off'
    'mask'               => '***',      // replacement string
    'sensitive_patterns' => ['password', 'secret', 'token', ...],
    'global_exclude'     => ['remember_token'],
],
```

---

## Rollback

Undo any audited change — single record or entire batch, with dry-run preview.

### Single Audit Rollback

```php
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;

// Preview first (dry run)
$preview = Recordkeeper::rollback($auditId, dryRun: true);

// Apply
Recordkeeper::rollback($auditId);

// Or from the audit model directly
$audit = Audit::find($auditId);
$audit->rollback();
```

### Batch Rollback

```php
// Revert all changes in a batch — atomic, in reverse order
Recordkeeper::rollbackBatch('nightly-import');
```

### How Events Are Reverted

| Original Event | Rollback Action                          |
| -------------- | ---------------------------------------- |
| `created`      | Model is force-deleted                   |
| `updated`      | Old values are restored                  |
| `deleted`      | Model is restored (supports SoftDeletes) |

> Encrypted values are **automatically decrypted** before restoration. Auditing is **disabled during rollback** to prevent recursive entries.

### Via Artisan

```bash
php artisan recordkeeper:rollback 1842 --dry-run   # preview what will change
php artisan recordkeeper:rollback 1842 --yes        # apply the rollback
php artisan recordkeeper:rollback --batch=nightly    # revert entire batch
```

---

## Batch Auditing

Group related changes so they can be queried and rolled back as a unit:

```php
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;

Recordkeeper::batch('nightly-import-2025-01', function () {
    $order1 = Order::create([...]);
    $order2 = Order::create([...]);
    $inventory->decrement('stock', 50);
    // All 3 audits share batch_id = 'nightly-import-2025-01'
});

// Something went wrong? Roll back the entire import in one line:
Recordkeeper::rollbackBatch('nightly-import-2025-01');
```

---

## Manual Logging

Record custom events outside the Eloquent/middleware flow:

```php
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;

// Simple event
Recordkeeper::log('payment.gateway.timeout', context: [
    'gateway' => 'stripe',
    'attempt' => 3,
]);

// With a subject model
Recordkeeper::log('export.triggered', subject: $order, context: [
    'format' => 'csv',
    'rows'   => 1500,
]);
```

---

## Querying Audits

### Eloquent Scopes

```php
use LaraArabDev\Recordkeeper\Models\Audit;

Audit::forModel('Order')->latest()->get();          // by model (short name or FQCN)
Audit::forSubject($order)->get();                    // by specific instance
Audit::forActor($admin)->get();                      // by who made the change
Audit::forGuard('api')->get();                       // by auth guard
Audit::forBatch('nightly-import')->get();            // by batch
Audit::rollbackable()->get();                        // only created/updated/deleted/restored
Audit::routeHits()->whereDate('created_at', today())->get();   // HTTP audits
Audit::jobAudits()->latest()->get();                 // job lifecycle audits
Audit::commandAudits()->get();                       // command audits
Audit::eventAudits()->get();                         // application event audits
```

### Fluent Query Builder

For complex searches with multiple filters:

```php
use LaraArabDev\Recordkeeper\Support\AuditQuery;

$audits = app(AuditQuery::class)
    ->model('Order')
    ->event(['created', 'updated'])
    ->actor(42, 'Admin')
    ->guard('api')
    ->tag('finance')
    ->since('-7 days')
    ->rollbackable()
    ->latest()
    ->limit(50)
    ->builder()
    ->get();
```

**All Available Query Methods**

| Method                   | Description                                           |
| ------------------------ | ----------------------------------------------------- | ----------------------- |
| `->model(string)`        | Filter by model — short name (e.g. `'Order'`) or FQCN |
| `->subjectId(int         | string)`                                              | Filter by auditable ID  |
| `->event(string          | array)`                                               | Filter by event name(s) |
| `->actor(id, type?)`     | Filter by `user_id` + optional `user_type`            |
| `->actorType(string)`    | Filter by actor type only                             |
| `->onlyAuthenticated()`  | Exclude system/anonymous audits                       |
| `->guard(string)`        | Filter by auth guard                                  |
| `->tag(string            | array)`                                               | Filter by tag(s)        |
| `->batch(string)`        | Filter by `batch_id`                                  |
| `->between(from, until)` | Date range filter                                     |
| `->since(from)`          | Created after a date                                  |
| `->search(string)`       | Free-text search across event, type, batch, user      |
| `->rollbackable()`       | Only model-change events                              |
| `->jobs()`               | Only job lifecycle events                             |
| `->commands()`           | Only command events                                   |
| `->events()`             | Only application events                               |
| `->latest()`             | Order by newest first                                 |
| `->limit(int)`           | Limit results                                         |
| `->offset(int)`          | Offset for pagination                                 |
| `->builder()`            | Get the underlying Eloquent Builder                   |

---

## Artisan Commands

| Command                  | Description                                                           |
| ------------------------ | --------------------------------------------------------------------- |
| `recordkeeper:install`   | Publish config and migrations                                         |
| `recordkeeper:search`    | Search audits with rich filters and multiple output formats           |
| `recordkeeper:show {id}` | Display a single audit with color-coded before/after diff             |
| `recordkeeper:tail`      | Live-follow new audits in real time (like `tail -f`)                  |
| `recordkeeper:stats`     | Audit statistics dashboard — totals, by event, top models, top actors |
| `recordkeeper:models`    | List all auditable models with their resolved configuration           |
| `recordkeeper:prune`     | Delete old audit records based on retention policy                    |
| `recordkeeper:rollback`  | Revert a single audit or an entire batch                              |

### Examples

```bash
# Search with filters — supports table, json, csv output
php artisan recordkeeper:search --model=Order --event=updated --since="-7 days" --format=json

# Show single audit with color-coded diff
php artisan recordkeeper:show 1842

# Live tail — watch audits stream in real time
php artisan recordkeeper:tail --model=Order --guard=api --interval=2

# Statistics dashboard
php artisan recordkeeper:stats --since="-30 days"

# Prune with dry-run preview
php artisan recordkeeper:prune --days=365 --dry-run
php artisan recordkeeper:prune --days=365 --yes

# Rollback with preview
php artisan recordkeeper:rollback 1842 --dry-run
php artisan recordkeeper:rollback --batch=nightly-import --yes

# List auditable models with their resolved config
php artisan recordkeeper:models --json
```

---

## Storage Drivers

Choose the right backend for your use case:

| Driver         | Best For                      | Rollback | Queries | Pruning          |
| -------------- | ----------------------------- | -------- | ------- | ---------------- |
| `**database**` | Full-featured (default)       | Yes      | Yes     | Yes              |
| `**redis**`    | High-throughput writes        | No       | Limited | Via TTL          |
| `**log**`      | Observability / debugging     | No       | No      | Via log rotation |
| `**null**`     | Tests / disabled environments | No       | No      | N/A              |

```php
// config/recordkeeper.php
'driver' => env('RECORDKEEPER_DRIVER', 'database'),

'drivers' => [
    'redis' => [
        'connection' => 'default',
        'ttl'        => 86400,      // auto-expire after 24h
    ],
    'log' => [
        'channel' => 'audit',       // dedicated log channel
        'level'   => 'info',
    ],
],
```

> **Custom drivers:** Implement `LaraArabDev\Recordkeeper\Contracts\AuditDriver` and register via `AuditDriverManager::extend()`.

---

## Customization

Recordkeeper is designed to fit into your app, not the other way around.

### Custom Actor Resolver

Override how the "who did this" is resolved:

```php
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;

// In a service provider boot() method:
Recordkeeper::resolveActorUsing(function () {
    return auth()->guard('admin')->user()
        ?? auth()->guard('api')->user()
        ?? auth()->user();
});
```

### Context Enrichment

Add custom metadata to every audit:

```php
// Add request-scoped context
Recordkeeper::pushContext([
    'deployment' => config('app.version'),
    'server'     => gethostname(),
]);

// Per-model context
$order->auditContext(['reason' => 'Customer requested change']);
$order->update(['status' => 'refunded']);
```

### Tags

Tag audits for easy filtering and grouping:

```php
// Via attribute
#[Auditable(tags: ['billing', 'critical'])]
class Invoice extends Model { ... }

// Via runtime
Recordkeeper::withTags(['nightly-sync']);
```

### Custom Storage Driver

```php
use LaraArabDev\Recordkeeper\Contracts\AuditDriver;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;

class ElasticsearchDriver implements AuditDriver
{
    public function persist(AuditPayload $payload): Audit { /* ... */ }
    public function find(int|string $id): ?Audit { /* ... */ }
    public function flush(): void { /* ... */ }
}

// Register in a service provider:
app(AuditDriverManager::class)->extend('elasticsearch', fn () => new ElasticsearchDriver);
```

### ChangeRecorded Event

React to audit writes with your own listeners (send Slack notifications, update dashboards, sync to external systems):

```php
use LaraArabDev\Recordkeeper\Events\ChangeRecorded;

class NotifyOnCriticalChange
{
    public function handle(ChangeRecorded $event): void
    {
        if (str_contains($event->audit->tags, 'critical')) {
            // Send Slack alert, create ticket, etc.
        }
    }
}
```

---

## Configuration Reference

Publish with `php artisan vendor:publish --tag=recordkeeper-config`.

**Full Configuration Table**

| Key                                   | Default                                      | Description                                       |
| ------------------------------------- | -------------------------------------------- | ------------------------------------------------- | --------- | ----- |
| **General**                           |                                              |                                                   |
| `enabled`                             | `true`                                       | Global on/off switch                              |
| `events`                              | `['created','updated','deleted','restored']` | Default Eloquent events to track                  |
| `strict`                              | `false`                                      | Throw on audit write failure (enable in tests)    |
| `driver`                              | `'database'`                                 | Storage backend                                   |
| **Privacy**                           |                                              |                                                   |
| `privacy.mode`                        | `'redact'`                                   | `redact`                                          | `encrypt` | `off` |
| `privacy.mask`                        | `'***'`                                      | Redaction replacement string                      |
| `privacy.sensitive_patterns`          | `['password','secret','token',...]`          | Auto-redacted field patterns                      |
| `privacy.global_exclude`              | `['remember_token']`                         | Fields excluded from all models                   |
| **Rollback**                          |                                              |                                                   |
| `rollback.enabled`                    | `true`                                       | Enable rollback feature                           |
| `rollback.permission`                 | `'rollback_audits'`                          | Required permission for rollback                  |
| `rollback.restore_deleted`            | `true`                                       | Allow restoring deleted models                    |
| **Retention**                         |                                              |                                                   |
| `retention.default_days`              | `0`                                          | Prune after N days (`0` = keep forever)           |
| `retention.per_model`                 | `[]`                                         | Per-model overrides: `['App\Models\Order' => 90]` |
| **Queue**                             |                                              |                                                   |
| `queue.enabled`                       | `false`                                      | Async audit writes                                |
| `queue.connection`                    | `null`                                       | Queue connection                                  |
| `queue.queue`                         | `'audits'`                                   | Queue name                                        |
| **Jobs**                              |                                              |                                                   |
| `jobs.enabled`                        | `false`                                      | Track queued job lifecycle                        |
| `jobs.exclude`                        | `[]`                                         | Job classes to skip                               |
| **Commands**                          |                                              |                                                   |
| `commands.enabled`                    | `false`                                      | Track Artisan command execution                   |
| `commands.exclude`                    | `['schedule:run',...]`                       | Commands to skip                                  |
| `commands.metrics.memory`             | `true`                                       | Record peak memory                                |
| `commands.metrics.audit_count`        | `true`                                       | Count audits per command run                      |
| `commands.metrics.anomaly`            | `false`                                      | Enable anomaly detection                          |
| `commands.metrics.anomaly_multiplier` | `2.0`                                        | Threshold multiplier                              |
| `commands.metrics.anomaly_min_runs`   | `5`                                          | Min history for comparison                        |
| **HTTP Tracking**                     |                                              |                                                   |
| `http.enabled`                        | `false`                                      | Track outbound HTTP from jobs                     |
| `http.queue`                          | `false`                                      | Async HTTP record persistence                     |
| `http.capture_headers`                | `false`                                      | Store request/response headers                    |
| `http.capture_body`                   | `false`                                      | Store response body                               |
| `http.body_limit`                     | `1000`                                       | Max response body bytes                           |
| `http.exclude_hosts`                  | `[]`                                         | Hosts to skip                                     |
| **Cache**                             |                                              |                                                   |
| `cache.enabled`                       | `false`                                      | Read cache for audit lookups                      |
| `cache.store`                         | `null`                                       | Laravel cache store                               |
| `cache.ttl`                           | `300`                                        | Cache TTL in seconds                              |
| **Discovery**                         |                                              |                                                   |
| `discovery.paths`                     | `['app/Models']`                             | Paths to scan for auditable models                |

---

## Database Schema

Recordkeeper extends the standard `laravel-auditing` schema with three additional columns and one new table.

### `audits` table (added columns)

| Column     | Type      | Index | Purpose                                     |
| ---------- | --------- | ----- | ------------------------------------------- |
| `guard`    | `varchar` | Yes   | Authentication guard (`web`, `api`, custom) |
| `batch_id` | `varchar` | Yes   | Groups related audits for batch rollback    |
| `context`  | `json`    | —     | Route info, duration, metrics, custom data  |

> A composite index on `(auditable_type, auditable_id, event)` is also added for fast lookups.

### `audit_http_requests` table

| Column             | Type            | Purpose                         |
| ------------------ | --------------- | ------------------------------- |
| `id`               | `bigint` PK     | —                               |
| `audit_id`         | `bigint` FK     | Links to parent job audit       |
| `method`           | `varchar(10)`   | HTTP verb (`GET`, `POST`, etc.) |
| `url`              | `text`          | Full request URL                |
| `status_code`      | `int` nullable  | Response status code            |
| `duration_ms`      | `int` nullable  | Round-trip time in milliseconds |
| `failed`           | `boolean`       | `true` if connection failed     |
| `exception`        | `text` nullable | Exception message on failure    |
| `request_headers`  | `json` nullable | When `capture_headers = true`   |
| `response_headers` | `json` nullable | When `capture_headers = true`   |
| `response_body`    | `text` nullable | When `capture_body = true`      |
| `created_at`       | `timestamp`     | —                               |

---

## Testing & Quality

```bash
composer test           # Pest test suite
composer test:coverage  # With code coverage
composer analyse        # PHPStan (level 6)
composer format         # Laravel Pint
composer infection      # Mutation testing
```

### Benchmarks

```bash
composer bench          # full suite
composer bench:quick    # fast run (3 revs x 2 iterations)
composer bench:http     # HTTP tracking benchmarks
composer bench:db       # database driver benchmarks
composer bench:command  # command auditing benchmarks
```

### Compatibility Matrix

| PHP | Laravel | Status    |
| --- | ------- | --------- |
| 8.2 | 11 / 12 | Supported |
| 8.3 | 11 / 12 | Supported |
| 8.4 | 11 / 12 | Supported |

---

## Performance & Benchmarks

Recordkeeper is engineered for **near-zero overhead** in production. Auditing should observe your application, not slow it down.

### How fast is it?

| Operation                  | Overhead           | Notes                                                     |
| -------------------------- | ------------------ | --------------------------------------------------------- |
| **Model audit (sync)**     | ~1-2ms per write   | Single INSERT into the `audits` table                     |
| **Model audit (async)**    | < 0.5ms sync       | Dispatches to queue, audit written in background          |
| **Route middleware**       | ~1-2ms per request | Captures timing, actor, context, then one INSERT          |
| **Job lifecycle event**    | ~0.5-1ms per event | Lightweight: resolves class, checks config, writes audit  |
| **Command audit**          | ~1ms on finish     | Only fires once per command execution                     |
| **Event audit**            | ~0.5-1ms per event | Skips framework events before any processing              |
| **Outbound HTTP tracking** | ~0.3ms per request | Records start time on send, computes duration on response |

### Why it's fast

1. **Opt-in architecture** — features you don't enable have **zero overhead**. Disabled listeners are never registered. Disabled middleware is never loaded.
2. **Early exits everywhere** — every listener checks `config('recordkeeper.enabled')` and exclusion lists before doing any work. Framework events (`Illuminate\`_, `eloquent._`) are skipped with a simple `str_starts_with()` check.
3. **No reflection in hot paths** — class attributes and traits are resolved once per class, not per event. Config lookups use Laravel's cached config.
4. **Async queue support** — enable `queue.enabled` to move audit writes off the request cycle entirely. Adds < 0.5ms synchronous overhead (just dispatching the job).
5. **Lightweight data objects** — `AuditPayload` is a simple DTO, no heavy ORM operations until the final write.
6. **Sampling for high-traffic routes** — `sample: 0.1` audits only 10% of requests. The random check is a single `mt_rand()` call.

### Run benchmarks yourself

```bash
composer bench           # full benchmark suite
composer bench:quick     # fast run (3 revs x 2 iterations)
composer bench:http      # HTTP tracking benchmarks
composer bench:db        # database driver benchmarks
composer bench:command   # command auditing benchmarks
```

### Production tips

| Scenario                              | Recommendation                                                            |
| ------------------------------------- | ------------------------------------------------------------------------- |
| High-traffic API (1000+ req/s)        | Enable `queue.enabled` for async writes, use `sample: 0.1` on busy routes |
| Write-heavy app (many model saves)    | Use the `redis` driver for fast writes, sync to database periodically     |
| Testing environment                   | Use the `null` driver — zero I/O, audits are silently discarded           |
| Separate audit queue from app queue   | Enable `queue.enabled` — audits go to a dedicated `audits` queue by default, keeping your app's main queue free |
| Different queue connection entirely   | Set `RECORDKEEPER_QUEUE_CONNECTION=redis-audits` to isolate audit workers on a separate connection |
| Large audit tables (millions of rows) | Set `retention.default_days` and schedule `recordkeeper:prune` daily      |

---

## FAQ

**Does this package require a lot of configuration to get started?**

No. Install, migrate, and add the trait — that's it. Recordkeeper works with sensible defaults out of the box:

```php
class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

This single trait automatically tracks creates, updates, deletes, and restores. It auto-discovers your fields from `$fillable` and `$casts`, excludes `password` and `remember_token`, and auto-redacts sensitive field patterns. **Zero config required.**

**Can I customize the auditing behavior?**

Yes. Recordkeeper is built on a **layered defaults** pattern — everything is customizable at multiple levels:

- **Global config** — `config/recordkeeper.php` sets defaults for all models
- **Per-model attributes** — `#[Auditable(events: ['updated'], tags: ['finance'])]` overrides for one model
- **Per-model traits** — `AuditsChanges` resolves config from attributes automatically
- **Per-job/command/event traits** — `AuditsJob`, `AuditsCommand`, `AuditsEvent` with overridable methods
- **Per-job/command/event attributes** — `#[AuditJob]`, `#[AuditCommand]`, `#[AuditEvent]`
- **Runtime API** — `Recordkeeper::pushContext()`, `Recordkeeper::withTags()`, `$model->auditContext()`
- **Custom storage drivers** — implement `AuditDriver` for Elasticsearch, S3, or any backend
- **Custom actor resolvers** — `Recordkeeper::resolveActorUsing()`
- **Pipeline stages** — add custom middleware to the audit write path

You can customize as little or as much as you need.

**Will this package slow down my application?**

No. Recordkeeper adds approximately **1-2ms per audit write** in synchronous mode. For context, a typical database query takes 1-5ms, and a typical HTTP request takes 50-500ms. The audit overhead is negligible.

For high-traffic applications, you can reduce overhead even further:

- **Async writes** — enable `queue.enabled` to move audit writes to a dedicated `audits` queue (< 0.5ms sync overhead, fully isolated from your app's queue)
- **Sampling** — use `sample: 0.1` on busy routes to audit only 10% of requests
- **Redis driver** — for the fastest writes with no SQL overhead
- **Null driver** — in test environments, zero I/O

Features you don't enable have **zero overhead** — disabled listeners are never registered.

**Is this compatible with my existing laravel-auditing setup?**

Yes. Recordkeeper is a **drop-in enhancement** — it builds on top of `laravel-auditing`, not a replacement. Your existing auditable models keep working exactly as before. Add `AuditsChanges` to a model to unlock Recordkeeper's extra features (auto-redaction, rollback, batching, CLI tools) on top of your current setup.

**Do I need to modify my event/job/command classes to audit them?**

Not necessarily. You have three options:

1. **Trait** — add `use AuditsJob;` to your class (one line, with overridable methods)
2. **Attribute** — add `#[AuditJob]` to your class (one line, declarative)
3. **Config** — set `jobs.enabled = true` to audit all jobs globally (no class changes)

The same pattern applies to commands and events.

**How does the priority work when I use both a trait and an attribute?**

The priority chain is: **Attribute > Trait > Config**.

```php
// The attribute wins — tags will be ['billing'], not ['trait-tag']
#[AuditJob(tags: ['billing'])]
class ProcessPayment implements ShouldQueue
{
    use AuditsJob;

    public function auditJobTags(): array
    {
        return ['trait-tag'];  // ignored when attribute is present
    }
}
```

This lets you start with a trait for defaults and add an attribute later to override specific settings.

**Can I keep audit writes on a separate queue so they don't compete with my app's jobs?**

Yes — this is the recommended setup for production. Enable async queue writes and Recordkeeper automatically sends audits to a dedicated `audits` queue, completely separate from your app's default queue:

```env
RECORDKEEPER_QUEUE=true
# Audits go to the "audits" queue by default — no config needed
```

Run a dedicated worker for audit writes:

```bash
# Your app worker — processes your normal jobs
php artisan queue:work --queue=default

# Audit worker — low priority, separate process
php artisan queue:work --queue=audits --sleep=3 --tries=3
```

For complete isolation, use a separate queue connection so audit writes don't share Redis/database connections with your app:

```env
RECORDKEEPER_QUEUE=true
RECORDKEEPER_QUEUE_CONNECTION=redis-audits
RECORDKEEPER_QUEUE_NAME=audits
```

This way your app's high-priority jobs are never delayed by audit writes, and you can scale audit workers independently.

**What happens to sensitive data like passwords in audit logs?**

Recordkeeper **never stores plaintext secrets**. Three layers of protection work automatically:

1. **Global exclusion** — `password` and `remember_token` are never stored in any audit (configurable)
2. **Auto-redaction** — any field whose name contains `password`, `secret`, `token`, `api_key`, `card`, `cvv`, `ssn`, or `iban` is replaced with `*`\*\* before storage
3. **Explicit encryption** — use `#[Encrypt('national_id')]` for fields you need to recover during rollback (AES-256)

All of this happens **before the data reaches the audit store** — the plaintext value is never written.

**Can I use this in a multi-tenant application?**

Yes. Use tags, context enrichment, and the guard system to segment audits per tenant:

```php
// Tag all audits with the current tenant
Recordkeeper::withTags(['tenant:'.$tenant->id]);

// Query audits for a specific tenant
Audit::query()->where('tags', 'like', '%tenant:42%')->get();
```

**How do I clean up old audit records?**

Set a retention policy and schedule pruning:

```php
// config/recordkeeper.php
'retention' => [
    'default_days' => 365,  // delete audits older than 1 year
    'per_model' => [
        'App\Models\Order' => 90,  // 90 days for orders
    ],
],
```

```php
// app/Console/Kernel.php
$schedule->command('recordkeeper:prune')->daily();
```

Or run manually: `php artisan recordkeeper:prune --days=365 --yes`

---

## Security

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

---

## Credits & License

- [LaraArabDev](https://github.com/LaraArabDev)
- [All Contributors](../../contributors)

MIT License — see [LICENSE](LICENSE) for details.

---

## ما هو Recordkeeper؟

**Recordkeeper** هو حزمة Laravel تُعزّز [owen-it/laravel-auditing](https://laravel-auditing.com/) وتوسّع قدراته بشكل كبير. بينما `laravel-auditing` يتتبع تغييرات الموديلات فقط، **Recordkeeper يتتبع كل شيء** في تطبيقك: المسارات (Routes)، طلبات API، الـ Jobs، أوامر Artisan، والأحداث (Events).

### لماذا تستخدم Recordkeeper؟

- **تتبع شامل** — لا يقتصر على الموديلات فقط، بل يشمل كل عملية في تطبيقك
- **PHP 8 Attributes** — بدل ملفات الإعدادات الطويلة، استخدم `#[Auditable]` و `#[Redact]` و `#[Encrypt]` مباشرة على الكلاس
- **حماية البيانات تلقائياً** — كلمات المرور والتوكنات وأرقام البطاقات تُخفى تلقائياً قبل التخزين
- **التراجع بضغطة واحدة** — استرجع أي تغيير فردي أو مجموعة تغييرات كاملة مع معاينة قبل التطبيق
- **8 أوامر Artisan** — ابحث، تتبع مباشرة، اعرض إحصائيات، نظّف، واسترجع من سطر الأوامر
- **4 محركات تخزين** — قاعدة البيانات، Redis، سجلات، أو Null للاختبارات

### التثبيت

```bash
composer require laraarabdev/recordkeeper
php artisan recordkeeper:install
php artisan migrate
```

> **ملاحظة:** الحزمة تثبّت `owen-it/laravel-auditing` تلقائياً كاعتماد — لا تحتاج لتثبيته يدوياً.

### البداية السريعة

أضف الـ trait والـ attribute لأي موديل Eloquent — هذا كل ما تحتاجه:

```php
use LaraArabDev\Recordkeeper\Attributes\Auditable;
use LaraArabDev\Recordkeeper\Attributes\Redact;
use LaraArabDev\Recordkeeper\Attributes\Encrypt;
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;

#[Auditable(events: ['created', 'updated', 'deleted'])]
#[Redact('cvv')]           // يُستبدل بـ *** في سجل التدقيق
#[Encrypt('national_id')]  // يُشفّر بـ AES ويُفك تلقائياً عند التراجع
class Payment extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

### تدقيق المسارات (Routes)

```php
// مسارات الويب
Route::middleware('audit')->group(function () {
    Route::post('/pay', PayController::class);
});

// مسارات API
Route::middleware(['auth:sanctum', 'audit.api'])->group(function () {
    Route::apiResource('orders', OrderApiController::class);
});
```

### التراجع عن التغييرات

```php
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;

// معاينة قبل التطبيق
$preview = Recordkeeper::rollback($auditId, dryRun: true);

// تطبيق التراجع
Recordkeeper::rollback($auditId);

// التراجع عن مجموعة كاملة
Recordkeeper::rollbackBatch('nightly-import');
```

### أوامر Artisan

```bash
php artisan recordkeeper:search --model=Order --event=updated    # البحث في السجلات
php artisan recordkeeper:show 1842                                # عرض تدقيق مع الفروقات
php artisan recordkeeper:tail --model=Order                       # تتبع مباشر
php artisan recordkeeper:stats --since="-30 days"                 # لوحة الإحصائيات
php artisan recordkeeper:rollback 1842 --dry-run                  # معاينة التراجع
php artisan recordkeeper:prune --days=365 --yes                   # تنظيف السجلات القديمة
php artisan recordkeeper:models                                    # عرض الموديلات المُراقبة
```

### المقارنة مع laravel-auditing وحده

| الميزة              | laravel-auditing فقط  | + Recordkeeper                  |
| ------------------- | --------------------- | ------------------------------- |
| تتبع الموديلات      | نعم                   | نعم                             |
| تدقيق المسارات وAPI | لا                    | **نعم**                         |
| تتبع الـ Jobs       | لا                    | **نعم**                         |
| تدقيق أوامر Artisan | لا                    | **نعم**                         |
| تتبع الأحداث        | لا                    | **نعم**                         |
| الإعداد             | مصفوفات PHP           | **PHP 8 Attributes + Traits**   |
| حماية الخصوصية      | أساسية                | **إخفاء تلقائي + تشفير AES**    |
| التراجع             | `transitionTo()` يدوي | **تراجع بضغطة واحدة** مع معاينة |
| التجميع (Batching)  | لا                    | **نعم** — تراجع ذري لمجموعة     |
| أدوات سطر الأوامر   | لا                    | **8 أوامر Artisan**             |
| محركات التخزين      | قاعدة بيانات فقط      | **4 محركات**                    |

---

للمزيد من التفاصيل والتوثيق الكامل، راجع الأقسام الإنجليزية أعلاه.
