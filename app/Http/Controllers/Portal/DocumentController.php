<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Clients\CommentOnClientDocument;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Concerns\ServesClientDocuments;
use App\Http\Controllers\Controller;
use App\Models\ClientDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client reads a document the team shared (shown as a page inside the portal) and comments on it.
 */
class DocumentController extends Controller
{
    use RendersAdminPages, ServesClientDocuments;

    public function show(ClientDocument $document): Response
    {
        Gate::authorize('view', $document);

        return Inertia::render('Portal/Document', [
            ...$this->documentProps($document),
            'copy' => ['portal' => trans('portal')],
        ]);
    }

    public function view(ClientDocument $document): HttpResponse
    {
        Gate::authorize('view', $document);

        return $this->documentPage($document);
    }

    public function comment(Request $request, ClientDocument $document, CommentOnClientDocument $comment): RedirectResponse
    {
        Gate::authorize('view', $document);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $comment->handle($document, $request->user(), $data['body']);
        $this->toast(__('portal.documents.comment_sent'));

        return back();
    }
}
