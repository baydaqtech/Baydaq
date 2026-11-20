<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Clients\CommentOnClientDocument;
use App\Actions\Clients\ShareClientDocument;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Concerns\ServesClientDocuments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShareClientDocumentRequest;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Response;

/**
 * Standalone HTML pages the team shares with a client (SRS, prototypes, reports), and the comments
 * on them. The routes bind a document only within its client.
 */
class ClientDocumentController extends Controller
{
    use RendersAdminPages, ServesClientDocuments;

    public function store(ShareClientDocumentRequest $request, Client $client, ShareClientDocument $share): RedirectResponse
    {
        $share->handle($client, $request->user(), $request->safe()->only(['title', 'note']), $request->file('file'));
        $this->toast(__('admin.clients.documents.shared'));

        return back();
    }

    public function show(Client $client, ClientDocument $document): Response
    {
        return $this->adminPage('Admin/Clients/Document', [
            ...$this->documentProps($document),
            'client' => ['id' => $client->id, 'name' => $client->localized('name')],
            'copy' => ['admin' => trans('admin'), 'portal' => ['documents' => trans('portal.documents')]],
        ]);
    }

    public function view(Client $client, ClientDocument $document): HttpResponse
    {
        return $this->documentPage($document);
    }

    public function comment(Request $request, Client $client, ClientDocument $document, CommentOnClientDocument $comment): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $comment->handle($document, $request->user(), $data['body']);
        $this->toast(__('portal.documents.comment_sent'));

        return back();
    }

    public function destroy(Client $client, ClientDocument $document): RedirectResponse
    {
        $document->delete();
        $this->toast(__('admin.clients.documents.deleted'));

        return to_route('admin.clients.show', $client);
    }
}
