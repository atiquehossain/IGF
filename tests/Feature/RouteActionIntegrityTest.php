<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteActionIntegrityTest extends TestCase
{
    public function test_every_application_controller_route_targets_a_real_method(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (!str_starts_with($action, 'App\\Http\\Controllers\\')) {
                continue;
            }

            [$controller, $method] = str_contains($action, '@')
                ? explode('@', $action, 2)
                : [$action, '__invoke'];

            if (!class_exists($controller) || !method_exists($controller, $method)) {
                $missing[] = implode('|', $route->methods()) . ' ' . $route->uri() . ' -> ' . $action;
            }
        }

        $this->assertSame([], $missing, "Broken controller route targets:\n" . implode("\n", $missing));
    }
}
