<?php

namespace Tests;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function actingAs(UserContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($guard === 'admin' && $user instanceof Admin) {
            $this->withSession([
                Admin::SESSION_AUTH_VERSION => (int) $user->auth_version,
            ]);
        }

        return $this;
    }
}
