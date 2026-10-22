<?php

namespace App\Http\Resources\Portal;

use App\Actions\Uploads\StoreLogo;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project as its client sees it: no internal notes or keys.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
            'logo_url' => StoreLogo::url($this->logo),
            'type' => $this->type->value,
            'relationship' => $this->relationship?->value,
            'partnership_share' => $this->partnership_share,
            'status' => $this->status->value,
            'progress' => $this->progress,
            'started_at' => $this->started_at?->toDateString(),
            'expected_launch_at' => $this->expected_launch_at?->toDateString(),
            'launched_at' => $this->launched_at?->toDateString(),
            'drive_url' => $this->drive_url,
            'app_url' => $this->app_url,
            'services_count' => $this->whenCounted('projectServices'),
            'team' => $this->whenLoaded('resources', fn () => $this->resources->map->only(['name', 'role'])),
            'files' => $this->whenLoaded('files', fn () => $this->files->map(fn ($file) => [
                'title' => $file->title,
                'url' => $file->url,
                'category' => $file->category->value,
            ])),
        ];
    }
}
