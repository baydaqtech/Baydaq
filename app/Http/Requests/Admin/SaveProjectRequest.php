<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectRelationship;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    /**
     * Access is checked by the route's can:projects.manage middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $poweredByBaydaq = $this->input('type') === ProjectType::PoweredByBaydaq->value;

        return [
            'client_id' => ['required', Rule::exists('clients', 'id')],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('projects', 'slug')->ignore($this->route('project'))],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            // Partnership vs product only applies to projects powered by Baydaq.
            'relationship' => $poweredByBaydaq ? ['required', Rule::enum(ProjectRelationship::class)] : ['exclude'],
            'partnership_share' => $poweredByBaydaq && $this->input('relationship') === ProjectRelationship::Partnership->value
                ? ['required', 'numeric', 'between:0,100']
                : ['exclude'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'progress' => ['required', 'integer', 'between:0,100'],
            'started_at' => ['nullable', 'date'],
            'expected_launch_at' => ['nullable', 'date'],
            'launched_at' => ['nullable', 'date'],
            'drive_url' => ['nullable', 'url', 'max:2048'],
            'repo_url' => ['nullable', 'url', 'max:2048'],
            'app_url' => ['nullable', 'url', 'max:2048'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('name_en'))]);
        }
    }

    /**
     * The project's columns: relationship and share are cleared when they no longer apply.
     *
     * @return array<string, mixed>
     */
    public function projectData(): array
    {
        return [
            'relationship' => null,
            'partnership_share' => null,
            ...$this->safe()->except('logo'),
        ];
    }
}
