<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\ChatAudit;
use App\Models\ChatConversation;
use App\Models\ChatFaq;
use App\Models\ChatMessage;
use App\Models\ChatSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    private const STATUSES = ['waiting', 'answered', 'resolved', 'closed'];
    private const LOCALES = ['en', 'bn'];
    private const SEARCH_SESSION_KEY = 'admin_chat_inbox_search';
    private const SEARCH_TTL_MINUTES = 10;

    public function index(Request $request)
    {
        if ($request->query->has('search')) {
            return redirect()->route('chat.index');
        }

        $status = $request->string('status')->toString();
        $search = $this->activeSearch($request);

        $query = ChatConversation::query()
            ->with([
                'user:id,name,email,phone_no',
                'latestMessage',
            ])
            ->withCount('messages')
            ->latest('last_message_at');

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%' . $search . '%';
                $builder->where('guest_name', 'like', $like)
                    ->orWhere('guest_email', 'like', $like)
                    ->orWhere('guest_phone', 'like', $like)
                    ->orWhere('uuid', 'like', $like)
                    ->orWhereHas('user', function ($userQuery) use ($like): void {
                        $userQuery->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone_no', 'like', $like);
                    })
                    ->orWhereHas('messages', fn ($messageQuery) => $messageQuery->where('body', 'like', $like));
            });
        }

        $conversations = $query->paginate(15);
        if (in_array($status, self::STATUSES, true)) {
            $conversations->appends(['status' => $status]);
        }

        $admin = Auth::guard('admin')->user();
        $permissions = app(Permission::class);
        $response = response()->view('admin.chat.index', [
            'title' => 'Website Chat',
            'tab' => 'inbox',
            'status' => $status,
            'search' => $search,
            'conversations' => $conversations,
            'can' => [
                'inbox' => true,
                'faq_view' => $permissions->allows($admin, 'chat.faq.index'),
                'view' => $permissions->allows($admin, 'chat.show'),
                'reply' => $permissions->allows($admin, 'chat.reply'),
                'status' => $permissions->allows($admin, 'chat.status'),
                'settings' => $permissions->allows($admin, 'chat.settings.update'),
                'faq_store' => $permissions->allows($admin, 'chat.faq.store'),
                'faq_update' => $permissions->allows($admin, 'chat.faq.update'),
                'faq_destroy' => $permissions->allows($admin, 'chat.faq.destroy'),
            ],
            'counts' => [
                'waiting' => ChatConversation::query()->where('status', 'waiting')->count(),
                'unread' => ChatConversation::query()->whereNotNull('last_message_at')
                    ->where(fn ($q) => $q->whereNull('admin_read_at')->orWhereColumn('admin_read_at', '<', 'last_message_at'))->count(),
                'all' => ChatConversation::query()->count(),
            ],
        ]);

        return $response->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function questions()
    {
        $admin = Auth::guard('admin')->user();
        $permissions = app(Permission::class);

        return response()->view('admin.chat.index', [
            'title' => 'Website Chat Questions & Answers',
            'tab' => 'questions',
            'faqs' => ChatFaq::query()->orderBy('locale')->orderBy('sort_order')->orderBy('id')->get(),
            'settings' => ChatSetting::query()->whereIn('locale', self::LOCALES)->get()->keyBy('locale'),
            'can' => [
                'inbox' => $permissions->allows($admin, 'chat.index'),
                'faq_view' => true,
                'settings' => $permissions->allows($admin, 'chat.settings.update'),
                'faq_store' => $permissions->allows($admin, 'chat.faq.store'),
                'faq_update' => $permissions->allows($admin, 'chat.faq.update'),
                'faq_destroy' => $permissions->allows($admin, 'chat.faq.destroy'),
            ],
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function storeSearch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'max:100'],
        ]);
        $search = $this->normalizeSearch($validated['search']);
        if ($search === '') {
            return back()->withErrors(['search' => 'Enter a name, contact detail, or question to search.']);
        }
        $request->session()->put(self::SEARCH_SESSION_KEY, [
            'admin_id' => (int) Auth::guard('admin')->id(),
            'value' => $search,
            'expires_at' => now()->addMinutes(self::SEARCH_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->route('chat.index');
    }

    public function clearSearch(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SEARCH_SESSION_KEY);

        return redirect()->route('chat.index');
    }

    public function show(ChatConversation $conversation)
    {
        $conversation->load([
            'user:id,name,email,phone_no',
            'messages' => fn ($query) => $query->orderBy('id'),
        ]);
        $conversation->forceFill(['admin_read_at' => now()])->save();
        $this->audit($conversation, 'view');

        $admin = Auth::guard('admin')->user();
        $permissions = app(Permission::class);
        return response()->view('admin.chat.show', [
            'title' => 'Chat Conversation',
            'conversation' => $conversation,
            'statuses' => self::STATUSES,
            'canReply' => $permissions->allows($admin, 'chat.reply'),
            'canStatus' => $permissions->allows($admin, 'chat.status'),
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function reply(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $body = $this->plainText($validated['body']);
        if ($body === '') {
            return back()->withErrors(['body' => 'Please enter a reply.'])->withInput();
        }

        $adminId = (int) Auth::guard('admin')->id();
        DB::transaction(function () use ($conversation, $body, $adminId): void {
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'sender_type' => 'admin',
                'body' => $body,
                'admin_id' => $adminId,
            ]);
            $conversation->update([
                'status' => 'answered',
                'assigned_admin_id' => $adminId,
                'last_message_at' => now(),
                'admin_read_at' => now(),
                'closed_at' => null,
            ]);
            $this->audit($conversation, 'reply');
        });

        return back()->with('success', 'Reply sent. The visitor will see it in the chat window.');
    }

    public function updateStatus(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(self::STATUSES)]]);
        $conversation->update([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'closed' ? now() : null,
            'assigned_admin_id' => $conversation->assigned_admin_id ?: Auth::guard('admin')->id(),
        ]);
        $this->audit($conversation, 'status:' . $validated['status']);

        return back()->with('success', 'Conversation status updated.');
    }

    public function updateSettings(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::LOCALES, true), 404);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:120'],
            'welcome_message' => ['required', 'string', 'max:1000'],
            'privacy_message' => ['nullable', 'string', 'max:500'],
        ]);
        ChatSetting::query()->updateOrCreate(['locale' => $locale], [
            'enabled' => (bool) $validated['enabled'],
            'title' => $this->plainText($validated['title']),
            'welcome_message' => $this->plainText($validated['welcome_message']),
            'privacy_message' => $this->plainText($validated['privacy_message'] ?? ''),
        ]);

        return redirect()->route('chat.faq.index')->with('success', 'Chat settings updated.');
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $data = $this->faqData($request);
        $data['created_by_admin_id'] = Auth::guard('admin')->id();
        $data['updated_by_admin_id'] = Auth::guard('admin')->id();
        ChatFaq::create($data);

        return redirect()->route('chat.faq.index')->with('success', 'Question and answer added.');
    }

    public function updateFaq(Request $request, ChatFaq $faq): RedirectResponse
    {
        $data = $this->faqData($request);
        $data['updated_by_admin_id'] = Auth::guard('admin')->id();
        $faq->update($data);

        return redirect()->route('chat.faq.index')->with('success', 'Question and answer updated.');
    }

    public function destroyFaq(ChatFaq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('chat.faq.index')->with('success', 'Question removed from the public chat. Existing conversation history is unchanged.');
    }

    private function faqData(Request $request): array
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(self::LOCALES)],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:4000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);

        return [
            'locale' => $validated['locale'],
            'question' => $this->plainText($validated['question']),
            'answer' => $this->plainText($validated['answer']),
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    private function plainText(?string $value): string
    {
        $plain = strip_tags((string) $value);
        $plain = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $plain);

        return trim((string) $plain);
    }

    private function activeSearch(Request $request): string
    {
        $state = $request->session()->get(self::SEARCH_SESSION_KEY);
        if (!is_array($state)
            || (int) ($state['admin_id'] ?? 0) !== (int) Auth::guard('admin')->id()
            || (int) ($state['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::SEARCH_SESSION_KEY);

            return '';
        }

        return $this->normalizeSearch((string) ($state['value'] ?? ''));
    }

    private function normalizeSearch(string $value): string
    {
        $value = mb_substr(trim($value), 0, 100);
        $value = preg_replace('/[%_\\\\\x00-\x1F\x7F]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function audit(ChatConversation $conversation, string $action): void
    {
        ChatAudit::create([
            'chat_conversation_id' => $conversation->id,
            'admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'created_at' => now(),
        ]);
    }
}
