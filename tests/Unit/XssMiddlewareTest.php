<?php

namespace Tests\Unit;

use App\Http\Middleware\XSS;
use Illuminate\Http\Request;
use Tests\TestCase;

class XssMiddlewareTest extends TestCase
{
    public function test_it_sanitizes_content_without_mutating_credentials(): void
    {
        $request = Request::create('/submit', 'POST', [
            'name' => '<b>Alice</b>',
            'password' => 'Strong<em>Password!23',
            'password_confirmation' => 'Strong<em>Password!23',
            'current_password' => 'Old<strong>Password!23',
            'access_token' => 'opaque<tag>token',
            'code' => '12<tag>34',
            'recovery_codes' => ['first<tag>code', 'second<tag>code'],
        ]);

        (new XSS())->handle($request, fn ($nextRequest) => $nextRequest);

        $this->assertSame('Alice', $request->input('name'));
        $this->assertSame('Strong<em>Password!23', $request->input('password'));
        $this->assertSame('Strong<em>Password!23', $request->input('password_confirmation'));
        $this->assertSame('Old<strong>Password!23', $request->input('current_password'));
        $this->assertSame('opaque<tag>token', $request->input('access_token'));
        $this->assertSame('12<tag>34', $request->input('code'));
        $this->assertSame(
            ['first<tag>code', 'second<tag>code'],
            $request->input('recovery_codes')
        );
    }
}
