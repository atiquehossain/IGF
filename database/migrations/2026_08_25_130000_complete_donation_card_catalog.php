<?php

use App\Models\DonationType;
use App\Services\DonationDestinationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CARD_METADATA = [
        'where-it-is-needed-most' => [10, 'hands-heart'],
        'education' => [20, 'graduation-cap'],
        'zakat' => [30, 'moon'],
        'sadaqah' => [40, 'hand-heart'],
        'food-support' => [50, 'food'],
        'emergency-relief' => [60, 'emergency'],
        'orphan-support' => [70, 'children'],
        'school-stationery' => [80, 'stationery'],
        'school-uniforms' => [90, 'uniform'],
        'school-meals' => [100, 'meals'],
        'adopt-a-school' => [110, 'school'],
        'ramadan-iftar' => [120, 'moon'],
        'qurbani' => [130, 'qurbani'],
        'pure-water-and-sanitation' => [140, 'water'],
        'women-empowerment' => [150, 'women'],
        'youth-development' => [160, 'youth'],
        'street-children-education' => [170, 'street-education'],
    ];

    private const EMERGENCY_DESCRIPTION =
        'Provide urgent essentials to communities affected by disasters and emergencies.';

    public function up(): void
    {
        if (!Schema::hasTable('donation_types')) {
            return;
        }

        if (!Schema::hasColumn('donation_types', 'display_order')) {
            Schema::table('donation_types', function (Blueprint $table): void {
                $table->unsignedInteger('display_order')->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('donation_types', 'icon_key')) {
            Schema::table('donation_types', function (Blueprint $table): void {
                $table->string('icon_key', 50)->nullable()->after('display_order');
            });
        }

        $emergency = DonationType::query()->where('slug', 'emergency-relief')->first();
        if ($emergency && trim((string) $emergency->description) === '') {
            $emergency->description = self::EMERGENCY_DESCRIPTION;
            $emergency->save();
        }

        $destinations = app(DonationDestinationService::class);
        foreach (self::CARD_METADATA as $slug => [$displayOrder, $iconKey]) {
            $cause = DonationType::query()->where('slug', $slug)->first();
            if (!$cause) {
                continue;
            }

            $presentation = [];
            if ($cause->display_order === null) {
                $presentation['display_order'] = $displayOrder;
            }
            if (trim((string) $cause->icon_key) === '') {
                $presentation['icon_key'] = $iconKey;
            }
            if ($presentation !== []) {
                $cause->fill($presentation)->save();
            }

            if (!$cause->status && $destinations->isReadyForPublication($cause)) {
                $cause->status = true;
                $cause->save();
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('donation_types')) {
            return;
        }

        if (Schema::hasColumn('donation_types', 'icon_key')) {
            Schema::table('donation_types', function (Blueprint $table): void {
                $table->dropColumn('icon_key');
            });
        }
        if (Schema::hasColumn('donation_types', 'display_order')) {
            Schema::table('donation_types', function (Blueprint $table): void {
                $table->dropColumn('display_order');
            });
        }
    }
};
