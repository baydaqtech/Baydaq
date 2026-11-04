<?php

namespace App\Http\Resources\Admin;

use App\Actions\Uploads\StoreLogo;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'name' => $this->client->localized('name')]),
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'type' => $this->type->value,
            'relationship' => $this->relationship?->value,
            'partnership_share' => $this->partnership_share,
            'status' => $this->status->value,
            'progress' => $this->progress,
            'started_at' => $this->started_at?->toDateString(),
            'expected_launch_at' => $this->expected_launch_at?->toDateString(),
            'launched_at' => $this->launched_at?->toDateString(),
            'drive_url' => $this->drive_url,
            'repo_url' => $this->repo_url,
            'app_url' => $this->app_url,
            'logo_url' => StoreLogo::url($this->logo),
            'milestones' => $this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'due_date' => $milestone->due_date?->toDateString(),
                'done' => $milestone->done_at !== null,
            ])),
            'resources' => $this->whenLoaded('resources', fn () => $this->resources->map->only(['id', 'name', 'role', 'notes'])),
            'files' => $this->whenLoaded('files', fn () => $this->files->map(fn ($file) => [
                'id' => $file->id,
                'title' => $file->title,
                'url' => $file->url,
                'category' => $file->category->value,
            ])),
            'releases' => $this->whenLoaded('releases', fn () => $this->releases->map(fn ($release) => [
                'id' => $release->id,
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
                'requested_by' => $changeRequest->user?->name,
                'invoice_id' => $changeRequest->invoice_id,
            ])),
            'services' => ProjectServiceResource::collection($this->whenLoaded('projectServices')),
        ];
    }
}
