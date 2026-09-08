<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use App\Models\District;
use App\Models\Division;
use App\Models\LatestNews;
use App\Models\TeamGroup;

use App\Helper\IgfFile;
use App\Services\AdminMediaUrlResolver;
use App\Services\ContentSanitizer;
use App\Services\SafeMediaReplacementService;

use Exception;
use Throwable;

class LatestNewsController extends Controller {

    private const MEMBER_IMAGE_WIDTH = 900;

    private const SOCIAL_LINK_LIMIT = 12;

    private const SOCIAL_PLATFORM_LABELS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'twitter' => 'X / Twitter',
        'x' => 'X',
        'youtube' => 'YouTube',
        'website' => 'Website',
    ];

    public function __construct(
        private ContentSanitizer $sanitizer,
        private SafeMediaReplacementService $media,
        private AdminMediaUrlResolver $mediaUrls,
    ) {
    }

    public function index(Request $request) {
        $title = $request->Lang->OurMembers;
        $search = $request->search;
        $groupFilter = $request->integer('group_id') ?: null;
        $latestNews = $this->memberQuery()
                ->with(['teamGroup', 'division', 'district'])
                ->where('name', 'like', '%' . $search . '%')
                ->when($groupFilter, fn (Builder $query, int $groupId) => $query->where('team_group_id', $groupId))
                ->orderByDesc('order_by')
                ->orderBy('name', 'ASC')
                ->paginate(15);

        $latestNews->getCollection()->each(function (LatestNews $member): void {
            $member->setAttribute('display_image_url', $this->mediaUrls->image(
                $member->getRawOriginal('path') ?: $member->getRawOriginal('image'),
                'our_members',
            ));
        });

        $groups = TeamGroup::query()
            ->where('language', app()->getLocale())
            ->withCount('members')
            ->withCount([
                'members as attached_members_count' => fn (Builder $query) => $query->withTrashed(),
            ])
            ->orderByDesc('order_by')
            ->orderBy('name')
            ->get();

        $divisions = Division::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $districts = District::query()
            ->where('status', 1)
            ->whereIn('division_id', $divisions->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'division_id', 'name']);

        return view('admin.members.index')->with(compact(
            'title',
            'latestNews',
            'groups',
            'divisions',
            'districts',
            'search',
            'groupFilter'
        ));
    }

    public function create() {
        return redirect()->route('latest.news.index')->with('message', 'Create members from the member list.');
    }

    public function store(Request $request) {
        $validated = $this->validateMember($request);
        $stagedAssets = [];
        $committed = false;

        try {
            DB::transaction(function () use ($request, $validated, &$stagedAssets): void {
                $latestnews = LatestNews::create([
                        'name' => $validated['name'],
                        'category_id' => $validated['category_id'] ?? null,
                        'team_group_id' => $this->memberGroupId($validated),
                        'division_id' => $this->nullableDivisionId($validated),
                        'district_id' => $this->nullableDistrictId($validated),
                        'description' => $validated['designation'],
                        'biography' => $this->nullableText($validated['biography'] ?? null),
                        'qualification' => $this->nullableText($validated['qualification'] ?? null),
                        'url' => $this->nullableText($this->sanitizer->sanitizeUrl($validated['url'] ?? null)),
                        'social_links' => $this->normalizeSocialLinks($validated['social_links'] ?? []),
                        'language' => app()->getLocale(),
                        'order_by' => (int) ($validated['order_by'] ?? 0),
                        'status' => 0,
                        'type' => 'our-members']);
                if ($request->hasFile('image')) {
                    $asset = $this->media->stageResizedPublicImage(
                        $request->file('image'),
                        'our_members',
                        self::MEMBER_IMAGE_WIDTH,
                    );
                    $stagedAssets[] = $asset;
                    $latestnews->update([
                        'image' => $asset->databaseValue,
                        'path' => $asset->databaseValue,
                    ]);
                }
            });
            $committed = true;
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request) {
        return redirect()->route('latest.news.index')->with('message', 'Member details are managed from the member list.');
    }

    public function edit($id = null, Request $request) {
        try {
            $latestnews = $this->memberQuery()->select(
                'id',
                'category_id',
                'team_group_id',
                'division_id',
                'district_id',
                'name',
                'url',
                'social_links',
                'image',
                'type',
                'description',
                'biography',
                'qualification',
                'order_by',
                'path'
            )->whereKey($id)->firstOrFail();
            $latestnews->path = $this->mediaUrls->image(
                $latestnews->getRawOriginal('path') ?: $latestnews->getRawOriginal('image'),
                'our_members',
            );
            $response = [ 'data' => $latestnews];
            return response($response, 200);
        } catch (Exception $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request) {
        $validated = $this->validateMember($request, true);
        $stagedAssets = [];
        $oldImages = [];
        $committed = false;

        try {
            DB::transaction(function () use ($request, $validated, &$stagedAssets, &$oldImages): void {
                $latestnews = $this->memberQuery()
                    ->lockForUpdate()
                    ->findOrFail($validated['id']);
                $asset = null;
                if ($request->hasFile('image')) {
                    $asset = $this->media->stageResizedPublicImage(
                        $request->file('image'),
                        'our_members',
                        self::MEMBER_IMAGE_WIDTH,
                    );
                    $stagedAssets[] = $asset;
                    $oldImages[] = [$latestnews->image, $latestnews->path];
                }

                $latestnews->update([
                    'name' => $validated['name'],
                    'category_id' => $validated['category_id'] ?? null,
                    'team_group_id' => $this->memberGroupId($validated, $latestnews),
                    'division_id' => $this->nullableDivisionId($validated, $latestnews),
                    'district_id' => $this->nullableDistrictId($validated, $latestnews),
                    'description' => $validated['designation'],
                    'biography' => $this->nullableText($validated['biography'] ?? null),
                    'qualification' => $this->nullableText($validated['qualification'] ?? null),
                    'url' => $this->nullableText($this->sanitizer->sanitizeUrl($validated['url'] ?? null)),
                    'social_links' => $this->normalizeSocialLinks($validated['social_links'] ?? []),
                    'order_by' => (int) ($validated['order_by'] ?? 0),
                ] + ($asset ? [
                    'image' => $asset->databaseValue,
                    'path' => $asset->databaseValue,
                ] : []));
            });
            $committed = true;
            foreach ($oldImages as $names) {
                $this->media->deleteLegacyFlatImages('our_members', $names);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request) {
        try {
            if ($request->ajax()) {
                $data = $this->memberQuery()->findOrFail($request->route('id'));
                if (!(bool) $data->status && ($message = $this->inactiveGeographyMessage($data))) {
                    return response(['message' => $message], 409);
                }
                $data->status = $data->status ^ 1;
                $data->update();
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    private function inactiveGeographyMessage(LatestNews $member): ?string
    {
        if ($member->division_id !== null
            && ! Division::query()->whereKey($member->division_id)->where('status', 1)->exists()) {
            return 'Publish the selected work division before publishing this team profile.';
        }

        if ($member->district_id !== null
            && ! District::query()
                ->whereKey($member->district_id)
                ->where('division_id', $member->division_id)
                ->where('status', 1)
                ->whereHas('division', fn (Builder $query) => $query->where('status', 1))
                ->exists()) {
            return 'Choose an active district inside an active work division before publishing this team profile.';
        }

        return null;
    }

    public function destroy($id = null, Request $request) {
        try {
            $latestnews = $this->memberQuery()->findOrFail($id);
            $latestnews->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function image($img = null) {
        return IgfFile::image('/our_members/' . $img);
    }

    private function validateMember(Request $request, bool $updating = false): array
    {
        $rules = [
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:500'],
            'biography' => ['nullable', 'string', 'max:5000'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'team_group_id' => [
                'nullable',
                'integer',
                Rule::exists('team_groups', 'id')->where(fn ($query) => $query
                    ->where('language', app()->getLocale())),
            ],
            'division_id' => [
                'nullable',
                'integer',
                Rule::exists('divisions', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
            'district_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value !== null && $value !== '' && ! $request->filled('division_id')) {
                        $fail('Choose a primary work division before choosing a district.');
                    }
                },
                Rule::exists('districts', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->where('division_id', $request->input('division_id'))),
            ],

            'url' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if ($value !== null && trim((string) $value) !== '' && $this->sanitizer->sanitizeUrl($value) === '') {
                    $fail('The ' . $attribute . ' field must be a safe URL or site path.');
                }
            }],
            'order_by' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'social_links' => ['nullable', 'array', 'max:' . self::SOCIAL_LINK_LIMIT],
            'social_links.*' => ['array'],
            'social_links.*.platform' => ['nullable', 'string', 'max:50'],
            'social_links.*.label' => ['nullable', 'string', 'max:80'],
            'social_links.*.url' => ['nullable', 'url:http,https', 'max:2048'],
        ];

        if ($updating) {
            $rules['id'] = [
                'required',
                'integer',
                Rule::exists('latest_news', 'id')->where(fn ($query) => $query
                    ->where('type', 'our-members')
                    ->where('language', app()->getLocale())),
            ];
        }

        return $request->validate($rules);
    }

    private function normalizeSocialLinks(mixed $links): array
    {
        if (!is_array($links)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach (array_slice($links, 0, self::SOCIAL_LINK_LIMIT) as $link) {
            if (!is_array($link)) {
                continue;
            }

            $url = trim((string) ($link['url'] ?? ''));
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                continue;
            }

            $dedupeKey = strtolower(rtrim($url, '/'));
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $platform = $this->normalizeSocialPlatform($link['platform'] ?? '', $url);
            $label = trim((string) ($link['label'] ?? ''));
            if ($label === '') {
                $label = self::SOCIAL_PLATFORM_LABELS[$platform] ?? ucwords(str_replace('-', ' ', $platform));
            }

            $normalized[] = [
                'platform' => $platform,
                'label' => mb_substr($label, 0, 80),
                'url' => $url,
            ];
            $seen[$dedupeKey] = true;
        }

        return $normalized;
    }

    private function normalizeSocialPlatform(mixed $platform, string $url): string
    {
        $platform = strtolower(trim((string) $platform));
        $platform = trim((string) preg_replace('/[^a-z0-9]+/', '-', $platform), '-');

        if ($platform !== '') {
            return mb_substr($platform, 0, 50);
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (['linkedin', 'facebook', 'instagram', 'tiktok', 'youtube', 'twitter'] as $candidate) {
            if (str_contains($host, $candidate)) {
                return $candidate;
            }
        }

        return in_array($host, ['x.com', 'www.x.com'], true) ? 'x' : 'website';
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function memberQuery(): Builder
    {
        return LatestNews::query()
            ->where('type', 'our-members')
            ->where('language', app()->getLocale());
    }

    private function memberGroupId(array $validated, ?LatestNews $member = null): int
    {
        if (!empty($validated['team_group_id'])) {
            return (int) $validated['team_group_id'];
        }

        if ($member?->team_group_id) {
            return (int) $member->team_group_id;
        }

        $locale = app()->getLocale();
        $group = TeamGroup::query()->firstOrCreate(
            [
                'language' => $locale,
                'slug' => 'board-of-directors',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Board of directors',
                'description' => 'The board provides mission stewardship, oversight, and accountability.',
                'order_by' => 100,
                'status' => 1,
            ]
        );

        return (int) $group->id;
    }

    private function nullableDivisionId(array $validated, ?LatestNews $member = null): ?int
    {
        if (!array_key_exists('division_id', $validated)) {
            return $member?->division_id === null ? null : (int) $member->division_id;
        }

        return empty($validated['division_id']) ? null : (int) $validated['division_id'];
    }

    private function nullableDistrictId(array $validated, ?LatestNews $member = null): ?int
    {
        if (array_key_exists('district_id', $validated)) {
            return empty($validated['district_id']) ? null : (int) $validated['district_id'];
        }

        if (! array_key_exists('division_id', $validated)) {
            return $member?->district_id === null ? null : (int) $member->district_id;
        }

        if (empty($validated['division_id']) || (int) $validated['division_id'] !== (int) $member?->division_id) {
            return null;
        }

        return $member?->district_id === null ? null : (int) $member->district_id;
    }

}
