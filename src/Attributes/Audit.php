<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Configure audit behaviour for a controller method (route-level auditing).
 */
final class Audit
{
    public function __construct(
        public readonly ?string $tag = null,
        public readonly bool $body = false,
        public readonly bool $response = false,
        public readonly float $sample = 1.0,
    ) {}
}
