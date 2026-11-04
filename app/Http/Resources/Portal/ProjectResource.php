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
            'milestones' => $this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($milestone) => [
                'title' => $milestone->title,
                'description' => $milestone->description,
                'due_date' => $milestone->due_date?->toDateString(),
                'done_at' => $milestone->done_at?->toDateString(),
            ])),
            'releases' => $this->whenLoaded('releases', fn () => $this->releases->map(fn ($release) => [
                'version' => $release->version,
                'platform' => $release->platform->value,
                'channel' => $release->channel->value,
                'url' => $release->url,
                'notes' => $release->notes,
                'released_at' => $release->released_at->toDateString(),
            ])),
            'change_requests' => $this->whenLoaded('changeRequests', fn () => $this->changeRequests->map(fn ($changeRequest) => [
                'id' => $changeRequest->id,
                'title' => $changeRequest->title,
                'description' => $changeRequest->description,
                'status' => $changeRequest->status->value,
                'quote_amount' => $changeRequest->quote_amount,
                'quote_days' => $changeRequest->quote_days,
                'quote_note' => $changeRequest->quote_note,
                'created_at' => $changeRequest->created_at->toIso8601String(),
                'decided_at' => $changeRequest->decided_at?->toIso8601String(),
            ])),
            'files' => $this->whenLoaded('files', fn () => $this->files->map(fn ($file) => [
                'title' => $file->title,
                'url' => $file->url,
                'category' => $file->category->value,
            ])),
        ];
    }
}
