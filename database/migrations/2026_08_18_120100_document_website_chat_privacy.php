<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENGLISH_NOTICE = '<h2>Website chat</h2><p>If you submit a chat question, authorized Ignite staff can see your message and the contact details or member identity connected with it. Use chat only for general questions. Do not enter passwords, card numbers, national identity information, medical details, or emergency and safeguarding reports. Chat records are handled under Ignite\'s approved privacy and record-retention rules.</p>';

    private const BANGLA_NOTICE = '<h2>ওয়েবসাইট চ্যাট</h2><p>আপনি চ্যাটে প্রশ্ন পাঠালে অনুমোদিত ইগনাইট কর্মীরা আপনার বার্তা এবং এর সাথে যুক্ত যোগাযোগের তথ্য বা সদস্য পরিচয় দেখতে পারবেন। চ্যাট শুধু সাধারণ প্রশ্নের জন্য ব্যবহার করুন। পাসওয়ার্ড, কার্ড নম্বর, জাতীয় পরিচয়পত্রের তথ্য, চিকিৎসার তথ্য অথবা জরুরি ও সুরক্ষা-সংক্রান্ত প্রতিবেদন লিখবেন না। চ্যাট রেকর্ড ইগনাইটের অনুমোদিত গোপনীয়তা ও রেকর্ড সংরক্ষণ নীতি অনুযায়ী পরিচালিত হয়।</p>';

    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')->where('slug', 'privacy-policy')->get()->each(function ($page): void {
            $notice = ($page->language ?? 'en') === 'bn' ? self::BANGLA_NOTICE : self::ENGLISH_NOTICE;
            $description = (string) $page->description;
            if (!str_contains($description, '<h2>Website chat</h2>') && !str_contains($description, '<h2>ওয়েবসাইট চ্যাট</h2>')) {
                DB::table('pages')->where('id', $page->id)->update([
                    'description' => rtrim($description) . $notice,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')->where('slug', 'privacy-policy')->get()->each(function ($page): void {
            DB::table('pages')->where('id', $page->id)->update([
                'description' => str_replace([self::ENGLISH_NOTICE, self::BANGLA_NOTICE], '', (string) $page->description),
                'updated_at' => now(),
            ]);
        });
    }
};
