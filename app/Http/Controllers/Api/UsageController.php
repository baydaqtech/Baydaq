<?php

namespace App\Http\Controllers\Api;

use App\Actions\Usage\RecordUsage;
use App\Actions\Usage\UsageSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Usage ingestion for the technical services (email, push, WhatsApp...): each backend posts how
 * many units it delivered for a project. Idempotency is per day: posting again adds to the day.
 */
class UsageController extends Controller
{
    public function store(Request $request, RecordUsage $record, UsageSummary $summary): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'meta' => ['nullable', 'array'],
        ]);

        $projectService = $request->attributes->get('projectService');
        $record->handle($projectService, $data['quantity'], $data['date'] ?? null, 'api', $data['meta'] ?? null);

        return response()->json($summary->handle($projectService->refresh()), 201);
    }
}
