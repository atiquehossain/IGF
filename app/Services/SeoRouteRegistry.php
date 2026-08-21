<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SeoRouteRegistry
{
    /** @return Collection<string, array{label?: string, path: string, page_slug?: string}> */
    public function all(): Collection
    {
        return collect(config('seo.routes', []))
            ->filter(fn ($definition, $name) => is_string($name)
                && is_array($definition)
                && isset($definition['path'])
                && is_string($definition['path']))
            ->sortKeys();
    }

    /** @return Collection<string, string> */
    public function routes(): Collection
    {
        return $this->all()->map(fn (array $definition) => $definition['path']);
    }

    public function has(string $routeName): bool
    {
        return $this->all()->has($routeName);
    }

    /** @return array{label?: string, path: string, page_slug?: string}|null */
    public function definition(string $routeName): ?array
    {
        $definition = $this->all()->get($routeName);

        return is_array($definition) ? $definition : null;
    }

    public function path(string $routeName): ?string
    {
        return $this->definition($routeName)['path'] ?? null;
    }

    public function pageSlug(string $routeName): ?string
    {
        return $this->definition($routeName)['page_slug'] ?? null;
    }
}
