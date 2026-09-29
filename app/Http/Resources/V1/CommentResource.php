<?php

namespace App\Http\Resources\V1;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One comment as the site shows it, with its approved replies when they are loaded.
 *
 * The writer's email, IP, and browser are never sent. staff is true for an answer
 * written by the site's team in the panel, so the site can mark it.
 *
 * Extending:
 * - A new public Comment column is one more key here; keep private data out.
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'body' => $this->body,
            'staff' => $this->user_id !== null,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
