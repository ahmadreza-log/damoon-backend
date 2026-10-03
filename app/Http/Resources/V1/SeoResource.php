<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Rankbeam\Seo\Data\SEOData;

/**
 * The resolved SEO of an article or page, for the head of its site page.
 *
 * Values come from HasSEO::seoData: what staff typed in the SEO box, or the fallbacks in
 * App\Models\Concerns\Meta when a field was left empty. canonical is the record's address on the
 * public website, built from the general settings. The social image is made absolute.
 * schema is a list of JSON-LD documents: the active site-wide schemas from the general settings,
 * then the record's own from its schema box, unless the record stores a schema_jsonld of its own.
 *
 * Extending:
 * - Another SEOData field is one more key here.
 *
 * @property SEOData $resource
 */
class SeoResource extends JsonResource
{
    /**
     * Title, description, canonical address, robots, social image, keywords, and structured data.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        $image = $data->ogImage;

        return [
            'title' => $data->title,
            'description' => $data->description,
            'canonical' => $data->canonical,
            'robots' => $data->robots,
            'image' => is_string($image) && $image !== '' ? url($image) : null,
            'keywords' => $data->getKeywordStrings(),
            'schema' => (array) $data->schemaJsonld,
        ];
    }
}
