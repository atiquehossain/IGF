<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatFaq;
use App\Models\ChatMessage;
use App\Models\ChatSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $settings = $this->settings($locale);
        $conversation = $this->latestAccessibleConversation($request);

        return $this->privateJson([
            'enabled' => (bool) $settings->enabled,
            'title' => $settings->title,
            'welcome_message' => $settings->welcome_message,
            'privacy_message' => $settings->privacy_message,
            'viewer' => $this->viewer($request),
            'quick_questions' => ChatFaq::query()
                ->where('locale', $locale)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(12)
                ->get(['uuid', 'question', 'answer'])
                ->map(fn (ChatFaq $faq): array => [
                    'id' => $faq->uuid,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                ])->values(),
            'conversation' => $conversation ? $this->conversationPayload($conversation) : null,
        ]);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $member = $this->eligibleMember($request);
        $normalizedName = $this->plainText($request->input('name'));
        $normalizedEmail = Str::lower(trim((string) $request->input('email')));
        $normalizedPhone = $this->plainText($request->input('phone'));
        $request->merge([
            'name' => $normalizedName,
            'email' => $normalizedEmail === '' ? null : $normalizedEmail,
            'phone' => $normalizedPhone === '' ? null : $normalizedPhone,
        ]);
        $rules = [
            'body' => ['required', 'string', 'max:2000'],
            'page_url' => ['nullable', 'string', 'max:1000'],
            'name' => [$member ? 'nullable' : 'required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
        $validated = $request->validate($rules);
        if (!$member && ($validated['email'] ?? null) === null && ($validated['phone'] ?? null) === null) {
            throw ValidationException::withMessages(['email' => 'Enter an email address or phone number so the team can follow up.']);
        }
        $body = $this->plainText($validated['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Please enter a question.']);
        }

        $locale = $this->locale();
        $token = Str::random(64);
        $now = now();

        $conversation = DB::transaction(function () use ($validated, $body, $member, $locale, $token, $now): ChatConversation {
            $conversation = ChatConversation::create([
                'visitor_token_hash' => hash('sha256', $token),
                'user_id' => $member?->id,
                'guest_name' => $member ? null : $validated['name'],
                'guest_email' => $member ? null : ($validated['email'] ?? null),
                'guest_phone' => $member ? null : ($validated['phone'] ?? null),
                'locale' => $locale,
                'status' => 'waiting',
                'page_url' => $this->safePagePath($validated['page_url'] ?? null),
                'last_message_at' => $now,
            ]);

            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'sender_type' => 'visitor',
                'body' => $body,
                'user_id' => $member?->id,
            ]);

            return $conversation;
        });

        $tokens = (array) $request->session()->get('chat_conversation_tokens', []);
        $tokens[$conversation->uuid] = $token;
        $request->session()->put('chat_conversation_tokens', array_slice($tokens, -10, null, true));

        return $this->privateJson(['conversation' => $this->conversationPayload($conversation)], 201);
    }

    public function show(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeVisitor($request, $conversation);

        return $this->privateJson(['conversation' => $this->conversationPayload($conversation)]);
    }

    public function storeMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeVisitor($request, $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $body = $this->plainText($validated['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Please enter a question.']);
        }

        $member = $conversation->user_id ? $this->eligibleMember($request) : null;
        $now = now();

        $stored = DB::transaction(function () use ($request, $conversation, $member, $body, $now): bool {
            $lockedConversation = ChatConversation::query()
                ->whereKey($conversation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorizeVisitor($request, $lockedConversation);
            if ($lockedConversation->status === 'closed') {
                return false;
            }

            ChatMessage::create([
                'chat_conversation_id' => $lockedConversation->id,
                'sender_type' => 'visitor',
                'body' => $body,
                'user_id' => $member?->id,
            ]);
            $lockedConversation->update([
                'status' => 'waiting',
                'last_message_at' => $now,
                'admin_read_at' => null,
                'closed_at' => null,
            ]);

            return true;
        });

        if (!$stored) {
            return $this->privateJson(['message' => 'This conversation is closed. Start a new chat if you still need help.'], 409);
        }

        return $this->privateJson(['conversation' => $this->conversationPayload($conversation->fresh())]);
    }

    public function recordFaqClick(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'faq_id' => ['required', 'uuid'],
        ]);
        $faq = ChatFaq::query()
            ->where('uuid', $validated['faq_id'])
            ->where('locale', $this->locale())
            ->where('is_active', true)
            ->firstOrFail();

        DB::table($faq->getTable())
            ->where($faq->getKeyName(), $faq->getKey())
            ->increment('click_count');

        return $this->privateJson(['recorded' => true]);
    }

    private function latestAccessibleConversation(Request $request): ?ChatConversation
    {
        $user = $request->user();
        if ($user) {
            $this->eligibleMember($request);

            return ChatConversation::query()->restorable()->where('user_id', $user->id)
                ->latest('last_message_at')->first();
        }

        $tokens = (array) $request->session()->get('chat_conversation_tokens', []);
        if ($tokens === []) {
            return null;
        }

        return ChatConversation::query()->restorable()->whereIn('uuid', array_keys($tokens))
            ->latest('last_message_at')->first();
    }

    private function authorizeVisitor(Request $request, ChatConversation $conversation): void
    {
        if ($conversation->user_id !== null) {
            abort_unless($request->user() && (int) $request->user()->id === (int) $conversation->user_id, 404);
            $this->eligibleMember($request);
            return;
        }

        $tokens = (array) $request->session()->get('chat_conversation_tokens', []);
        $token = (string) ($tokens[$conversation->uuid] ?? '');
        abort_unless($token !== '' && hash_equals($conversation->visitor_token_hash, hash('sha256', $token)), 404);
    }

    private function eligibleMember(Request $request): ?User
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        abort_unless((int) $user->status === 1 && (int) $user->is_approved === 1, 403, 'This account is not approved for member services.');

        return $user;
    }

    private function conversationPayload(ChatConversation $conversation): array
    {
        $conversation->loadMissing(['messages' => fn ($query) => $query->orderBy('id')]);

        return [
            'id' => $conversation->uuid,
            'status' => $conversation->status,
            'messages' => $conversation->messages->map(fn (ChatMessage $message): array => [
                'id' => $message->uuid,
                'sender_type' => $message->sender_type,
                'body' => $message->body,
                'is_automated' => $message->sender_type === 'automation',
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function viewer(Request $request): ?array
    {
        $user = $request->user();
        if (!$user || (int) $user->status !== 1 || (int) $user->is_approved !== 1) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    private function settings(string $locale): ChatSetting
    {
        return ChatSetting::query()->where('locale', $locale)->first()
            ?? ChatSetting::query()->where('locale', 'en')->firstOrFail();
    }

    private function locale(): string
    {
        return in_array(app()->getLocale(), ['en', 'bn'], true) ? app()->getLocale() : 'en';
    }

    private function plainText(?string $value): string
    {
        $plain = strip_tags((string) $value);
        $plain = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $plain);

        return trim((string) $plain);
    }

    private function safePagePath(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);
            $value = (string) ($parts['path'] ?? '/');
        }
        if (!str_starts_with($value, '/')) {
            $value = '/' . ltrim($value, '/');
        }

        return Str::limit(strtok($value, '#') ?: '/', 1000, '');
    }

    private function privateJson(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
