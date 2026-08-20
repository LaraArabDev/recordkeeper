<p align="center">
    <img src="art/banner.svg" alt="Recordkeeper Banner" style="width: 100%; max-width: 800px;">
</p>

<h1 align="center">Recordkeeper</h1>

<p align="center">
    <strong>Headless audit trail, rollback & data protection for Laravel</strong>
</p>

<p align="center">
    <a href="https://packagist.org/packages/laraarabdev/recordkeeper"><img src="https://img.shields.io/packagist/v/laraarabdev/recordkeeper.svg?style=flat-square" alt="Latest Version"></a>
    <a href="https://packagist.org/packages/laraarabdev/recordkeeper"><img src="https://img.shields.io/packagist/dt/laraarabdev/recordkeeper.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/laraarabdev/recordkeeper"><img src="https://img.shields.io/packagist/l/laraarabdev/recordkeeper.svg?style=flat-square" alt="License"></a>
    <a href="https://packagist.org/packages/laraarabdev/recordkeeper"><img src="https://img.shields.io/packagist/php-v/laraarabdev/recordkeeper.svg?style=flat-square" alt="PHP"></a>
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/laravel-11.x%20%7C%2012.x-red?style=flat-square" alt="Laravel"></a>
</p>

<p align="center">
    <a href="https://github.com/LaraArabDev/recordkeeper/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/tests.yml?branch=main&label=tests&style=flat-square" alt="Tests"></a>
    <a href="https://codecov.io/gh/LaraArabDev/recordkeeper"><img src="https://img.shields.io/codecov/c/github/LaraArabDev/recordkeeper?style=flat-square" alt="codecov"></a>
    <a href="https://github.com/LaraArabDev/recordkeeper/actions/workflows/static-analysis.yml"><img src="https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/static-analysis.yml?branch=main&label=phpstan&style=flat-square" alt="Static Analysis"></a>
    <a href="https://github.com/LaraArabDev/recordkeeper/actions/workflows/security.yml"><img src="https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/security.yml?branch=main&label=security&style=flat-square" alt="Security Audit"></a>
    <a href="https://github.com/LaraArabDev/recordkeeper/actions/workflows/mutation-testing.yml"><img src="https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/mutation-testing.yml?branch=main&label=infection&style=flat-square" alt="Mutation Testing"></a>
    <a href="https://github.com/LaraArabDev/recordkeeper/actions/workflows/code-style.yml"><img src="https://img.shields.io/github/actions/workflow/status/LaraArabDev/recordkeeper/code-style.yml?branch=main&label=pint&style=flat-square" alt="Code Style"></a>
</p>

<p align="center">
    Track every model change, route hit, queued job, Artisan command, and application event — with one-click rollback, privacy controls, and zero config arrays.<br>
    Built on <a href="https://laravel-auditing.com/">owen-it/laravel-auditing</a> · PHP 8.2 – 8.4 · Laravel 11 / 12
</p>

<p align="center">
    <b><a href="https://github.com/LaraArabDev">LaraArabDev</a></b> — We build, develop, empower, and contribute. An Arab open-source community crafting production-grade Laravel packages.<br>
    <b><a href="https://github.com/LaraArabDev">LaraArabDev</a></b> — نبني، نطوّر، نُمكّن، ونُساهم. مجتمع عربي مفتوح المصدر يصنع حزم Laravel احترافية وجاهزة للإنتاج.
</p>

<p align="center">
    <a href="#-quick-start">Quick Start</a> ·
    <a href="#-features">Features</a> ·
    <a href="#-customization">Customization</a> ·
    <a href="#-faq">FAQ</a> ·
    <a href="#-العربية">العربية</a>
</p>

---

## What is Recordkeeper?

Every production application needs an answer to "what happened?" — whether it's a customer disputing a charge, an admin accidentally deleting records, or an auditor asking who changed what and when.

**Recordkeeper** gives your Laravel app a complete audit trail with **zero configuration**. Install the package, add one trait to your models, and you're done. Every create, update, delete, and restore is automatically logged with before/after values, who made the change, and when it happened.

But model changes are just the beginning. Recordkeeper can also track:

- Every HTTP request hitting your routes (who accessed what endpoint, response times, status codes)
- Every outbound API call your app makes (Stripe charges, webhook deliveries, third-party integrations)
- Every queued job from dispatch through completion or failure
- Every Artisan command with duration, memory usage, and anomaly detection
- Every application event with full payload capture

**You decide what to monitor.** Want to audit only model changes? Just add the trait. Need full API visibility? Turn on route auditing. Want to track outbound HTTP calls, jobs, commands, and events too? Enable them one by one. Or turn everything on at once. Every feature is independent — enable exactly the combination that fits your application, and disabled features have zero overhead.

It also comes with **built-in privacy protection** (auto-redact passwords, encrypt sensitive fields), **one-click rollback** (undo any change with a dry-run preview), and **8 Artisan commands** for searching, tailing, and managing your audit trail from the terminal.

> **Built on [owen-it/laravel-auditing](https://laravel-auditing.com/)** — the most popular audit package in the Laravel ecosystem. Recordkeeper installs it automatically. If you already use `laravel-auditing`, Recordkeeper is a drop-in enhancement — your existing auditable models keep working.

---

## 🚀 Quick Start

Three commands. That's it.

```bash
composer require laraarabdev/recordkeeper
php artisan recordkeeper:install
php artisan migrate
```

Now add the trait to any model you want to audit:

```php
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;

class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

Every `create`, `update`, `delete`, and `restore` on that model is now tracked automatically. No configuration files to edit, no arrays to maintain, no boilerplate.

| Requirement | Version |
| --- | --- |
| PHP | 8.2, 8.3, or 8.4 |
| Laravel | 11 or 12 |
| owen-it/laravel-auditing | ^13.0 or ^14.0 (auto-installed) |

---

## 🧭 How It Works

Recordkeeper is designed around a simple philosophy: **works out of the box, customize when you need to.**

The moment you add the `AuditsChanges` trait, Recordkeeper automatically tracks all CRUD events, discovers fields from `$fillable` and `$casts`, excludes passwords, auto-redacts sensitive patterns with `***`, stores audits with no expiry, and enables rollback. You don't need to touch a config file.

When you're ready to customize, you have three ways to do it — pick whichever fits your workflow:

```
config/recordkeeper.php   →  Global defaults (all models, all features)
       ↓
#[Auditable(...)]         →  Per-model overrides via PHP 8 Attributes
       ↓
Traits + Methods          →  Fine-grained control via overridable methods
```

**Priority: Attribute > Trait > Config.** When multiple are present, the most specific one wins.

For example, your config might set a 365-day retention globally, but a specific model can override that to 90 days with `#[Auditable(retentionDays: 90)]`. Or you can enable job tracking globally in config but exclude specific jobs. Mix and match as needed.

---

## 📦 Features

### Model Auditing

Automatically tracks every create, update, delete, and restore on your Eloquent models. Each audit records the old values, new values, who made the change, their IP address, and when it happened.

**Minimal setup — just add the trait:**

```php
class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

**Customize with attributes when you need more control:**

```php
use LaraArabDev\Recordkeeper\Attributes\Auditable;
use LaraArabDev\Recordkeeper\Attributes\AuditExclude;
use LaraArabDev\Recordkeeper\Attributes\Redact;
use LaraArabDev\Recordkeeper\Attributes\Encrypt;

#[Auditable(
    events: ['created', 'updated', 'deleted'],  // skip 'restored' if you don't need it
    tags: ['payments'],                          // tag all audits for easy filtering
    retentionDays: 180,                          // auto-prune after 6 months
    threshold: 500,                              // max audits per model instance
)]
#[AuditExclude('internal_notes')]   // never store this field
#[Redact('cvv')]                    // replace with *** before storage
#[Encrypt('national_id')]           // AES-encrypt, auto-decrypt on rollback
class Payment extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

<details>
<summary><strong>Available model attributes</strong></summary>

| Attribute | Description |
| --- | --- |
| `#[Auditable]` | Configure auditing: `events`, `only`, `exclude`, `redact`, `encrypt`, `retentionDays`, `threshold`, `tags` |
| `#[AuditExclude('field')]` | Never store this field in audits (repeatable) |
| `#[Redact('field')]` | Replace value with `***` before storage (repeatable) |
| `#[Encrypt('field')]` | AES-encrypt value, auto-decrypt on rollback (repeatable) |

</details>

---

### Route & API Auditing

Track every HTTP request coming into your application — who hit which endpoint, when, and what happened. Audits capture the route name, HTTP method, status code, response time, authenticated user, IP address, and user agent.

**Per-route — add middleware to specific routes:**

Two middleware aliases are registered automatically: `audit` (web guard) and `audit.api` (API guard with multi-guard resolution for Sanctum, Passport, etc.).

```php
// Web routes
Route::middleware('audit')->group(function () {
    Route::post('/pay', PayController::class);
});

// API routes
Route::middleware(['auth:sanctum', 'audit.api'])->group(function () {
    Route::apiResource('orders', OrderApiController::class);
});
```

**Global — audit every route with one env var:**

```env
RECORDKEEPER_ROUTES=true
```

This pushes audit middleware into the `web` and `api` middleware groups automatically. Routes with explicit `audit` or `audit.api` middleware are never double-audited. Common tool paths (Horizon, Telescope, Debugbar, etc.) are excluded by default.

**Fine-grained control on controller methods:**

```php
use LaraArabDev\Recordkeeper\Attributes\Audit;

#[Audit(tag: 'checkout', body: true, response: true, sample: 0.5)]
public function store(Request $request) { ... }
```

**Incoming webhooks** (Stripe, GitHub, Twilio) are just regular HTTP requests — they're audited the same way, no special setup required.

<details>
<summary><strong>Route configuration options</strong></summary>

```php
// config/recordkeeper.php
'routes' => [
    'enabled' => env('RECORDKEEPER_ROUTES', false),
    'web'     => true,        // audit web routes
    'api'     => true,        // audit api routes
    'exclude' => [            // skip these paths
        'horizon/*', 'telescope/*', '_debugbar/*',
        '_ignition/*', 'sanctum/*', 'livewire/*', 'health',
    ],
    'body'   => false,        // capture request body
    'sample' => 1.0,          // 1.0 = 100%, 0.1 = 10%
    'tag'    => null,          // optional tag for all global audits
],
```

</details>

---

### Outbound HTTP Tracking

Track every HTTP request your application makes to external services — API calls to Stripe, payment gateways, third-party integrations, outgoing webhooks. Each request records the URL, host, HTTP method, status code, round-trip time, and whether the connection failed.

**Setup — one env var:**

```env
RECORDKEEPER_HTTP=true
```

That's it. Recordkeeper hooks into Laravel's built-in HTTP client events (`RequestSending`, `ResponseReceived`, `ConnectionFailed`) — no middleware or code changes needed. Outbound requests are stored in a separate `audit_http_requests` table, linked to a parent audit record.

> **Note:** Only requests made through Laravel's `Http::` facade are tracked. Raw cURL or direct Guzzle calls bypass Laravel's events and are not captured.

**Per-job control with attributes:**

```php
use LaraArabDev\Recordkeeper\Attributes\TrackHttp;

#[TrackHttp(includeHosts: ['api.stripe.com', 'api.paypal.com'])]
class ProcessPaymentJob implements ShouldQueue { ... }
```

<details>
<summary><strong>HTTP tracking configuration</strong></summary>

```php
'http' => [
    'enabled' => env('RECORDKEEPER_HTTP', false),
    'mode' => 'auto',              // 'auto' = all, 'manual' = opt-in only
    'queue' => false,              // async persistence
    'capture_headers' => false,    // store request/response headers
    'capture_body' => false,       // store response body
    'body_limit' => 1000,          // truncate body at N characters
    'exclude_hosts' => [],         // skip these hosts
],
```

| Mode | Behavior |
| --- | --- |
| `auto` (default) | All outbound HTTP calls are tracked automatically |
| `manual` | Only track calls from jobs/contexts that explicitly opt in |

</details>

---

### Job Tracking

Follow queued jobs through their entire lifecycle: dispatched, processing, completed, or failed. Each transition is recorded as a separate audit entry, giving you a complete timeline of every job.

**Opt in per job — pick your style:**

```php
// With a trait (quick, overridable methods)
use LaraArabDev\Recordkeeper\Concerns\AuditsJob;

class ProcessPayment implements ShouldQueue
{
    use AuditsJob;
}

// With an attribute (declarative, all config in one place)
use LaraArabDev\Recordkeeper\Attributes\AuditJob;

#[AuditJob(tags: ['payments'])]
class ProcessPayment implements ShouldQueue { ... }
```

**Or enable globally for all jobs:**

```env
RECORDKEEPER_JOBS=true
```

<details>
<summary><strong>AuditsJob trait — overridable methods</strong></summary>

| Method | Default | Description |
| --- | --- | --- |
| `auditJobTags()` | `[]` | Tags for this job's audits |
| `shouldAuditQueued()` | `true` | Track when job is dispatched |
| `shouldAuditProcessing()` | `true` | Track when job starts processing |
| `shouldAuditProcessed()` | `true` | Track when job completes |
| `shouldAuditFailed()` | `true` | Track when job fails |

</details>

---

### Command Tracking

Track Artisan command execution with duration, memory usage, exit code, and optional anomaly detection. Anomaly detection flags runs where duration or audit count significantly exceeds the historical average — useful for catching runaway commands.

```php
use LaraArabDev\Recordkeeper\Attributes\AuditCommand;

#[AuditCommand(tags: ['maintenance'])]
class PruneInactiveUsers extends Command { ... }
```

Or enable globally: `RECORDKEEPER_COMMANDS=true`. Long-running system commands (`schedule:run`, `queue:work`, `horizon:work`, etc.) are excluded by default.

<details>
<summary><strong>Command metrics configuration</strong></summary>

```php
'commands' => [
    'enabled' => env('RECORDKEEPER_COMMANDS', false),
    'exclude' => ['schedule:run', 'queue:work', ...],
    'metrics' => [
        'memory' => true,              // track peak memory
        'audit_count' => true,         // count audits during command
        'anomaly' => false,            // enable anomaly detection
        'anomaly_multiplier' => 2.0,   // flag if > 2x average
        'anomaly_min_runs' => 5,       // minimum runs before detection
    ],
],
```

</details>

---

### Event Tracking

Capture application events with optional payload. Useful for tracking domain events like `OrderShipped`, `UserRegistered`, or `PaymentFailed` alongside your model and route audits.

```php
use LaraArabDev\Recordkeeper\Attributes\AuditEvent;

#[AuditEvent(capturePayload: true, tags: ['shipping'])]
class OrderShipped { ... }
```

You can also register events to track in your config's `listen` array, or enable globally with `RECORDKEEPER_EVENTS=true`. Framework events (Illuminate namespace) are always skipped automatically.

---

### Privacy & Data Protection

Sensitive data is transformed **before** it reaches the audit store — never stored in plaintext. Recordkeeper provides three layers of protection that work together:

| Layer | How | Recoverable? | Example |
| --- | --- | --- | --- |
| **Global exclusion** | Fields listed in `privacy.global_exclude` are never stored at all | N/A | `password`, `remember_token` |
| **Auto-redaction** | Fields matching `privacy.sensitive_patterns` are replaced with `***` | No | Any field containing `secret`, `token`, `cvv`, `ssn`, `iban` |
| **Explicit redaction** | `#[Redact('field')]` attribute on your model | No | `#[Redact('date_of_birth')]` |
| **Encryption** | `#[Encrypt('field')]` attribute — AES-encrypted in storage | Yes — auto-decrypted on rollback | `#[Encrypt('national_id')]` |

```php
#[Redact('ssn', 'date_of_birth')]
#[Encrypt('api_secret')]
class Patient extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

Auto-redaction works out of the box with no configuration. The default patterns cover the most common sensitive fields. You can customize the pattern list and the redaction mask (`***` by default) in your config.

---

### Rollback

Undo any audited model change — single or batch — with an optional dry-run preview so you can see exactly what will change before committing.

```php
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;

// Preview what will change (nothing is modified)
$preview = Recordkeeper::rollback($auditId, dryRun: true);

// Apply the rollback
Recordkeeper::rollback($auditId);

// Roll back an entire batch — atomic, in reverse order
Recordkeeper::rollbackBatch('nightly-import');
```

| Original Event | Rollback Action |
| --- | --- |
| `created` | Model is force-deleted |
| `updated` | Old values are restored |
| `deleted` | Model is restored (supports SoftDeletes) |

Encrypted values are auto-decrypted before restoration. Auditing is automatically disabled during rollback to prevent recursive audit entries.

**Via Artisan:**

```bash
php artisan recordkeeper:rollback 1842 --dry-run    # preview
php artisan recordkeeper:rollback 1842 --yes         # apply
php artisan recordkeeper:rollback --batch=nightly     # batch rollback
```

---

### Batch Auditing

Group related changes under a single batch ID for atomic rollback. When you roll back a batch, every audit in the group is reverted in reverse order within a database transaction.

```php
Recordkeeper::batch('nightly-import-2025-01', function () {
    $order1 = Order::create([...]);
    $order2 = Order::create([...]);
    $inventory->decrement('stock', 50);
    // All 3 audits share the same batch_id
});

// Later, undo the entire import in one call:
Recordkeeper::rollbackBatch('nightly-import-2025-01');
```

---

### Manual Logging

Record custom events outside the Eloquent or middleware flow. Useful for tracking business events, external interactions, or anything that doesn't fit neatly into model/route auditing.

```php
Recordkeeper::log('payment.gateway.timeout', context: [
    'gateway' => 'stripe',
    'attempt' => 3,
]);

Recordkeeper::log('export.triggered', subject: $order, context: [
    'format' => 'csv',
    'rows'   => 1500,
]);
```

---

### Querying Audits

Recordkeeper provides two ways to query your audit trail: Eloquent scopes for simple lookups and a fluent query builder for complex searches.

**Eloquent scopes:**

```php
use LaraArabDev\Recordkeeper\Models\Audit;

Audit::forModel('Order')->latest()->get();
Audit::forSubject($order)->get();
Audit::forActor($admin)->get();
Audit::forGuard('api')->get();
Audit::forBatch('nightly-import')->get();
Audit::rollbackable()->get();
Audit::routeHits()->whereDate('created_at', today())->get();
Audit::jobAudits()->latest()->get();
```

**Fluent query builder:**

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

<details>
<summary><strong>All query builder methods</strong></summary>

| Method | Description |
| --- | --- |
| `->model(string)` | Filter by model (short name or FQCN) |
| `->subjectId(int\|string)` | Filter by auditable ID |
| `->event(string\|array)` | Filter by event name(s) |
| `->actor(id, type?)` | Filter by user_id + optional user_type |
| `->actorType(string)` | Filter by actor type only |
| `->onlyAuthenticated()` | Exclude system/anonymous audits |
| `->guard(string)` | Filter by auth guard |
| `->tag(string\|array)` | Filter by tag(s) |
| `->batch(string)` | Filter by batch_id |
| `->between(from, until)` | Date range filter |
| `->since(from)` | Created after a date |
| `->search(string)` | Free-text search |
| `->rollbackable()` | Only model-change events |
| `->jobs()` | Only job lifecycle events |
| `->commands()` | Only command events |
| `->events()` | Only application events |
| `->latest()` | Order by newest first |
| `->limit(int)` | Limit results |
| `->offset(int)` | Offset for pagination |
| `->builder()` | Get the underlying Eloquent Builder |

</details>

---

### Artisan Commands

Eight commands for managing your audit trail from the terminal:

| Command | Description |
| --- | --- |
| `recordkeeper:install` | Publish config and migrations |
| `recordkeeper:search` | Search audits with rich filters (model, event, actor, tag, date range) |
| `recordkeeper:show {id}` | Display a single audit with color-coded before/after diff |
| `recordkeeper:tail` | Live-follow new audits in real time (like `tail -f`) |
| `recordkeeper:stats` | Dashboard with counts by event, top models, top actors |
| `recordkeeper:models` | List all auditable models with their configuration |
| `recordkeeper:prune` | Delete old records based on retention policy |
| `recordkeeper:rollback` | Revert a single audit or an entire batch |

```bash
php artisan recordkeeper:search --model=Order --event=updated --since="-7 days"
php artisan recordkeeper:show 1842
php artisan recordkeeper:tail --model=Order --guard=api
php artisan recordkeeper:stats --since="-30 days"
php artisan recordkeeper:prune --days=365 --dry-run
php artisan recordkeeper:rollback 1842 --dry-run
php artisan recordkeeper:rollback --batch=nightly-import --yes
php artisan recordkeeper:models --json
```

---

### Storage Drivers

Choose the storage backend that fits your needs. The default `database` driver works for most applications. Switch drivers with a single env var.

| Driver | Best For | Rollback | Queries |
| --- | --- | --- | --- |
| **database** | Full-featured auditing (default) | Yes | Yes |
| **redis** | High-throughput, temporary tracking | No | Limited |
| **log** | Observability and debugging | No | No |
| **null** | Tests and disabled environments | No | No |

```env
RECORDKEEPER_DRIVER=database
```

**Custom drivers:** Implement the `AuditDriver` contract and register via `AuditDriverManager::extend()`.

---

## 🔧 Customization

Recordkeeper is designed so you never *have* to customize — but when you want to, every aspect is configurable.

### Custom Actor Resolver

By default, Recordkeeper uses `auth()->user()`. Override this to resolve actors from multiple guards or custom sources:

```php
Recordkeeper::resolveActorUsing(function () {
    return auth()->guard('admin')->user()
        ?? auth()->guard('api')->user()
        ?? auth()->user();
});
```

### Context Enrichment

Attach extra metadata to audits — useful for tracking deployments, server identity, or business reasons for changes:

```php
// Global context (applies to all audits)
Recordkeeper::pushContext([
    'deployment' => config('app.version'),
    'server'     => gethostname(),
]);

// Per-model context (applies to the next audit on this instance)
$order->auditContext(['reason' => 'Customer requested change']);
$order->update(['status' => 'refunded']);
```

### Tags

Tag audits for easy filtering and grouping:

```php
// Via attribute (static, per-model)
#[Auditable(tags: ['billing', 'critical'])]
class Invoice extends Model { ... }

// At runtime (dynamic, scoped to current request)
Recordkeeper::withTags(['nightly-sync']);
```

### React to Audit Writes

Listen for the `ChangeRecorded` event to trigger actions after any audit is written:

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

### Async Queue Writes

For high-traffic applications, offload audit writes to a background queue:

```env
RECORDKEEPER_QUEUE=true
RECORDKEEPER_QUEUE_NAME=audits
```

Run a dedicated worker for audit writes so they don't compete with your application jobs:

```bash
php artisan queue:work --queue=audits
```

---

## 📋 Configuration Reference

<details>
<summary><strong>Full configuration table</strong></summary>

| Key | Default | Description |
| --- | --- | --- |
| **General** | | |
| `enabled` | `true` | Global on/off switch |
| `events` | `['created','updated','deleted','restored']` | Default model events to track |
| `strict` | `false` | Throw exceptions on audit write failure (enable in tests) |
| `driver` | `'database'` | Storage backend: `database`, `redis`, `log`, `null` |
| **Privacy** | | |
| `privacy.mode` | `'redact'` | `redact` / `encrypt` / `off` |
| `privacy.mask` | `'***'` | Redaction replacement string |
| `privacy.sensitive_patterns` | `['password','secret','token','api_key','authorization','card','cvv','ssn','iban']` | Auto-redacted field name patterns |
| `privacy.global_exclude` | `['password','remember_token']` | Fields never stored in any audit |
| **Rollback** | | |
| `rollback.enabled` | `true` | Enable rollback feature |
| `rollback.permission` | `'rollback_audits'` | Required permission gate |
| `rollback.restore_deleted` | `true` | Restore soft-deleted models on rollback |
| `rollback.track` | `true` | Record an audit entry for the rollback itself |
| **Retention** | | |
| `retention.default_days` | `0` | Auto-prune after N days (0 = keep forever) |
| `retention.per_model` | `[]` | Per-model overrides: `['App\Models\Order' => 90]` |
| **Routes (incoming)** | | |
| `routes.enabled` | `false` | Audit all web/api routes globally |
| `routes.web` | `true` | Include web routes in global auditing |
| `routes.api` | `true` | Include API routes in global auditing |
| `routes.exclude` | `['horizon/*','telescope/*',...]` | URL patterns to skip |
| `routes.body` | `false` | Capture request body |
| `routes.sample` | `1.0` | Sampling rate (0.0 – 1.0) |
| `routes.tag` | `null` | Tag for all globally-audited routes |
| **Queue** | | |
| `queue.enabled` | `false` | Write audits asynchronously via queue |
| `queue.connection` | `null` | Queue connection name |
| `queue.queue` | `'audits'` | Queue name |
| **Jobs** | | |
| `jobs.enabled` | `false` | Track job lifecycle globally |
| `jobs.exclude` | `[]` | Job classes to skip |
| **Commands** | | |
| `commands.enabled` | `false` | Track Artisan commands globally |
| `commands.exclude` | `['schedule:run','queue:work',...]` | Commands to skip |
| `commands.metrics.memory` | `true` | Track peak memory usage |
| `commands.metrics.audit_count` | `true` | Count audits created during command |
| `commands.metrics.anomaly` | `false` | Enable anomaly detection |
| `commands.metrics.anomaly_multiplier` | `2.0` | Flag if metric > N × average |
| `commands.metrics.anomaly_min_runs` | `5` | Minimum runs before detection activates |
| **HTTP (outbound)** | | |
| `http.enabled` | `false` | Track outbound HTTP calls |
| `http.mode` | `'auto'` | `auto` = all, `manual` = opt-in only |
| `http.queue` | `false` | Persist HTTP records asynchronously |
| `http.capture_headers` | `false` | Store request/response headers |
| `http.capture_body` | `false` | Store response body |
| `http.body_limit` | `1000` | Truncate response body at N characters |
| `http.exclude_hosts` | `[]` | Hosts to skip entirely |
| **Events** | | |
| `events_tracking.enabled` | `false` | Track application events globally |
| `events_tracking.exclude` | `[]` | Event classes to skip |
| `listen` | `[]` | Specific event classes to track (when global is disabled) |
| **Other** | | |
| `guards` | `['web' => true, 'api' => true]` | Guards to resolve actors from |
| `discovery.paths` | `['app/Models']` | Paths for model discovery command |
| `pipeline` | `[]` | Custom pipeline stages for audit processing |

</details>

---

## ❓ FAQ

<details>
<summary><strong>Does this require a lot of configuration?</strong></summary>

No. Install, migrate, add the `AuditsChanges` trait — that's it. Recordkeeper works with sensible defaults out of the box. It automatically tracks CRUD events, discovers fields from `$fillable` and `$casts`, excludes passwords, and auto-redacts sensitive patterns. You only configure things when you want to change the defaults.
</details>

<details>
<summary><strong>Will this slow down my app?</strong></summary>

No. A synchronous model audit adds about 1-2ms. For high-traffic apps, enable `queue.enabled` to drop that to under 0.5ms (the audit is written asynchronously). You can also use `sample: 0.1` on busy routes to only audit 10% of requests, or use the `redis` driver for maximum write throughput. Features that are disabled have zero overhead — they don't even register their event listeners.
</details>

<details>
<summary><strong>Is it compatible with existing laravel-auditing?</strong></summary>

Yes. Recordkeeper installs `owen-it/laravel-auditing` as a dependency and builds on top of it. Your existing auditable models keep working. To unlock Recordkeeper's extra features (privacy protection, attributes, rollback, CLI tools), replace the `Auditable` trait with `AuditsChanges` on any model.
</details>

<details>
<summary><strong>What happens if an audit write fails?</strong></summary>

By default, failures are logged silently and your application continues normally — an audit failure should never break your app. If you want strict behavior (useful in tests), set `RECORDKEEPER_STRICT=true` and failures will throw exceptions instead.
</details>

<details>
<summary><strong>How do Attributes, Traits, and Config interact?</strong></summary>

Priority is **Attribute > Trait > Config**. The most specific configuration wins. For example, if your global config sets `retentionDays: 365` but a model has `#[Auditable(retentionDays: 90)]`, that model uses 90 days. If neither an attribute nor a trait specifies a value, the global config is used. This lets you set sensible defaults globally and override only where needed.
</details>

<details>
<summary><strong>Can I write audits asynchronously?</strong></summary>

Yes. Enable `queue.enabled` and audits are dispatched to a dedicated `audits` queue. Run a separate worker for isolation:

```bash
php artisan queue:work --queue=default    # your app jobs
php artisan queue:work --queue=audits     # audit writes
```

For full isolation, set `RECORDKEEPER_QUEUE_CONNECTION=redis-audits` to use a different queue connection entirely.
</details>

<details>
<summary><strong>How does sensitive data protection work?</strong></summary>

Three layers: (1) **Global exclusion** — fields like `password` and `remember_token` are never stored at all. (2) **Auto-redaction** — any field whose name contains patterns like `secret`, `token`, `cvv`, `ssn`, or `iban` is automatically replaced with `***`. (3) **Explicit encryption** — use `#[Encrypt('field')]` for fields that need to be recoverable on rollback (AES-encrypted in storage). All of this happens before data reaches the audit store.
</details>

<details>
<summary><strong>Does it support multi-tenancy?</strong></summary>

Yes. Use tags and context enrichment to scope audits per tenant:

```php
Recordkeeper::withTags(['tenant:'.$tenant->id]);
Audit::forTag('tenant:42')->get();
```

You can also use a custom actor resolver to track tenant-specific users across different auth guards.
</details>

<details>
<summary><strong>What about audit storage growth?</strong></summary>

Several options: (1) Set `retention.default_days` to auto-expire old audits and schedule `recordkeeper:prune` daily. (2) Set per-model retention with `#[Auditable(retentionDays: 90)]`. (3) Use the `redis` driver with a TTL for temporary audit data. (4) Use `sample` on high-traffic routes to reduce volume. (5) Use `threshold` on models to cap audits per instance.
</details>

<details>
<summary><strong>Can I track incoming webhooks?</strong></summary>

Yes. Incoming webhooks are just regular HTTP requests hitting your routes. Add `audit.api` middleware to your webhook routes, or enable `RECORDKEEPER_ROUTES=true` to audit all routes globally. No special webhook-specific setup is needed.
</details>

<details>
<summary><strong>Can I track outgoing webhooks?</strong></summary>

Yes. If your app sends webhooks using Laravel's `Http::` facade, they're tracked automatically when `RECORDKEEPER_HTTP=true`. The URL, status code, duration, and any failures are recorded in the `audit_http_requests` table.
</details>

<details>
<summary><strong>How do I search audits from the terminal?</strong></summary>

Use `recordkeeper:search` with filters:

```bash
php artisan recordkeeper:search --model=Order --event=updated --since="-7 days" --tag=billing
```

For real-time monitoring, use `recordkeeper:tail` which live-follows new audits as they're written.
</details>

<details>
<summary><strong>Can I use a custom storage driver?</strong></summary>

Yes. Implement the `AuditDriver` contract and register it:

```php
use LaraArabDev\Recordkeeper\Support\AuditDriverManager;

app(AuditDriverManager::class)->extend('custom', fn ($app) => new MyCustomDriver);
```

Then set `RECORDKEEPER_DRIVER=custom` in your `.env`.
</details>

<details>
<summary><strong>What's the difference between route auditing and model auditing?</strong></summary>

Model auditing tracks *data changes* — what fields were modified, old vs new values, who changed them. Route auditing tracks *HTTP requests* — who accessed what endpoint, response time, status code. They complement each other: route auditing tells you "User 42 hit `POST /api/orders`", while model auditing tells you "User 42 created Order #789 with these field values."
</details>

---

## ⚡ Performance

| Operation | Overhead |
| --- | --- |
| Model audit (sync) | ~1-2ms |
| Model audit (async) | < 0.5ms |
| Route middleware | ~1-2ms |
| Job/Command/Event | ~0.5-1ms |
| HTTP tracking | ~0.3ms |

**Why it's fast:** opt-in architecture means disabled features have zero overhead, early exits on every check, no reflection in hot paths, lightweight DTOs for data transfer, and sampling support for high-traffic routes.

| Scenario | Recommendation |
| --- | --- |
| High-traffic API (1000+ req/s) | Enable `queue.enabled`, use `sample: 0.1` |
| Write-heavy app | Use `redis` driver |
| Testing | Use `null` driver |
| Large audit tables | Set `retention.default_days`, schedule `recordkeeper:prune` daily |

```bash
# Run benchmarks
composer bench           # full suite
composer bench:quick     # fast run
```

---

## 🧪 Testing & Quality

```bash
composer test           # Pest test suite
composer test:coverage  # With code coverage
composer analyse        # PHPStan (level 6)
composer format         # Laravel Pint
composer infection      # Mutation testing
```

| PHP | Laravel | Status |
| --- | --- | --- |
| 8.2 | 11 / 12 | Supported |
| 8.3 | 11 / 12 | Supported |
| 8.4 | 11 / 12 | Supported |

---

## 🔐 Security

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

---

## 📄 Credits & License

- [LaraArabDev](https://github.com/LaraArabDev)
- [All Contributors](../../contributors)

MIT License — see [LICENSE](LICENSE) for details.

---

## 🇸🇦 العربية

<p align="center">
    <img src="art/banner-ar.svg" alt="Recordkeeper بانر" style="width: 100%; max-width: 800px;">
</p>

<div dir="rtl" align="right">

### ما هو Recordkeeper؟

**Recordkeeper** حزمة Laravel تمنح تطبيقك سجل تدقيق كامل بدون أي إعداد. ثبّت الحزمة، أضف trait واحد، وكل إنشاء وتعديل وحذف واستعادة يُسجّل تلقائياً. مبنية على [owen-it/laravel-auditing](https://laravel-auditing.com/) وتوسّعها بشكل كبير.

لكن تتبع الموديلات هو البداية فقط. يمكن لـ Recordkeeper أيضاً تتبع:

- كل طلب HTTP يصل لمساراتك (من وصل لأي نقطة نهاية، أوقات الاستجابة، أكواد الحالة)
- كل استدعاء API صادر من تطبيقك (مدفوعات Stripe، إرسال webhooks، خدمات خارجية)
- كل Job من الإرسال حتى الاكتمال أو الفشل
- كل أمر Artisan مع المدة واستهلاك الذاكرة وكشف الشذوذ
- كل حدث تطبيق مع التقاط البيانات الكاملة

**أنت تختار ما تراقبه.** تريد تدقيق الموديلات فقط؟ أضف الـ trait. تحتاج رؤية كاملة لـ API؟ فعّل تدقيق المسارات. تريد تتبع HTTP الصادر والـ Jobs والأوامر والأحداث أيضاً؟ فعّلها واحدة تلو الأخرى. أو فعّل كل شيء مرة واحدة. كل ميزة مستقلة — فعّل المزيج الذي يناسب تطبيقك بالضبط، والميزات المعطّلة ليس لها أي تأثير على الأداء.

تأتي أيضاً مع **حماية خصوصية مدمجة** (إخفاء تلقائي لكلمات المرور، تشفير الحقول الحساسة)، **تراجع بضغطة واحدة** (استرجاع أي تغيير مع معاينة قبل التطبيق)، و**8 أوامر Artisan** للبحث والتتبع وإدارة سجل التدقيق من الطرفية.

**الفلسفة:** تعمل فوراً بإعدادات ذكية — خصّص فقط عندما تحتاج.

### التثبيت

```bash
composer require laraarabdev/recordkeeper
php artisan recordkeeper:install
php artisan migrate
```

### البداية السريعة

```php
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;

class Order extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;  // يتتبع الإنشاء والتعديل والحذف والاستعادة تلقائياً
}
```

### الميزات الرئيسية

| الميزة | الوصف | التفعيل |
| --- | --- | --- |
| **تتبع الموديلات** | يسجّل كل تغيير مع القيم القديمة والجديدة | أضف `AuditsChanges` trait |
| **تدقيق المسارات** | يتتبع كل طلب HTTP وارد مع المستخدم والتوقيت | `audit` middleware أو `RECORDKEEPER_ROUTES=true` |
| **تتبع HTTP الصادر** | يسجّل كل استدعاء API خارجي عبر `Http::` | `RECORDKEEPER_HTTP=true` |
| **تتبع الـ Jobs** | يتابع دورة حياة الـ Job كاملة | `AuditsJob` trait أو `RECORDKEEPER_JOBS=true` |
| **تدقيق الأوامر** | يتتبع أوامر Artisan مع المدة والذاكرة | `#[AuditCommand]` أو `RECORDKEEPER_COMMANDS=true` |
| **تتبع الأحداث** | يلتقط أحداث التطبيق مع البيانات | `#[AuditEvent]` أو `RECORDKEEPER_EVENTS=true` |
| **حماية الخصوصية** | إخفاء تلقائي للبيانات الحساسة + تشفير AES | يعمل تلقائياً |
| **التراجع** | استرجاع أي تغيير فردي أو مجموعة مع معاينة | `Recordkeeper::rollback($id)` |
| **8 أوامر Artisan** | بحث، تتبع مباشر، إحصائيات، تراجع، تنظيف | من سطر الأوامر |
| **4 محركات تخزين** | قاعدة بيانات، Redis، سجلات، Null | `RECORDKEEPER_DRIVER=...` |

### حماية البيانات

```php
#[Redact('cvv')]           // يُستبدل بـ *** — غير قابل للاسترجاع
#[Encrypt('national_id')]  // يُشفّر بـ AES — يُفك تلقائياً عند التراجع
class Payment extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use AuditsChanges;
}
```

البيانات الحساسة تُعالج **قبل** وصولها لمخزن التدقيق. كلمات المرور والتوكنات وأرقام البطاقات تُخفى تلقائياً بدون إعداد.

### التراجع عن التغييرات

```php
Recordkeeper::rollback($auditId, dryRun: true);  // معاينة
Recordkeeper::rollback($auditId);                 // تطبيق
Recordkeeper::rollbackBatch('nightly-import');     // تراجع مجموعة
```

### ثلاث طرق للإعداد

```
config/recordkeeper.php   →  إعدادات عامة (كل الموديلات)
       ↓
#[Auditable(...)]         →  إعدادات لكل موديل عبر Attributes
       ↓
Traits + Methods          →  تحكم دقيق عبر methods قابلة للتعديل
```

**الأولوية:** Attribute > Trait > Config — الأكثر تحديداً يفوز دائماً.

### أوامر Artisan

```bash
php artisan recordkeeper:search --model=Order --event=updated
php artisan recordkeeper:show 1842
php artisan recordkeeper:tail --model=Order
php artisan recordkeeper:stats --since="-30 days"
php artisan recordkeeper:rollback 1842 --dry-run
php artisan recordkeeper:prune --days=365 --yes
php artisan recordkeeper:models
```

### مقارنة مع laravel-auditing

| الميزة | laravel-auditing فقط | + Recordkeeper |
| --- | --- | --- |
| تتبع الموديلات | نعم | نعم |
| تدقيق المسارات وAPI | لا | **نعم** |
| تتبع HTTP الصادر | لا | **نعم** |
| تتبع Jobs/أوامر/أحداث | لا | **نعم** |
| الإعداد | مصفوفات PHP | **PHP 8 Attributes + Traits + Config** |
| حماية الخصوصية | أساسية | **إخفاء تلقائي + تشفير AES** |
| التراجع | يدوي | **بضغطة واحدة مع معاينة** |
| أدوات سطر الأوامر | لا | **8 أوامر** |
| محركات التخزين | قاعدة بيانات فقط | **4 محركات** |

---

للتوثيق الكامل وأمثلة الكود التفصيلية، راجع الأقسام الإنجليزية أعلاه.

</div>
