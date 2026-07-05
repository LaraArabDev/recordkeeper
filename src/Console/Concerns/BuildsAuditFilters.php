<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console\Concerns;

use LaraArabDev\Recordkeeper\Support\AuditQuery;

/**
 * Shared trait for building AuditQuery from common CLI options.
 *
 * Commands using this trait should define the relevant options:
 * --model=, --model-id=, --tag=, --event=*, --since=, --until=, --batch=
 */
trait BuildsAuditFilters
{
    /**
     * Build an AuditQuery from the command's CLI options.
     *
     * Supports: --model, --model-id, --tag, --event, --since, --until, --batch, --user, --guard, --q.
     * Options not defined on the command are safely skipped via hasOption() guards.
     */
    protected function buildAuditQuery(): AuditQuery
    {
        $query = new AuditQuery;

        if ($model = $this->option('model')) {
            $query->model((string) $model);
        }

        if ($this->hasOption('model-id') && ($modelId = $this->option('model-id'))) {
            $query->subjectId((string) $modelId);
        }

        if ($this->hasOption('tag') && ($tag = $this->option('tag'))) {
            $query->tag((string) $tag);
        }

        $events = $this->hasOption('event') ? $this->option('event') : [];
        if (! empty($events)) {
            $query->event($events);
        }

        if ($this->hasOption('since') && ($since = $this->option('since'))) {
            $parsed = strtotime((string) $since);
            $query->since($parsed !== false ? date('Y-m-d H:i:s', $parsed) : (string) $since);
        }

        if ($this->hasOption('until') && ($until = $this->option('until'))) {
            $parsed = strtotime((string) $until);
            $query->until($parsed !== false ? date('Y-m-d H:i:s', $parsed) : (string) $until);
        }

        if ($this->hasOption('batch') && ($batch = $this->option('batch'))) {
            $query->batch((string) $batch);
        }

        if ($this->hasOption('user') && ($user = $this->option('user'))) {
            $query->actor((string) $user);
        }

        if ($this->hasOption('guard') && ($guard = $this->option('guard'))) {
            $query->guard((string) $guard);
        }

        if ($this->hasOption('q') && ($q = $this->option('q'))) {
            $query->search((string) $q);
        }

        return $query;
    }

    /**
     * Determine whether at least one filter option was provided.
     */
    protected function hasAnyFilter(): bool
    {
        $checks = ['model', 'model-id', 'tag', 'since', 'until', 'batch'];

        foreach ($checks as $opt) {
            if ($this->hasOption($opt) && ! empty($this->option($opt))) {
                return true;
            }
        }

        $events = $this->hasOption('event') ? $this->option('event') : [];

        return ! empty($events);
    }
}
