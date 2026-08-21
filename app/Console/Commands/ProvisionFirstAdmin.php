<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Services\AdminAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionFirstAdmin extends Command
{
    protected $signature = 'igf:provision-admin
        {--name= : Administrator display name}
        {--username= : Administrator username}
        {--email= : Administrator email address}';

    protected $description = 'Securely create the first Ignite administrator without a default credential';

    public function handle(): int
    {
        if (Admin::query()->exists()) {
            $this->error('An administrator already exists. Use the authenticated admin UI to manage accounts.');
            return self::FAILURE;
        }

        if (!AuthMenu::query()->exists() || !MenuAction::query()->exists()) {
            $this->error('Permission data is missing. Run php artisan db:seed first.');
            return self::FAILURE;
        }

        $input = [
            'name' => $this->option('name') ?: $this->ask('Administrator name'),
            'username' => $this->option('username') ?: $this->ask('Administrator username'),
            'email' => $this->option('email') ?: $this->ask('Administrator email'),
            'password' => $this->secret('Temporary password (12+ characters, mixed case, number and symbol)'),
        ];
        $input['username'] = strtolower(trim((string) $input['username']));
        $input['email'] = strtolower(trim((string) $input['email']));
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:admins,username'],
            'email' => ['required', 'email', 'max:50', 'unique:admins,email'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        [$role, $admin] = DB::transaction(function () use ($input): array {
            $role = Role::query()->where('is_owner', true)->lockForUpdate()->first();
            $role ??= new Role(['name' => 'Deployment Owner']);
            $role->forceFill([
                'permission' => AuthMenu::query()->where('status', 1)->pluck('id')->implode(','),
                'actionPermission' => MenuAction::query()->where('status', 1)->pluck('id')->implode(','),
                'serial' => AuthMenu::query()
                    ->with('children')
                    ->where('status', 1)
                    ->whereNull('parent_id')
                    ->orderBy('order_by')
                    ->get()
                    ->toJson(),
                'order_by' => 0,
                'status' => 1,
                'security_rank' => 0,
                'is_owner' => true,
            ])->save();

            $admin = Admin::create([
                'name' => $input['name'],
                'username' => $input['username'],
                'email' => $input['email'],
                'role' => (string) $role->id,
                'status' => 1,
                'password' => Hash::make($input['password']),
                'must_change_password' => true,
                'password_changed_at' => now(),
            ]);

            app(AdminAuditService::class)->record(null, 'admin.provisioned', $admin, [
                'role_id' => $role->id,
                'must_change_password' => true,
            ], ['source' => 'igf:provision-admin']);

            return [$role, $admin];
        });

        $this->info('The first administrator was created and must change the temporary password at sign-in.');
        return self::SUCCESS;
    }
}
