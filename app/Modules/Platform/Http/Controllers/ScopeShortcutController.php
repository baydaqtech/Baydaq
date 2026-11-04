<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\ScopeShortcut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Save the current scope (with its branch filter) under a name, or forget a saved one (H1).
 */
class ScopeShortcutController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $scope = $this->scope();

        ScopeShortcut::for($request->user()->id, $scope->segment(), $scope->selectedBranchIds)
            ->forceFill(['last_used_at' => now()])->fill(['name' => $data['name'], 'pinned' => true])->save();
        $this->toast(__('suite.palette.saved'));

        return back();
    }

    public function destroy(Request $request, ScopeShortcut $shortcut): RedirectResponse
    {
        abort_unless($shortcut->user_id === $request->user()->id, 404);
        $shortcut->delete();

        return back();
    }
}
