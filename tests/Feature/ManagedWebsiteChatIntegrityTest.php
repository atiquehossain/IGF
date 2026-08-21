<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\ChatConversation;
use App\Models\ChatFaq;
use App\Models\ChatMessage;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManagedWebsiteChatIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_bootstrap_exposes_only_active_current_locale_questions_in_order(): void
    {
        ChatFaq::where('locale', 'en')->firstOrFail()->update(['is_active' => false]);
        ChatFaq::create([
            'locale' => 'en',
            'question' => 'First active question?',
            'answer' => 'First answer.',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ChatFaq::create([
            'locale' => 'bn',
            'question' => 'বাংলা প্রশ্ন?',
            'answer' => 'বাংলা উত্তর।',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('chat.bootstrap'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('privacy_message', '')
            ->assertJsonPath('quick_questions.0.question', 'First active question?');

        $questions = collect($response->json('quick_questions'))->pluck('question');
        $this->assertFalse($questions->contains('বাংলা প্রশ্ন?'));
        $this->assertFalse($questions->contains(ChatFaq::withTrashed()->where('locale', 'en')->where('is_active', false)->value('question')));
    }

    public function test_guest_submission_requires_identification_and_is_isolated_to_its_session(): void
    {
        $this->postJson(route('chat.conversations.store'), [
            'name' => 'Guest Visitor',
            'body' => 'I need help.',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $created = $this->postJson(route('chat.conversations.store'), [
            'name' => 'Guest Visitor',
            'email' => 'guest@example.test',
            'body' => 'I need help with a project.',
            'page_url' => 'https://example.test/projects?private=value#fragment',
        ])->assertCreated()->assertJsonStructure(['conversation' => ['id', 'status', 'messages']]);

        $uuid = $created->json('conversation.id');
        $this->assertDatabaseHas('chat_conversations', [
            'uuid' => $uuid,
            'guest_name' => 'Guest Visitor',
            'guest_email' => 'guest@example.test',
            'page_url' => '/projects',
            'user_id' => null,
        ]);
        $this->getJson(route('chat.conversations.show', $uuid))->assertOk();

        $this->app['session.store']->flush();
        $this->getJson(route('chat.conversations.show', $uuid))->assertNotFound();
    }

    public function test_guest_identity_and_contact_must_remain_nonempty_after_plain_text_normalization(): void
    {
        $this->postJson(route('chat.conversations.store'), [
            'name' => '<b>' . "\x01" . '</b>',
            'email' => 'guest@example.test',
            'body' => 'A markup-only name must not satisfy identification.',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->postJson(route('chat.conversations.store'), [
            'name' => 'Guest Visitor',
            'phone' => '<i>' . "\x07" . '</i>',
            'body' => 'A markup-only phone must not satisfy contact.',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(0, ChatConversation::count());
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_authenticated_attribution_comes_only_from_session_and_requires_approved_active_member(): void
    {
        $member = $this->member(['name' => 'Approved Member']);
        $other = $this->member(['email' => 'other@example.test', 'phone_no' => '01700000002']);

        $bootstrap = $this->actingAs($member)->getJson(route('chat.bootstrap'))->assertOk();
        $this->assertSame([
            'id' => $member->id,
            'name' => 'Approved Member',
        ], $bootstrap->json('viewer'));

        $this->postJson(route('chat.conversations.store'), [
            'body' => 'Who is attached to this question?',
            'user_id' => $other->id,
            'name' => 'Forged Name',
            'email' => 'forged@example.test',
        ])->assertCreated();

        $this->assertDatabaseHas('chat_conversations', [
            'user_id' => $member->id,
            'guest_name' => null,
            'guest_email' => null,
        ]);

        $pending = $this->member([
            'email' => 'pending@example.test',
            'phone_no' => '01700000003',
            'is_approved' => 0,
        ]);
        $this->actingAs($pending)->postJson(route('chat.conversations.store'), [
            'body' => 'This must not be recorded.',
        ])->assertForbidden();
        $this->assertDatabaseMissing('chat_messages', ['body' => 'This must not be recorded.']);
    }

    public function test_revoked_member_cannot_restore_a_saved_transcript_through_bootstrap(): void
    {
        $member = $this->member();
        $this->actingAs($member)->postJson(route('chat.conversations.store'), [
            'body' => 'A private member conversation.',
        ])->assertCreated();

        $member->update(['is_approved' => 2]);

        $this->getJson(route('chat.bootstrap'))->assertForbidden();
    }

    public function test_predefined_question_click_records_only_anonymous_aggregate_analytics(): void
    {
        $faq = ChatFaq::where('locale', 'en')->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $conversationCount = ChatConversation::count();
        $messageCount = ChatMessage::count();
        $auditCount = \App\Models\ChatAudit::count();
        $this->assertSame('/chat/faq-click', parse_url(route('chat.faqs.click'), PHP_URL_PATH));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('chat_faqs', 'last_clicked_at'));

        $this->postJson(route('chat.faqs.click'), ['faq_id' => 'not-a-uuid'])
            ->assertUnprocessable()->assertJsonValidationErrors('faq_id');

        $this->postJson(route('chat.faqs.click'), [
            'faq_id' => $faq->uuid,
            'name' => 'Must not be stored',
            'email' => 'must-not-be-stored@example.test',
        ])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['recorded' => true]);

        $faq->refresh();
        $this->assertSame(1, $faq->click_count);
        $this->assertSame($conversationCount, ChatConversation::count());
        $this->assertSame($messageCount, ChatMessage::count());
        $this->assertSame($auditCount, \App\Models\ChatAudit::count());
        $this->assertNull(session('chat_conversation_tokens'));
        $this->assertDatabaseMissing('chat_conversations', ['guest_email' => 'must-not-be-stored@example.test']);

        $faq->update(['is_active' => false]);
        $this->postJson(route('chat.faqs.click'), ['faq_id' => $faq->uuid])->assertNotFound();
        $this->assertSame(1, $faq->fresh()->click_count);

        $banglaFaq = ChatFaq::where('locale', 'bn')->where('is_active', true)->firstOrFail();
        $this->postJson(route('chat.faqs.click'), ['faq_id' => $banglaFaq->uuid])->assertNotFound();
        $this->assertSame(0, $banglaFaq->fresh()->click_count);

        $admin = $this->chatAdmin([167], true, 'Analytics viewer');
        $this->actingAs($admin, 'admin')->get(route('chat.faq.index'))
            ->assertOk()
            ->assertSee('anonymous aggregate totals')
            ->assertSee('1 click')
            ->assertDontSee('Matching words')
            ->assertDontSee('Reply when no saved answer matches');
    }

    public function test_guest_and_member_faq_clicks_change_only_the_aggregate_count_not_its_timestamp(): void
    {
        $faq = ChatFaq::where('locale', 'en')->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $member = $this->member();
        $originalCount = $faq->click_count;
        $originalUpdatedAt = $faq->updated_at->copy();
        $conversationCount = ChatConversation::count();
        $messageCount = ChatMessage::count();
        $auditCount = \App\Models\ChatAudit::count();

        Carbon::setTestNow($originalUpdatedAt->copy()->addDay());
        try {
            $this->postJson(route('chat.faqs.click'), ['faq_id' => $faq->uuid])
                ->assertOk()
                ->assertExactJson(['recorded' => true]);
            $this->actingAs($member)->postJson(route('chat.faqs.click'), ['faq_id' => $faq->uuid])
                ->assertOk()
                ->assertExactJson(['recorded' => true]);
        } finally {
            Carbon::setTestNow();
        }

        $faq->refresh();
        $this->assertSame($originalCount + 2, $faq->click_count);
        $this->assertTrue($faq->updated_at->equalTo($originalUpdatedAt));
        $this->assertSame($conversationCount, ChatConversation::count());
        $this->assertSame($messageCount, ChatMessage::count());
        $this->assertSame($auditCount, \App\Models\ChatAudit::count());
        $this->assertNull(session('chat_conversation_tokens'));
    }

    public function test_custom_conversation_and_message_never_match_faqs_or_create_automated_replies(): void
    {
        $faq = ChatFaq::where('locale', 'en')->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $created = $this->postJson(route('chat.conversations.store'), [
            'name' => 'Custom Question Guest',
            'email' => 'custom-question@example.test',
            'body' => $faq->question,
            'faq_id' => $faq->uuid,
            'answer' => 'Forged client answer',
            'sender_type' => 'automation',
            'user_id' => 999999,
        ])->assertCreated()
            ->assertJsonPath('conversation.status', 'waiting')
            ->assertJsonCount(1, 'conversation.messages');

        $conversation = ChatConversation::where('uuid', $created->json('conversation.id'))->firstOrFail();
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertSame(1, $conversation->messages()->where('sender_type', 'visitor')->count());
        $this->assertSame(0, $conversation->messages()->where('sender_type', 'automation')->count());
        $this->assertNull($conversation->messages()->firstOrFail()->chat_faq_id);
        $this->assertNull($conversation->user_id);
        $this->assertDatabaseMissing('chat_messages', ['body' => 'Forged client answer']);

        $this->postJson(route('chat.messages.store', $conversation), [
            'body' => $faq->question,
            'faq_id' => $faq->uuid,
            'answer' => 'Another forged answer',
            'sender_type' => 'admin',
        ])->assertOk()
            ->assertJsonPath('conversation.status', 'waiting')
            ->assertJsonCount(2, 'conversation.messages');

        $this->assertSame(2, $conversation->messages()->where('sender_type', 'visitor')->count());
        $this->assertSame(0, $conversation->messages()->where('sender_type', 'automation')->count());
        $this->assertSame(0, $conversation->messages()->whereNotNull('chat_faq_id')->count());
    }

    public function test_predefined_question_clicks_are_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77']);
        $faq = ChatFaq::where('locale', 'en')->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('chat.faqs.click'), ['faq_id' => $faq->uuid])->assertOk();
        }
        $this->postJson(route('chat.faqs.click'), ['faq_id' => $faq->uuid])->assertTooManyRequests();

        $this->assertSame(20, $faq->fresh()->click_count);
        $this->assertSame(0, ChatConversation::count());
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_html_and_control_characters_are_removed_and_admin_output_is_escaped(): void
    {
        $created = $this->postJson(route('chat.conversations.store'), [
            'name' => '<b>Visitor</b>',
            'email' => 'safe@example.test',
            'body' => '<script>alert(1)</script><img src=x onerror=alert(2)>Hello' . "\0",
        ])->assertCreated();

        $conversation = ChatConversation::where('uuid', $created->json('conversation.id'))->firstOrFail();
        $this->assertSame('Visitor', $conversation->guest_name);
        $this->assertSame('alert(1)Hello', $conversation->messages()->where('sender_type', 'visitor')->value('body'));

        $admin = $this->chatAdmin([160]);
        $response = $this->actingAs($admin, 'admin')->get(route('chat.show', $conversation))
            ->assertOk()
            ->assertSee('alert(1)Hello')
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('onerror=alert', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_chat_permissions_fail_closed_and_transcript_view_is_separate_from_inbox(): void
    {
        $conversation = $this->guestConversation();
        $restricted = $this->chatAdmin([], false);
        $this->actingAs($restricted, 'admin')->get(route('chat.index'))->assertForbidden();

        $inboxOnly = $this->chatAdmin([], true, 'Inbox-only admin');
        $this->actingAs($inboxOnly, 'admin')->get(route('chat.index'))
            ->assertOk()->assertSee('Conversation inbox');
        $this->get(route('chat.show', $conversation))->assertForbidden();

        $viewer = $this->chatAdmin([160], true, 'Transcript viewer');
        $this->actingAs($viewer, 'admin')->get(route('chat.show', $conversation))->assertOk();
        $this->assertDatabaseHas('chat_audits', [
            'chat_conversation_id' => $conversation->id,
            'admin_id' => $viewer->id,
            'action' => 'view',
        ]);
    }

    public function test_qa_only_admin_can_manage_public_answers_without_accessing_visitor_pii(): void
    {
        $conversation = $this->guestConversation([
            'guest_name' => 'Private Visitor Name',
            'guest_email' => 'private-visitor@example.test',
        ]);
        $conversation->messages()->firstOrFail()->update([
            'body' => 'Private visitor question that must stay in the inbox.',
        ]);
        $faq = ChatFaq::where('locale', 'en')->orderBy('sort_order')->firstOrFail();
        $admin = $this->chatAdmin([163, 164, 165, 166, 167], false, 'Q&A-only admin');

        $this->actingAs($admin, 'admin');
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $response = $this->get(route('chat.faq.index'));
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $response->assertOk()
            ->assertSee('Saved questions')
            ->assertSee($faq->question)
            ->assertSee('Chat Answers')
            ->assertDontSee('Chat Inbox')
            ->assertDontSee('Conversation inbox')
            ->assertDontSee('Private Visitor Name')
            ->assertDontSee('private-visitor@example.test')
            ->assertDontSee('Private visitor question that must stay in the inbox.');
        $this->assertFalse(collect($queries)->contains(
            fn (array $query): bool => str_contains(mb_strtolower((string) ($query['query'] ?? '')), 'chat_conversations')
        ));

        $this->get(route('chat.index'))->assertForbidden();
        $this->post(route('chat.search'), ['search' => 'private-visitor@example.test'])->assertForbidden();
        $this->post(route('chat.search.clear'))->assertForbidden();

        $this->put(route('chat.settings.update', 'en'), [
            'enabled' => 1,
            'title' => 'Public help centre',
            'welcome_message' => 'Choose a predefined question or contact our team.',
            'privacy_message' => '',
        ])->assertRedirect(route('chat.faq.index'));
        $this->assertDatabaseHas('chat_settings', ['locale' => 'en', 'title' => 'Public help centre']);

        $this->post(route('chat.faq.store'), [
            'locale' => 'en',
            'question' => 'A Q&A-only administrator created this?',
            'answer' => 'Yes, without opening the visitor inbox.',
            'sort_order' => 90,
            'is_active' => 1,
        ])->assertRedirect(route('chat.faq.index'));
        $this->assertDatabaseHas('chat_faqs', ['question' => 'A Q&A-only administrator created this?']);
    }

    public function test_admin_reply_becomes_visible_to_the_same_visitor_and_not_another_session(): void
    {
        $created = $this->postJson(route('chat.conversations.store'), [
            'name' => 'Reply Guest',
            'phone' => '+8801700000000',
            'body' => 'Can a person reply?',
        ])->assertCreated();
        $conversation = ChatConversation::where('uuid', $created->json('conversation.id'))->firstOrFail();

        $admin = $this->chatAdmin([160, 161]);
        $this->actingAs($admin, 'admin')->post(route('chat.reply', $conversation), [
            'body' => 'Yes. This reply came from the Ignite admin portal.',
        ])->assertRedirect();

        $this->getJson(route('chat.conversations.show', $conversation))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Yes. This reply came from the Ignite admin portal.', 'sender_type' => 'admin']);
        $this->assertDatabaseHas('chat_audits', ['chat_conversation_id' => $conversation->id, 'action' => 'reply']);

        $this->app['session.store']->flush();
        $this->getJson(route('chat.conversations.show', $conversation))->assertNotFound();
    }

    public function test_a_stale_open_model_cannot_append_to_or_reopen_a_conversation_closed_in_the_database(): void
    {
        $token = Str::random(64);
        $conversation = $this->guestConversation([
            'visitor_token_hash' => hash('sha256', $token),
        ]);
        $staleConversation = ChatConversation::query()->findOrFail($conversation->id);
        $originalMessageCount = $conversation->messages()->count();

        ChatConversation::query()->whereKey($conversation->id)->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $this->assertSame('waiting', $staleConversation->status);

        $request = Request::create(
            route('chat.messages.store', $conversation),
            'POST',
            ['body' => 'This stale request must not reopen the conversation.'],
        );
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('chat_conversation_tokens', [
            $conversation->uuid => $token,
        ]);

        $response = $this->app->make(\App\Http\Controllers\ChatController::class)
            ->storeMessage($request, $staleConversation);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($originalMessageCount, $conversation->messages()->count());
        $this->assertSame('closed', $conversation->fresh()->status);
        $this->assertNotNull($conversation->fresh()->closed_at);
        $this->assertDatabaseMissing('chat_messages', [
            'chat_conversation_id' => $conversation->id,
            'body' => 'This stale request must not reopen the conversation.',
        ]);
    }

    public function test_closed_conversation_is_not_restored_and_guest_or_member_can_start_a_new_enquiry(): void
    {
        $guestClosed = $this->postJson(route('chat.conversations.store'), [
            'name' => 'Returning Guest',
            'email' => 'returning-guest@example.test',
            'body' => 'My first guest enquiry.',
        ])->assertCreated()->json('conversation.id');
        ChatConversation::where('uuid', $guestClosed)->firstOrFail()->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->getJson(route('chat.bootstrap'))->assertOk()->assertJsonPath('conversation', null);
        $guestNew = $this->postJson(route('chat.conversations.store'), [
            'name' => 'Returning Guest',
            'email' => 'returning-guest@example.test',
            'body' => 'My new guest enquiry.',
        ])->assertCreated()->json('conversation.id');
        $this->assertNotSame($guestClosed, $guestNew);
        $this->getJson(route('chat.bootstrap'))->assertJsonPath('conversation.id', $guestNew);

        $member = $this->member(['email' => 'returning-member@example.test']);
        $memberClosed = $this->actingAs($member)->postJson(route('chat.conversations.store'), [
            'body' => 'My first member enquiry.',
        ])->assertCreated()->json('conversation.id');
        ChatConversation::where('uuid', $memberClosed)->firstOrFail()->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->getJson(route('chat.bootstrap'))->assertOk()->assertJsonPath('conversation', null);
        $memberNew = $this->postJson(route('chat.conversations.store'), [
            'body' => 'My new member enquiry.',
        ])->assertCreated()->json('conversation.id');
        $this->assertNotSame($memberClosed, $memberNew);
        $this->getJson(route('chat.bootstrap'))->assertJsonPath('conversation.id', $memberNew);
    }

    public function test_public_writes_are_rate_limited_without_creating_a_sixth_conversation(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.50']);
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('chat.conversations.store'), [
                'name' => 'Rate Guest',
                'email' => 'rate@example.test',
                'body' => 'Question ' . $i,
            ])->assertCreated();
        }

        $this->postJson(route('chat.conversations.store'), [
            'name' => 'Rate Guest',
            'email' => 'rate@example.test',
            'body' => 'Question 6',
        ])->assertTooManyRequests();
        $this->assertSame(5, ChatConversation::count());
    }

    public function test_admin_search_finds_email_and_question_without_putting_pii_in_urls(): void
    {
        $privateEmail = 'private-search@example.test';
        $anchor = $this->guestConversation([
            'guest_name' => 'Email Match Anchor',
            'guest_email' => $privateEmail,
        ]);
        $anchor->messages()->firstOrFail()->update(['body' => 'A uniquely phrased clean-water project question.']);
        foreach (range(1, 16) as $index) {
            $this->guestConversation([
                'guest_name' => 'Email Match ' . $index,
                'guest_email' => $privateEmail,
            ]);
        }
        $this->guestConversation([
            'guest_name' => 'Unrelated Visitor',
            'guest_email' => 'unrelated@example.test',
        ]);

        $restricted = $this->chatAdmin([], false, 'No inbox search');
        $this->actingAs($restricted, 'admin')->post(route('chat.search'), ['search' => $privateEmail])
            ->assertForbidden();

        $admin = $this->chatAdmin([], true);
        $this->actingAs($admin, 'admin')->post(route('chat.search'), ['search' => $privateEmail])
            ->assertRedirect(route('chat.index'));

        $emailResults = $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee($privateEmail)
            ->assertDontSee('Unrelated Visitor')
            ->assertSee('page=2', false);
        $this->assertSearchAbsentFromRenderedUrls($emailResults->getContent(), $privateEmail);

        $this->post(route('chat.search'), ['search' => 'uniquely phrased clean-water'])
            ->assertRedirect(route('chat.index'));
        $questionResults = $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Email Match Anchor')
            ->assertSee('A uniquely phrased clean-water project question.')
            ->assertDontSee('Email Match 1');
        $this->assertSearchAbsentFromRenderedUrls($questionResults->getContent(), 'uniquely phrased clean-water');

        $this->get(route('chat.index', ['search' => $privateEmail]))
            ->assertRedirect(route('chat.index'));
        $this->post(route('chat.search.clear'))->assertRedirect(route('chat.index'));
        $this->get(route('chat.index'))->assertOk()->assertSee('Unrelated Visitor');
    }

    public function test_widget_is_site_wide_accessible_and_uses_plain_text_rendering(): void
    {
        $component = file_get_contents(resource_path('js/Shared/WebsiteChat.vue'));
        $layout = file_get_contents(resource_path('js/layouts/App.vue'));

        $this->assertStringContainsString('role="dialog"', $component);
        $this->assertStringContainsString('aria-modal="true"', $component);
        $this->assertStringContainsString('aria-live="polite"', $component);
        $this->assertStringContainsString("event.key === 'Escape'", $component);
        $this->assertStringContainsString("event.key !== 'Tab'", $component);
        $this->assertStringContainsString("chatWithUs: 'আমাদের সঙ্গে চ্যাট করুন'", $component);
        $this->assertStringContainsString('--chat-action: #9b3f00', $component);
        $this->assertStringContainsString('startNewEnquiry', $component);
        $this->assertStringContainsString('<p v-if="privacyMessage" id="igf-chat-privacy">', $component);
        $this->assertStringNotContainsString('v-html', $component);
        $this->assertStringContainsString('<WebsiteChat />', $layout);
    }

    private function member(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Member User',
            'email' => 'member@example.test',
            'phone_no' => '01700000001',
            'status' => 1,
            'is_approved' => 1,
            'password' => bcrypt('password'),
        ], $overrides));
    }

    private function assertSearchAbsentFromRenderedUrls(string $html, string $search): void
    {
        preg_match_all('/\\b(?:href|action)=["\']([^"\']*)["\']/iu', $html, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $url) {
            $decoded = rawurldecode(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
            $this->assertStringNotContainsString($search, $decoded);
            $this->assertStringNotContainsString('search=', mb_strtolower($decoded));
        }
    }

    private function chatAdmin(array $actionIds = [], bool $withInbox = true, string $name = 'Chat Admin'): Admin
    {
        $menu = AuthMenu::where('link', 'chat.index')->firstOrFail();
        $role = Role::create([
            'name' => $name . ' Role ' . Str::random(5),
            'permission' => $withInbox ? (string) $menu->id : '',
            'actionPermission' => implode(',', $actionIds),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => $name,
            'username' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'email' => Str::lower(Str::random(6)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }

    private function guestConversation(array $overrides = []): ChatConversation
    {
        $conversation = ChatConversation::create(array_merge([
            'visitor_token_hash' => hash('sha256', Str::random(64)),
            'guest_name' => 'Guest Person',
            'guest_email' => 'guest-person@example.test',
            'locale' => 'en',
            'status' => 'waiting',
            'last_message_at' => now(),
        ], $overrides));
        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'sender_type' => 'visitor',
            'body' => 'A saved visitor question.',
        ]);

        return $conversation;
    }
}
