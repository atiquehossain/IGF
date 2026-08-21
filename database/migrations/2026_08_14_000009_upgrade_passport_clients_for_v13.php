<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Passport 13 deliberately supports legacy numeric client IDs. Keeping
        // them avoids invalidating every issued token during an in-place upgrade.
        if (Schema::hasColumn('oauth_clients', 'redirect')) {
            Schema::table('oauth_clients', function (Blueprint $table) {
                $table->text('redirect')->nullable()->change();
                $table->boolean('personal_access_client')->default(false)->change();
                $table->boolean('password_client')->default(false)->change();
            });
        }
        if (!Schema::hasColumn('oauth_clients', 'redirect_uris')) {
            Schema::table('oauth_clients', function (Blueprint $table) {
                $table->text('redirect_uris')->nullable();
            });
        }
        if (!Schema::hasColumn('oauth_clients', 'grant_types')) {
            Schema::table('oauth_clients', function (Blueprint $table) {
                $table->text('grant_types')->nullable();
            });
        }

        DB::table('oauth_clients')->orderBy('id')->chunkById(100, function ($clients) {
            foreach ($clients as $client) {
                $redirects = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) ($client->redirect ?? ''))
                )));

                $grants = match (true) {
                    (bool) ($client->personal_access_client ?? false) => ['personal_access'],
                    (bool) ($client->password_client ?? false) => ['password', 'refresh_token'],
                    $redirects !== [] => ['authorization_code', 'refresh_token'],
                    default => ['client_credentials'],
                };

                DB::table('oauth_clients')->where('id', $client->id)->update([
                    'redirect_uris' => json_encode($redirects, JSON_UNESCAPED_SLASHES),
                    'grant_types' => json_encode($grants, JSON_UNESCAPED_SLASHES),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('oauth_clients', 'grant_types')) {
            Schema::table('oauth_clients', fn (Blueprint $table) => $table->dropColumn('grant_types'));
        }
        if (Schema::hasColumn('oauth_clients', 'redirect_uris')) {
            Schema::table('oauth_clients', fn (Blueprint $table) => $table->dropColumn('redirect_uris'));
        }
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
