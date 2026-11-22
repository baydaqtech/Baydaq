<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ClientDocument;
use App\Models\ClientDocumentComment;
use Illuminate\Http\Response;

/**
 * Shows a client document the same way in admin and in the portal: the page data with its comments,
 * and the uploaded HTML itself as a sandboxed page.
 */
trait ServesClientDocuments
{
    /**
     * The page's scripts, forms and pop-ups work, but it runs as an unknown origin: it can't read
     * Baydaq cookies or call Baydaq with the viewer's sign-in, whoever uploaded it.
     */
    protected const SANDBOX = 'sandbox allow-scripts allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-downloads';

    /**
     * A sandboxed page has no browser storage and throws when a script touches it; this gives such
     * scripts an in-memory stand-in so prototypes that save state still run.
     */
    private const STORAGE_STANDIN = <<<'JS'
        (function(){function s(){var d={};return{getItem:function(k){return Object.prototype.hasOwnProperty.call(d,k)?d[k]:null},setItem:function(k,v){d[k]=String(v)},removeItem:function(k){delete d[k]},clear:function(){d={}},key:function(i){return Object.keys(d)[i]||null},get length(){return Object.keys(d).length}}}['localStorage','sessionStorage'].forEach(function(n){try{window[n].getItem('_')}catch(e){try{Object.defineProperty(window,n,{value:s(),configurable:true})}catch(e2){}}})})();
        JS;

    /**
     * The uploaded HTML as its own page.
     */
    protected function documentPage(ClientDocument $document): Response
    {
        return response($this->withStorageStandIn($document->content), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => self::SANDBOX,
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /**
     * The document's details and comments for the viewer page.
     *
     * @return array{document: array<string, mixed>, comments: list<array<string, mixed>>}
     */
    protected function documentProps(ClientDocument $document): array
    {
        $document->load(['uploader:id,name', 'comments' => fn ($query) => $query->with('user:id,name')->orderBy('id')]);

        return [
            'document' => [
                'id' => $document->id,
                'number' => $document->number(),
                'title' => $document->title,
                'note' => $document->note,
                'file_name' => $document->file_name,
                'size' => $document->size,
                'uploaded_at' => $document->created_at->toIso8601String(),
                'uploaded_by' => $document->uploader?->name,
            ],
            'comments' => $document->comments->map(fn (ClientDocumentComment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'by_team' => $comment->by_team,
                'author' => $comment->user?->name,
                'at' => $comment->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Put the storage stand-in first in <head> (or right after <html> or the doctype), so it runs
     * before the page's own scripts without changing how the page is laid out.
     */
    private function withStorageStandIn(string $html): string
    {
        $script = '<script>'.self::STORAGE_STANDIN.'</script>';

        foreach (['/<head\b[^>]*>/i', '/<html\b[^>]*>/i', '/<!doctype[^>]*>/i'] as $pattern) {
            if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE) === 1) {
                $at = $match[0][1] + strlen($match[0][0]);

                return substr($html, 0, $at).$script.substr($html, $at);
            }
        }

        return $script.$html;
    }
}
