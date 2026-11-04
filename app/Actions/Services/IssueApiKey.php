<?php

namespace App\Actions\Services;

use App\Models\ProjectService;
use Illuminate\Support\Str;

class IssueApiKey
{
    /**
     * Give a project's service a fresh key for Baydaq's own backends to report usage with. The
     * plaintext is returned once; only the encrypted copy is kept. The key carries the service id
     * so a request can be matched without decrypting every key: bsk_{id}_{secret}.
     */
    public function handle(ProjectService $projectService): string
    {
        $key = 'bsk_'.$projectService->id.'_'.Str::random(40);

        $projectService->forceFill(['api_key' => $key, 'api_key_issued_at' => now()])->save();

        return $key;
    }

    /** The service a key belongs to, or null when it does not match. */
    public static function resolve(?string $key): ?ProjectService
    {
        if ($key === null || ! preg_match('/^bsk_(\d+)_[A-Za-z0-9]{40}$/', $key, $matches)) {
            return null;
        }

        $projectService = ProjectService::query()->find((int) $matches[1]);

        return $projectService !== null && $projectService->api_key !== null && hash_equals($projectService->api_key, $key) ? $projectService : null;
    }
}
