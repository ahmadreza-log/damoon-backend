<?php

namespace App\Http\Resources\V1;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A form in a list: title, slug, and description.
 *
 * Extending:
 * - A field every list needs goes here; one only the detail needs goes in FormDetailResource.
 *
 * @mixin Form
 */
class FormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
