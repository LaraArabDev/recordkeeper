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
    /**
     * Create a new Audit attribute instance.
     *
     * @param  string|null  $tag  Optional tag to apply to the route audit.
     * @param  bool  $body  Whether to capture the request body.
     * @param  bool  $response  Whether to capture the response body.
     * @param  float  $sample  Sampling rate between 0.0 and 1.0.
     */
    public function __construct(
        public readonly ?string $tag = null,
        public readonly bool $body = false,
        public readonly bool $response = false,
        public readonly float $sample = 1.0,
    ) {}
}
