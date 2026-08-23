<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\LocalizationManager;
use App\Services\TranslationCenterService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TranslationCenterController extends Controller
{
    public function __construct(
        private TranslationCenterService $translations,
        private LocalizationManager $localization
    ) {
    }

    public function index(Request $request)
    {
        [$sourceLocale, $targetLocale] = $this->locales($request);
        $allRows = $this->translations->rows($sourceLocale, $targetLocale);
        $summary = $this->translations->summary($allRows);
        $group = $request->string('group')->toString();
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $filtered = $allRows
            ->when($group !== '', fn ($rows) => $rows->where('group', $group))
            ->when($status === 'missing', fn ($rows) => $rows->where('required', true)->where('status', 'missing'))
            ->when($status === 'translated', fn ($rows) => $rows->where('status', 'translated'))
            ->when($status === 'optional', fn ($rows) => $rows->where('required', false))
            ->when($search !== '', function ($rows) use ($search) {
                $needle = Str::lower($search);
                return $rows->filter(fn (array $row) => Str::contains(Str::lower(
                    $row['context'] . ' ' . $row['field'] . ' ' . strip_tags($row['source']) . ' ' . strip_tags($row['target'])
                ), $needle));
            })
            ->values();

        $perPage = 40;
        $page = max(1, $request->integer('page', 1));
        $rows = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.translation-center.index', [
            'title' => 'Translation Center',
            'rows' => $rows,
            'summary' => $summary,
            'sourceLocale' => $sourceLocale,
            'targetLocale' => $targetLocale,
            'locales' => $this->localization->editorLocales(),
            'targetLanguage' => $this->localization->locale($targetLocale),
            'group' => $group,
            'status' => $status,
            'search' => $search,
            'groups' => [
                'interface' => 'Website interface',
                'settings' => 'Website settings',
                'pages' => 'Content Hub',
                'navigation' => 'Menus',
                'content' => 'Other content',
                'seo' => 'SEO',
            ],
        ]);
    }

    public function update(Request $request)
    {
        [$sourceLocale, $targetLocale] = $this->locales($request);
        $data = $request->validate([
            'translations' => ['required', 'array', 'min:1', 'max:100'],
            'translations.*.key' => ['required', 'string', 'size:64', 'distinct'],
            'translations.*.precondition' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
            'translations.*.value' => ['nullable', 'string', 'max:25000'],
        ]);

        $saved = $this->translations->save(
            $sourceLocale,
            $targetLocale,
            $data['translations'],
            auth('admin')->id()
        );

        return back()->with([
            'message' => $saved === 0 ? 'Everything on this page was already saved.' : "{$saved} translation " . ($saved === 1 ? 'was' : 'were') . ' saved.',
            'alert-type' => 'success',
        ]);
    }

    public function toggle(Request $request)
    {
        [$sourceLocale, $targetLocale] = $this->locales($request);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $data['enabled'];

        DB::transaction(function () use ($sourceLocale, $targetLocale, $enabled): void {
            $this->translations->lockWorkspace($targetLocale);

            if ($enabled) {
                $summary = $this->translations->summary($this->translations->rows($sourceLocale, $targetLocale));
                if ($summary['missing'] > 0) {
                    throw ValidationException::withMessages([
                        'enabled' => "Translate the remaining {$summary['missing']} required rows before publishing Bangla.",
                    ]);
                }
                $this->translations->syncPublicationState($sourceLocale, $targetLocale);
            }
            $this->localization->setEnabled($targetLocale, $enabled, auth('admin')->id());
            SiteSetting::query()->updateOrCreate(
                ['group' => 'header', 'key' => 'show_language_switcher', 'locale' => '*'],
                [
                    'value' => $enabled ? '1' : '0',
                    'type' => 'boolean',
                    'is_public' => true,
                    'created_by' => auth('admin')->id(),
                    'updated_by' => auth('admin')->id(),
                ]
            );
        });

        return back()->with([
            'message' => $enabled ? 'Bangla is now available. Translated records now follow the same published or hidden state as their English source.' : 'Bangla has been hidden from the public website. Existing translations are safe.',
            'alert-type' => 'success',
        ]);
    }

    private function locales(Request $request): array
    {
        $available = $this->localization->editorLocales()->pluck('id')->all();
        $sourceLocale = $request->string('source_locale', 'en')->toString();
        $targetLocale = $request->string('target_locale', 'bn')->toString();
        abort_unless(in_array($sourceLocale, $available, true) && in_array($targetLocale, $available, true), 404);
        abort_if($sourceLocale === $targetLocale, 422, 'Choose two different languages.');

        return [$sourceLocale, $targetLocale];
    }
}
