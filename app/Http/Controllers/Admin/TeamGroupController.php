<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LatestNews;
use App\Models\TeamGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validateGroup($request);

        TeamGroup::create($attributes + [
            'uuid' => (string) Str::uuid(),
            'language' => app()->getLocale(),
        ]);

        return back()->with([
            'message' => 'Team group created successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function update(Request $request, TeamGroup $teamGroup): RedirectResponse
    {
        $this->assertCurrentLocale($teamGroup);
        $teamGroup->update($this->validateGroup($request, $teamGroup));

        return back()->with([
            'message' => 'Team group updated successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function status(TeamGroup $teamGroup): RedirectResponse
    {
        $this->assertCurrentLocale($teamGroup);

        DB::transaction(function () use ($teamGroup): void {
            $locked = TeamGroup::query()->lockForUpdate()->findOrFail($teamGroup->id);
            $this->assertCurrentLocale($locked);
            $locked->update(['status' => ((int) $locked->status) ^ 1]);
        });

        return back()->with([
            'message' => 'Team group visibility updated successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function destroy(TeamGroup $teamGroup): RedirectResponse
    {
        $this->assertCurrentLocale($teamGroup);

        return DB::transaction(function () use ($teamGroup): RedirectResponse {
            $locked = TeamGroup::query()->lockForUpdate()->findOrFail($teamGroup->id);
            $this->assertCurrentLocale($locked);

            $hasMembers = LatestNews::withTrashed()
                ->where('type', 'our-members')
                ->where('team_group_id', $locked->id)
                ->exists();

            if ($hasMembers) {
                return back()->withErrors([
                    'team_group' => 'Move every attached team member to another group before deleting this group.',
                ], 'teamGroup');
            }

            $locked->delete();

            return back()->with([
                'message' => 'Team group deleted successfully.',
                'alert-type' => 'success',
            ]);
        });
    }

    private function validateGroup(Request $request, ?TeamGroup $group = null): array
    {
        $name = trim(strip_tags((string) $request->input('group_name', '')));
        $description = trim(strip_tags((string) $request->input('group_description', '')));
        $slug = $this->normalizedSlug($request->input('group_slug'), $name);
        $locale = app()->getLocale();

        return Validator::make([
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'slug' => $slug,
            'order_by' => $request->input('group_order_by', 0),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('team_groups', 'slug')
                    ->where(fn ($query) => $query->where('language', $locale))
                    ->ignore($group?->id),
            ],
            'order_by' => ['required', 'integer', 'min:0', 'max:999999'],
        ])->validateWithBag('teamGroup');
    }

    private function normalizedSlug(mixed $requested, string $name): string
    {
        $slug = Str::slug(trim((string) $requested));
        if ($slug === '') {
            $slug = Str::slug($name);
        }

        return $slug !== '' ? $slug : 'team-' . substr(sha1($name), 0, 10);
    }

    private function assertCurrentLocale(TeamGroup $teamGroup): void
    {
        abort_unless($teamGroup->language === app()->getLocale(), 403);
    }
}
