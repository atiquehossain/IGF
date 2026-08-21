<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_ID = 49;
    private const ACTION_IDS = [160, 161, 162, 163, 164, 165, 166];

    public function up(): void
    {
        Schema::create('chat_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 5)->unique();
            $table->boolean('enabled')->default(true);
            $table->string('title', 120);
            $table->text('welcome_message');
            $table->text('privacy_message');
            $table->text('fallback_message');
            $table->timestamps();
        });

        Schema::create('chat_faqs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('locale', 5)->index();
            $table->string('question', 500);
            $table->text('answer');
            $table->text('keywords')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['locale', 'is_active', 'sort_order']);
        });

        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('visitor_token_hash', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_email', 150)->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->string('locale', 5)->index();
            $table->string('status', 20)->default('waiting')->index();
            $table->string('page_url', 1000)->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('admin_read_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->text('body');
            $table->foreignId('chat_faq_id')->nullable()->constrained('chat_faqs')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['chat_conversation_id', 'created_at']);
        });

        Schema::create('chat_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 50);
            $table->timestamp('created_at');
            $table->index(['chat_conversation_id', 'created_at']);
        });

        $now = now();
        DB::table('chat_settings')->insert([
            [
                'locale' => 'en',
                'enabled' => true,
                'title' => 'Chat with Ignite',
                'welcome_message' => 'Choose a common question or send a message to our team.',
                'privacy_message' => '',
                'fallback_message' => 'Thank you. Your question is saved and an Ignite team member can reply here.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'locale' => 'bn',
                'enabled' => true,
                'title' => 'ইগনাইটের সাথে চ্যাট করুন',
                'welcome_message' => 'একটি সাধারণ প্রশ্ন বেছে নিন অথবা আমাদের টিমকে বার্তা পাঠান।',
                'privacy_message' => '',
                'fallback_message' => 'ধন্যবাদ। আপনার প্রশ্নটি সংরক্ষিত হয়েছে এবং ইগনাইট টিমের একজন সদস্য এখানে উত্তর দিতে পারবেন।',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('chat_faqs')->insert([
            $this->faq('71000000-0000-4000-8000-000000000001', 'en', 'How can I donate?', 'Open the Donate page, choose an amount and cause, then continue through the secure payment steps.', 'donate,donation,payment', 10, $now),
            $this->faq('71000000-0000-4000-8000-000000000002', 'en', 'How can I volunteer?', 'Open Volunteer Registration from the website, complete the form, and our team will review your application.', 'volunteer,join,help', 20, $now),
            $this->faq('71000000-0000-4000-8000-000000000003', 'en', 'How can I contact the team?', 'Use the Contact page or email info@ignite.org.bd. You can also leave your question in this chat.', 'contact,email,phone', 30, $now),
            $this->faq('71000000-0000-4000-8000-000000000004', 'bn', 'আমি কীভাবে অনুদান দিতে পারি?', 'অনুদান পৃষ্ঠা খুলুন, পরিমাণ ও খাত নির্বাচন করুন, তারপর নিরাপদ পেমেন্ট ধাপগুলো সম্পন্ন করুন।', 'অনুদান,দান,পেমেন্ট', 10, $now),
            $this->faq('71000000-0000-4000-8000-000000000005', 'bn', 'আমি কীভাবে স্বেচ্ছাসেবক হতে পারি?', 'ওয়েবসাইটের স্বেচ্ছাসেবক নিবন্ধন পৃষ্ঠা খুলে ফর্মটি পূরণ করুন। আমাদের টিম আবেদনটি পর্যালোচনা করবে।', 'স্বেচ্ছাসেবক,যোগদান,সাহায্য', 20, $now),
            $this->faq('71000000-0000-4000-8000-000000000006', 'bn', 'আমি টিমের সাথে কীভাবে যোগাযোগ করব?', 'যোগাযোগ পৃষ্ঠা ব্যবহার করুন অথবা info@ignite.org.bd ঠিকানায় ইমেইল করুন। এই চ্যাটেও প্রশ্ন পাঠাতে পারেন।', 'যোগাযোগ,ইমেইল,ফোন', 30, $now),
        ]);

        DB::table('auth_menus')->upsert([[
            'id' => self::MENU_ID,
            'parent_id' => null,
            'name' => 'Website Chat',
            'link' => 'chat.index',
            'icon' => 'fa-comments-o',
            'order_by' => 52,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], ['name', 'link', 'icon', 'order_by', 'status', 'updated_at']);

        $actions = [
            [160, 'View conversations', 0, 'chat.show', 1],
            [161, 'Reply to conversations', 2, 'chat.reply', 2],
            [162, 'Update conversation status', 3, 'chat.status', 3],
            [163, 'Update chat settings', 2, 'chat.settings.update', 4],
            [164, 'Add chat question', 1, 'chat.faq.store', 5],
            [165, 'Edit chat question', 2, 'chat.faq.update', 6],
            [166, 'Delete chat question', 4, 'chat.faq.destroy', 7],
        ];
        DB::table('menu_actions')->upsert(array_map(fn (array $action): array => [
            'id' => $action[0],
            'auth_menu_id' => self::MENU_ID,
            'name' => $action[1],
            'type' => $action[2],
            'link' => $action[3],
            'icon' => null,
            'order_by' => $action[4],
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $actions), ['id'], ['auth_menu_id', 'name', 'type', 'link', 'order_by', 'status', 'updated_at']);

        DB::table('roles')->where('name', 'Super Admin')->get()->each(function ($role): void {
            DB::table('roles')->where('id', $role->id)->update([
                'permission' => $this->appendIds($role->permission, [self::MENU_ID]),
                'actionPermission' => $this->appendIds($role->actionPermission, self::ACTION_IDS),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Super Admin')->get()->each(function ($role): void {
            DB::table('roles')->where('id', $role->id)->update([
                'permission' => $this->removeIds($role->permission, [self::MENU_ID]),
                'actionPermission' => $this->removeIds($role->actionPermission, self::ACTION_IDS),
                'updated_at' => now(),
            ]);
        });

        DB::table('menu_actions')->whereIn('id', self::ACTION_IDS)->delete();
        DB::table('auth_menus')->where('id', self::MENU_ID)->delete();
        Schema::dropIfExists('chat_audits');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('chat_faqs');
        Schema::dropIfExists('chat_settings');
    }

    private function faq(string $uuid, string $locale, string $question, string $answer, string $keywords, int $order, $now): array
    {
        return [
            'uuid' => $uuid,
            'locale' => $locale,
            'question' => $question,
            'answer' => $answer,
            'keywords' => $keywords,
            'sort_order' => $order,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function appendIds(?string $value, array $ids): string
    {
        return collect(explode(',', (string) $value))->merge($ids)
            ->map(fn ($id) => trim((string) $id))->filter()->unique()->implode(',');
    }

    private function removeIds(?string $value, array $ids): string
    {
        $remove = array_map('strval', $ids);

        return collect(explode(',', (string) $value))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && !in_array($id, $remove, true))->implode(',');
    }
};
