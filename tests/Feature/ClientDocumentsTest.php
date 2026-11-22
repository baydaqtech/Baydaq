<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientDocumentComment;
use App\Models\User;
use App\Notifications\AccountReady;
use App\Notifications\ClientDocumentActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = '<!doctype html><html lang="ar"><head><meta charset="utf-8"><title>SRS</title><script>localStorage.setItem("seen", "1")</script></head><body><h1>وثيقة المتطلبات</h1></body></html>';

    private Client $client;

    private User $clientUser;

    private User $team;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->withoutVite();
        $this->client = Client::factory()->create();
        $this->clientUser = User::factory()->client($this->client)->create();
        $this->team = User::factory()->admin(Permission::ClientsManage)->create();
    }

    public function test_the_team_shares_an_html_page_and_the_clients_accounts_get_an_email(): void
    {
        $this->actingAs($this->team)
            ->post("/admin/clients/{$this->client->id}/documents", [
                'title' => 'SRS v1',
                'note' => 'First draft for review.',
                'file' => UploadedFile::fake()->createWithContent('srs-v1.html', "\xEF\xBB\xBF".self::PAGE),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document = ClientDocument::query()->sole();
        $this->assertSame($this->client->id, $document->client_id);
        $this->assertSame('SRS v1', $document->title);
        $this->assertSame('srs-v1.html', $document->file_name);
        $this->assertSame(self::PAGE, $document->content);
        $this->assertSame($this->team->id, $document->uploaded_by);

        Notification::assertSentTo($this->clientUser, ClientDocumentActivity::class, function (ClientDocumentActivity $notification, array $channels, User $notifiable) use ($document): bool {
            return $notification->event === ClientDocumentActivity::SHARED
                && str_ends_with($notification->toMail($notifiable)->actionUrl, "/app/documents/{$document->id}");
        });
        Notification::assertNotSentTo($this->team, ClientDocumentActivity::class);
    }

    public function test_the_admin_client_page_lists_the_documents_with_the_labels_they_need(): void
    {
        ClientDocument::factory()->for($this->client)->create(['title' => 'SRS']);

        $this->actingAs($this->team)->get("/admin/clients/{$this->client->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 1)
                ->where('documents.0.title', 'SRS')
                ->where('documents.0.number', 1)
                ->where('document_max_mb', 20)
                ->has('copy.portal.documents.number')
                ->has('copy.portal.documents.by'));
    }

    public function test_only_titled_html_files_saved_as_utf8_are_accepted(): void
    {
        $this->actingAs($this->team);
        $url = "/admin/clients/{$this->client->id}/documents";

        $this->post($url, ['title' => 'Spec', 'file' => UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post($url, ['title' => 'Spec', 'file' => UploadedFile::fake()->createWithContent('spec.html', "<p>\xC7\xE1\xD3\xE1\xC7\xE3</p>")])->assertSessionHasErrors('file');
        $this->post($url, ['title' => '', 'file' => UploadedFile::fake()->createWithContent('spec.html', self::PAGE)])->assertSessionHasErrors('title');

        $this->assertSame(0, ClientDocument::query()->count());
    }

    public function test_a_team_member_who_only_views_clients_can_read_but_not_share_or_reply(): void
    {
        $document = ClientDocument::factory()->for($this->client)->create();
        $viewer = User::factory()->admin(Permission::ClientsView)->create();
        $this->actingAs($viewer);

        $this->get("/admin/clients/{$this->client->id}/documents/{$document->id}")->assertOk();
        $this->post("/admin/clients/{$this->client->id}/documents", ['title' => 'X', 'file' => UploadedFile::fake()->createWithContent('x.html', self::PAGE)])->assertForbidden();
        $this->post("/admin/clients/{$this->client->id}/documents/{$document->id}/comments", ['body' => 'Hi'])->assertForbidden();
    }

    public function test_the_portal_lists_the_clients_documents_newest_first_numbered_in_upload_order(): void
    {
        $first = ClientDocument::factory()->for($this->client)->create(['title' => 'SRS']);
        ClientDocument::factory()->for($this->client)->create(['title' => 'Wireframes']);
        $latest = ClientDocument::factory()->for($this->client)->create(['title' => 'MVP1 prototype']);
        ClientDocument::factory()->create(['title' => 'Someone else’s']);
        ClientDocumentComment::factory()->for($first, 'document')->count(2)->create();

        $this->actingAs($this->clientUser)->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Home')
                ->has('documents', 3)
                ->where('documents.0.title', 'MVP1 prototype')
                ->where('documents.0.number', 3)
                ->where('documents.0.url', "/app/documents/{$latest->id}")
                ->where('documents.2.title', 'SRS')
                ->where('documents.2.number', 1)
                ->where('documents.2.comments_count', 2)
                ->missing('documents.0.content'));
    }

    public function test_the_client_opens_a_document_as_a_page_and_its_html_runs_sandboxed(): void
    {
        $document = ClientDocument::factory()->for($this->client)->create(['title' => 'SRS', 'content' => self::PAGE]);
        $this->actingAs($this->clientUser);

        $this->get("/app/documents/{$document->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Document')
                ->where('document.title', 'SRS')
                ->where('document.number', 1)
                ->missing('document.content')
                ->has('comments', 0));

        $page = $this->get("/app/documents/{$document->id}/view")->assertOk();
        $html = $page->getContent();

        $this->assertStringStartsWith('text/html', $page->headers->get('Content-Type'));
        $this->assertStringStartsWith('sandbox allow-scripts', $page->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('allow-same-origin', $page->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('noindex', $page->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<h1>وثيقة المتطلبات</h1>', $html);
        // The storage stand-in runs first in <head>, before the page's own scripts.
        $this->assertStringStartsWith('<!doctype html><html lang="ar"><head><script>(function(){', $html);
        $this->assertLessThan(strpos($html, 'localStorage.setItem("seen"'), strpos($html, 'sessionStorage'));
    }

    public function test_a_page_without_a_head_still_gets_the_storage_stand_in_first(): void
    {
        $document = ClientDocument::factory()->for($this->client)->create(['content' => '<p>Plain</p>']);

        $html = $this->actingAs($this->clientUser)->get("/app/documents/{$document->id}/view")->getContent();

        $this->assertStringStartsWith('<script>(function(){', $html);
        $this->assertStringEndsWith('</script><p>Plain</p>', $html);
    }

    public function test_the_client_comments_and_the_team_is_emailed(): void
    {
        $document = ClientDocument::factory()->for($this->client)->create(['title' => 'SRS']);

        $this->actingAs($this->clientUser)
            ->post("/app/documents/{$document->id}/comments", ['body' => 'Please add a section on payments.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $comment = ClientDocumentComment::query()->sole();
        $this->assertFalse($comment->by_team);
        $this->assertSame($this->clientUser->id, $comment->user_id);

        Notification::assertSentTo($this->team, ClientDocumentActivity::class, function (ClientDocumentActivity $notification, array $channels, User $notifiable) use ($document): bool {
            return $notification->event === ClientDocumentActivity::COMMENTED
                && $notification->excerpt === 'Please add a section on payments.'
                && str_ends_with($notification->toMail($notifiable)->actionUrl, "/admin/clients/{$this->client->id}/documents/{$document->id}");
        });
        Notification::assertNotSentTo($this->clientUser, ClientDocumentActivity::class);

        $this->post("/app/documents/{$document->id}/comments", ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_the_team_replies_and_the_client_is_emailed(): void
    {
        $document = ClientDocument::factory()->for($this->client)->create();
        ClientDocumentComment::factory()->for($document, 'document')->create(['body' => 'Looks good', 'user_id' => $this->clientUser->id]);

        $this->actingAs($this->team)
            ->post("/admin/clients/{$this->client->id}/documents/{$document->id}/comments", ['body' => 'Thanks, v2 next week.'])
            ->assertRedirect();

        $this->assertTrue(ClientDocumentComment::query()->latest('id')->first()->by_team);
        Notification::assertSentTo($this->clientUser, ClientDocumentActivity::class, fn (ClientDocumentActivity $notification): bool => $notification->event === ClientDocumentActivity::COMMENTED);
        Notification::assertNotSentTo($this->team, ClientDocumentActivity::class);

        $this->get("/admin/clients/{$this->client->id}/documents/{$document->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Clients/Document')
                ->has('comments', 2)
                ->where('comments.0.by_team', false)
                ->where('comments.0.author', $this->clientUser->name)
                ->where('comments.1.by_team', true));
    }

    public function test_another_clients_document_stays_closed(): void
    {
        $theirs = ClientDocument::factory()->create();
        $this->actingAs($this->clientUser);

        $this->get("/app/documents/{$theirs->id}")->assertForbidden();
        $this->get("/app/documents/{$theirs->id}/view")->assertForbidden();
        $this->post("/app/documents/{$theirs->id}/comments", ['body' => 'Hello'])->assertForbidden();
        $this->assertSame(0, ClientDocumentComment::query()->count());

        // In admin a document only opens under its own client.
        $this->actingAs($this->team)->get("/admin/clients/{$this->client->id}/documents/{$theirs->id}")->assertNotFound();
    }

    public function test_deleting_a_document_removes_it_and_its_comments(): void
    {
        $document = ClientDocument::factory()->for($this->client)->create();
        ClientDocumentComment::factory()->for($document, 'document')->create();

        $this->actingAs($this->team)
            ->delete("/admin/clients/{$this->client->id}/documents/{$document->id}")
            ->assertRedirect("/admin/clients/{$this->client->id}");

        $this->assertSame(0, ClientDocument::query()->count());
        $this->assertSame(0, ClientDocumentComment::query()->count());
    }

    public function test_a_new_client_account_gets_its_invite_signs_in_and_sees_the_shared_document(): void
    {
        $this->actingAs($this->team);

        $this->post('/admin/clients', ['name_ar' => 'شركة الرواد', 'name_en' => 'Alrowad Co', 'contact_email' => 'ceo@alrowad.test', 'status' => 'active'])->assertRedirect();
        $client = Client::query()->where('name_en', 'Alrowad Co')->sole();

        $this->post("/admin/clients/{$client->id}/users", ['name' => 'Khalid', 'email' => 'CEO@alrowad.test', 'locale' => 'ar'])->assertRedirect();
        $account = User::query()->where('email', 'ceo@alrowad.test')->sole();

        $token = null;
        Notification::assertSentTo($account, AccountReady::class, function (AccountReady $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post("/admin/clients/{$client->id}/documents", ['title' => 'SRS v1', 'file' => UploadedFile::fake()->createWithContent('srs.html', self::PAGE)])->assertRedirect();
        $document = ClientDocument::query()->where('client_id', $client->id)->sole();
        Notification::assertSentTo($account, ClientDocumentActivity::class);

        $this->post('/logout');
        $this->post('/account/setup', ['token' => $token, 'email' => 'ceo@alrowad.test', 'password' => 'Chosen-pass-2026', 'password_confirmation' => 'Chosen-pass-2026'])
            ->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($account);

        $this->get('/app')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('documents', 1)
            ->where('documents.0.title', 'SRS v1')
            ->where('documents.0.number', 1));
        $this->get("/app/documents/{$document->id}")->assertOk();
        $this->assertStringContainsString('وثيقة المتطلبات', $this->get("/app/documents/{$document->id}/view")->assertOk()->getContent());
    }
}
