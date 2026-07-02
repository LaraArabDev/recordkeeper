<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Benchmarks;

use LaraArabDev\Recordkeeper\Attributes\Auditable;
use LaraArabDev\Recordkeeper\Attributes\Redact;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Groups(['attribute-resolver', 'pure-php'])]
#[BeforeMethods(['setUp'])]
#[Warmup(3)]
#[Iterations(5)]
final class AttributeResolverBench extends BenchCase
{
    #[Revs(5000)]
    public function benchResolveCachedModel(): void
    {
        AttributeResolver::resolve(PlainModel::class);
    }

    #[Revs(200)]
    public function benchResolveUncachedModel(): void
    {
        AttributeResolver::clearCache();
        AttributeResolver::resolve(PlainModel::class);
    }

    #[Revs(200)]
    public function benchResolveUncachedModelWithAttributes(): void
    {
        AttributeResolver::clearCache();
        AttributeResolver::resolve(DecoratedModel::class);
    }

    #[Revs(200)]
    public function benchResolveUncachedModelWithRedact(): void
    {
        AttributeResolver::clearCache();
        AttributeResolver::resolve(SensitiveModel::class);
    }
}

class PlainModel
{
    public function getFillable(): array
    {
        return ['name', 'email', 'status', 'total'];
    }

    public function getCasts(): array
    {
        return ['total' => 'decimal:2'];
    }
}

#[Auditable(events: ['created', 'updated'], tags: ['order'])]
class DecoratedModel
{
    public function getFillable(): array
    {
        return ['name', 'email', 'status', 'total'];
    }

    public function getCasts(): array
    {
        return [];
    }
}

#[Redact('password', 'api_token', 'secret_key')]
class SensitiveModel
{
    public function getFillable(): array
    {
        return ['name', 'email', 'password', 'api_token', 'secret_key'];
    }

    public function getCasts(): array
    {
        return [];
    }
}
