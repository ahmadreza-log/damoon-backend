<?php

namespace Damoon\Schema\Concerns;

use Damoon\Schema\Schemas;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a model's schema items in its schemas column and renders them.
 *
 * A null column means the record was never edited, so the defaults of blueprints are used;
 * an empty list means every schema was removed on purpose. The model casts schemas to an
 * array and implements Schemable with its own blueprints.
 *
 * Extending:
 * - Override placeholders, section, crumbs, and faqs when the model has that data.
 *
 * @mixin Model
 */
trait HasSchemas
{
    /**
     * The stored schema items, or the defaults when none are stored.
     *
     * @return list<array{type: string, active?: bool, fields?: array<string, mixed>}>
     */
    public function schemata(): array
    {
        $stored = $this->getAttribute('schemas');

        return is_array($stored) ? array_values($stored) : Schemas::defaults(static::blueprints());
    }

    /**
     * The active items as JSON-LD documents, filled for this record.
     *
     * @return list<array<string, mixed>>
     */
    public function structured(): array
    {
        return Schemas::render($this->schemata(), $this);
    }

    /**
     * No record placeholders unless the model gives them.
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        return [];
    }

    /**
     * No section unless the model gives one.
     */
    public function section(): ?string
    {
        return null;
    }

    /**
     * No breadcrumb parents unless the model gives them.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function crumbs(): array
    {
        return [];
    }

    /**
     * No questions unless the model gives them.
     *
     * @return list<array{question?: string, answer?: string}>
     */
    public function faqs(): array
    {
        return [];
    }
}
