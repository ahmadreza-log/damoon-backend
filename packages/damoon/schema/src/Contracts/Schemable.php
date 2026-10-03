<?php

namespace Damoon\Schema\Contracts;

/**
 * Something that carries schema.org structured data: a content model, or the site settings.
 *
 * The HasSchemas trait implements everything except blueprints; a model overrides the
 * rest when it has the data. Each schema item is {type, active, fields}, where type is a key
 * of Vocabulary::types and fields holds values that may contain placeholders.
 *
 * These methods sit on Eloquent models, so a model property with the same name as one of them
 * (a column such as markup or items) would make Eloquent treat the method as a relation; the
 * names here are chosen to stay clear of usual column names.
 *
 * Extending:
 * - Values for a new placeholder come from placeholders, keyed like '{title}'.
 */
interface Schemable
{
    /**
     * The schema types a new record starts with, such as ['Article', 'BreadcrumbList'].
     *
     * @return list<string>
     */
    public static function blueprints(): array;

    /**
     * The record's schema items: the stored ones, or the defaults of blueprints when none are stored.
     *
     * @return list<array{type: string, active?: bool, fields?: array<string, mixed>}>
     */
    public function schemata(): array;

    /**
     * Values of the record placeholders, such as '{title}' => 'نصب پمپ'.
     *
     * @return array<string, string>
     */
    public function placeholders(): array;

    /**
     * The address of the list this record sits in, for the breadcrumb step after home.
     */
    public function section(): ?string;

    /**
     * Breadcrumb steps between the section and the record, the farthest first, as [name, address].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function crumbs(): array;

    /**
     * The record's own questions for FAQPage, as {question, answer}.
     *
     * @return list<array{question?: string, answer?: string}>
     */
    public function faqs(): array;

    /**
     * The active schema items rendered as JSON-LD documents.
     *
     * @return list<array<string, mixed>>
     */
    public function structured(): array;
}
