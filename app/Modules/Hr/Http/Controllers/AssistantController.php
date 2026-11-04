<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Assistant\AnswerHrQuestion;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Intelligence;
use App\Modules\Platform\Ports\IntelligenceUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The HR assistant page and its answers (H15). Each answer only sees what the asker's role
 * reaches in the current scope and branch filter.
 */
class AssistantController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function show(Intelligence $intelligence): Response
    {
        abort_unless($intelligence->isEnabled(), 404);

        return $this->suitePage('Hr/Assistant', [], ['hr']);
    }

    public function ask(Request $request, AnswerHrQuestion $answer, Intelligence $intelligence): JsonResponse
    {
        abort_unless($intelligence->isEnabled(), 404);

        $data = $request->validate([
            'conversation' => ['required', 'array', 'min:1', 'max:20'],
            'conversation.*.role' => ['required', 'in:user,assistant'],
            'conversation.*.content' => ['required', 'string', 'max:4000'],
        ]);

        if (end($data['conversation'])['role'] !== 'user') {
            return response()->json(['message' => __('hr.assistant.ask_something')], 422);
        }

        $access = $this->access();
        $scope = $this->scope();

        try {
            return response()->json(['answer' => $answer->handle(array_values($data['conversation']), fn (string $key) => $scope->branchesFor($key, $access))]);
        } catch (IntelligenceUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
