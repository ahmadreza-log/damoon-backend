<?php

namespace App\Http\Resources\V1;

use App\Models\Form;
use App\Support\Fields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One form with everything a site needs to draw and send it.
 *
 * Besides the list fields: the submit button text, the address to post the answers to,
 * whether the form needs multipart/form-data because it has a file field, the name of the
 * hidden input to send empty against bots, and the fields from Form::definition.
 *
 * Extending:
 * - A new field setting belongs in App\Support\Fields::definition, which fills fields here.
 *
 * @mixin Form
 */
class FormDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fields = $this->definition();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'button' => $this->label(),
            'action' => url('v1/forms/'.$this->slug),
            'multipart' => in_array('file', array_column($fields, 'type'), true),
            'honeypot' => Fields::HONEYPOT,
            'fields' => $fields,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
