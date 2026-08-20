<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\DataObjects;

/**
 * Immutable value object carrying all data needed to persist a single audit entry.
 */
final readonly class AuditPayload
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $event,
        public string $auditableType,
        public int|string|null $auditableId,
        public array $oldValues,
        public array $newValues,
        public ?string $userType = null,
        public int|string|null $userId = null,
        public ?string $url = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $tags = null,
        public ?string $batchId = null,
        public array $context = [],
        public ?string $guard = null,
        public ?string $source = null,
    ) {}

    /**
     * Reconstruct a payload from its snake_case array representation.
     *
     * @param  array<string, mixed>  $data  The snake_case keyed array of audit data.
     * @return self The reconstructed AuditPayload instance.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            event: $data['event'],
            auditableType: $data['auditable_type'],
            auditableId: $data['auditable_id'] ?? null,
            oldValues: $data['old_values'] ?? [],
            newValues: $data['new_values'] ?? [],
            userType: $data['user_type'] ?? null,
            userId: $data['user_id'] ?? null,
            url: $data['url'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            tags: $data['tags'] ?? null,
            batchId: $data['batch_id'] ?? null,
            context: $data['context'] ?? [],
            guard: $data['guard'] ?? null,
            source: $data['source'] ?? null,
        );
    }

    /**
     * Convert the payload to its snake_case array representation.
     *
     * @return array{event: string, auditable_type: string, auditable_id: int|string|null, old_values: array<string, mixed>, new_values: array<string, mixed>, user_type: ?string, user_id: int|string|null, url: ?string, ip_address: ?string, user_agent: ?string, tags: ?string, batch_id: ?string, context: array<string, mixed>, guard: ?string, source: ?string}
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'user_type' => $this->userType,
            'user_id' => $this->userId,
            'url' => $this->url,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'tags' => $this->tags,
            'batch_id' => $this->batchId,
            'context' => $this->context,
            'guard' => $this->guard,
            'source' => $this->source,
        ];
    }
}
