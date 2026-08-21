<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOTICES = [
        'en' => 'Authorized Ignite staff can see your question and the contact details you provide. Do not include card numbers, NID, medical details, or emergency and safeguarding information.',
        'bn' => 'অনুমোদিত ইগনাইট কর্মীরা আপনার প্রশ্ন ও দেওয়া যোগাযোগের তথ্য দেখতে পারবেন। কার্ড নম্বর, জাতীয় পরিচয়পত্র, চিকিৎসার তথ্য বা জরুরি ও সুরক্ষা-সংক্রান্ত তথ্য লিখবেন না।',
    ];

    public function up(): void
    {
        foreach (self::NOTICES as $locale => $notice) {
            DB::table('chat_settings')
                ->where('locale', $locale)
                ->where('privacy_message', $notice)
                ->update(['privacy_message' => '']);
        }
    }

    public function down(): void
    {
        foreach (self::NOTICES as $locale => $notice) {
            DB::table('chat_settings')
                ->where('locale', $locale)
                ->where('privacy_message', '')
                ->update(['privacy_message' => $notice]);
        }
    }
};
