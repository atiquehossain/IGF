<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\District;
use App\Models\Division;
use App\Models\NoticeBoard;

use App\Helper\IgfFile;
use App\Helper\StaticUtil;
use App\Helper\Str;
use App\Services\LocalizationManager;
use App\Services\SafeMediaReplacementService;
use Exception;
use Throwable;
use Illuminate\Support\Str as SupportStr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NoticeBoardController extends Controller
{
    public function __construct(
        private LocalizationManager $localization,
        private SafeMediaReplacementService $media,
    ) {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->PublicationTitle;
        $search = $request->search;

        $notice_boards = NoticeBoard::select('notice_boards.*')
            ->with(['division', 'district'])
            ->withCount('translations')
            ->where('title', 'like', '%' . $search . '%')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('admin.notice-board.index')->with(compact('title', 'notice_boards', 'search'));
    }

    public function create(Request $request)
    {
        $title = $request->Lang->PublicationTitle . " " . $request->Lang->Common->Create;
        [$locales, $translationSources, $defaultLocale, $translationSourceId] = $this->translationOptions();
        [$divisions, $districts] = $this->geographyOptions();

        return view('admin.notice-board.add')->with(compact(
            'title',
            'locales',
            'translationSources',
            'defaultLocale',
            'translationSourceId',
            'divisions',
            'districts'
        ));
    }

    public function store(Request $request)
    {
        $this->validate(request(), array_merge([
            'image_path' => 'mimes:jpeg,png,jpg|max:3000',
            'title' => 'required|string|unique:notice_boards,title',
            'image_alt' => ['nullable', 'string', 'max:420'],
            'language' => ['required', Rule::in($this->localeIds())],
            'translation_source_id' => ['nullable', 'integer', 'exists:notice_boards,id'],
        ], $this->eventRules(), $this->geographyRules($request)));
        $translationKey = $this->translationKeyFor($request);
        $asset = null;
        $committed = false;

        try {
            $request['slug'] = Str::slug(@$request->title);

            $description = StaticUtil::pageRemoveNewLine(@$request->description);
            $inline_css = StaticUtil::pageRemoveNewLine(@$request->inline_css);
            if ($request->hasFile('image_path')) {
                $asset = $this->media->stageResizedPublicImage(
                    $request->file('image_path'),
                    'notice_board',
                    410,
                    240,
                );
            }

            $notice_board = DB::transaction(fn (): NoticeBoard => NoticeBoard::create(array_merge([
                    'translation_key' => $translationKey,
                    'title' => $request->title,
                    'sub_title' => @$request->sub_title,
                    'slug' => $request->slug,
                    'description' => @$description,
                    'inline_css' => @$inline_css,
                    'published_at' => date('Y-m-d', strtotime($request->published_at)),
                    'publisher_name' => $request->publisher_name,
                    'url' => @$request->url,
                    'location' => @$request->location,
                    'image_alt' => $this->plainText($request->input('image_alt')),
                    'language' => $request->language,
                    'notice_type' => 'notice-board',
                    'ip' => @$request->ip(),
                    'order_by' => @$request->order_by,
                    'status' => 0,
                ], $this->eventPayload($request), $this->geographyPayload($request), $asset ? [
                    'image_path' => $asset->databaseValue,
                ] : [])));
            $committed = true;

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('notice.board.edit', $notice_board->id))->with($notification);
            } else {
                return redirect(route('notice.board.index'))->with($notification);
            }
        } catch (Throwable $e) {
            if (!$committed && $asset) {
                $this->media->discardMany([$asset]);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request)
    {
        return redirect()->route('notice.board.index')->with([
            'message' => 'Open an event or news item with Edit.',
            'alert-type' => 'info',
        ]);
    }

    public function edit($id = null, Request $request)
    {
        $title = $request->Lang->PublicationTitle . " " . $request->Lang->Common->Update;

        try {
            $notice_board = NoticeBoard::find($id);
            abort_unless($notice_board, 404);
            [$locales, $translationSources, $defaultLocale, $translationSourceId] = $this->translationOptions($notice_board);
            [$divisions, $districts] = $this->geographyOptions();

            return view('admin.notice-board.edit')->with(compact(
                'title',
                'notice_board',
                'locales',
                'translationSources',
                'defaultLocale',
                'translationSourceId',
                'divisions',
                'districts'
            ));
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request)
    {
        $this->validate(request(), array_merge([
            'image_path' => 'mimes:jpeg,png,jpg|max:3000',
            'title' => 'required|string|unique:notice_boards,title,' . $request->id,
            'image_alt' => ['nullable', 'string', 'max:420'],
            'language' => ['required', Rule::in($this->localeIds())],
            'translation_source_id' => ['nullable', 'integer', 'exists:notice_boards,id'],
        ], $this->eventRules(), $this->geographyRules($request)));

        $notice_board = NoticeBoard::find($request->id);
        if (empty($notice_board)) {
            return back()->with([
                'message' => $request->Lang->Common->Form->NotFound,
                'alert-type' => 'warning',
            ]);
        }
        $translationKey = $this->translationKeyFor($request, $notice_board);
        $asset = null;
        $oldImage = null;
        $committed = false;

        try {
            $description = StaticUtil::pageRemoveNewLine(@$request->description);
            $inline_css = StaticUtil::pageRemoveNewLine(@$request->inline_css);
            if ($request->hasFile('image_path')) {
                $asset = $this->media->stageResizedPublicImage(
                    $request->file('image_path'),
                    'notice_board',
                    410,
                    240,
                );
            }

            DB::transaction(function () use (
                $request,
                $notice_board,
                $translationKey,
                $description,
                $inline_css,
                $asset,
                &$oldImage,
            ): void {
                $lockedNotice = NoticeBoard::query()
                    ->lockForUpdate()
                    ->findOrFail($notice_board->id);
                if ($asset) {
                    $oldImage = $lockedNotice->image_path;
                }

                $lockedNotice->update(array_merge([
                    'translation_key' => $translationKey,
                    'title' => $request->title,
                    'sub_title' => @$request->sub_title,
                    'description' => @$description,
                    'inline_css' => @$inline_css,
                    'published_at' => date('Y-m-d', strtotime($request->published_at)),
                    'publisher_name' => $request->publisher_name,
                    'notice_type' => 'notice-board',
                    'order_by' => @$request->order_by,
                    'url' => @$request->url,
                    'location' => @$request->location,
                    'image_alt' => $request->exists('image_alt')
                        ? $this->plainText($request->input('image_alt'))
                        : $lockedNotice->image_alt,
                    'language' => $request->language,
                    'ip' => $request->ip(),
                ], $this->eventPayload($request, $lockedNotice), $this->geographyPayload($request, $lockedNotice), $asset ? [
                    'image_path' => $asset->databaseValue,
                ] : []));
            });
            $committed = true;
            if ($asset) {
                $this->media->deleteLegacyFlatImages('notice_board', $oldImage);
            }

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('notice.board.edit', $request->id))->with($notification);
            } else {
                return redirect(route('notice.board.index'))->with($notification);
            }
        } catch (Throwable $e) {
            if (!$committed && $asset) {
                $this->media->discardMany([$asset]);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request)
    {
        try {
            if ($request->ajax()) {
                $data = NoticeBoard::findOrFail($request->route('id'));
                if (!(bool) $data->status && ($message = $this->inactiveGeographyMessage($data))) {
                    return response(['message' => $message], 409);
                }
                $data->status = $data->status ^ 1;
                $data->update();
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    private function inactiveGeographyMessage(NoticeBoard $notice): ?string
    {
        if ($notice->division_id !== null
            && ! Division::query()->whereKey($notice->division_id)->where('status', 1)->exists()) {
            return 'Publish the selected activity division before publishing this item.';
        }

        if ($notice->district_id !== null
            && ! District::query()
                ->whereKey($notice->district_id)
                ->where('division_id', $notice->division_id)
                ->where('status', 1)
                ->whereHas('division', fn ($query) => $query->where('status', 1))
                ->exists()) {
            return 'Choose an active district inside an active activity division before publishing this item.';
        }

        return null;
    }

    public function destroy($id = null, Request $request)
    {
        try {
            $notice_board = NoticeBoard::find($id);
            $notice_board->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function image($img = null)
    {
        return IgfFile::image('/notice_board/' . $img);
    }

    public function fileDownloadPath(?string $filename = null)
    {
        [$notice, $filePath] = $this->publicFile($filename);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $notice->title ?: 'notice');

        return response()->download(
            $filePath,
            trim($downloadName, '-.') . ($extension ? '.' . $extension : ''),
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function pdfViewPath(?string $filename = null)
    {
        [, $filePath] = $this->publicFile($filename);

        abort_unless(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf', 404);

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0: NoticeBoard, 1: string} */
    private function publicFile(?string $filename): array
    {
        abort_if(empty($filename) || basename($filename) !== $filename, 404);

        $notice = NoticeBoard::query()
            ->publiclyReleased()
            ->where('file_path', $filename)
            ->firstOrFail();
        $disk = Storage::disk('local');
        $root = realpath($disk->path('notice-attachments'));
        $filePath = realpath($disk->path('notice-attachments/' . $filename));

        abort_if(
            $root === false
                || $filePath === false
                || !str_starts_with($filePath, $root . DIRECTORY_SEPARATOR)
                || !is_file($filePath),
            404
        );

        return [$notice, $filePath];
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: string, 3: ?int} */
    private function translationOptions(?NoticeBoard $notice = null): array
    {
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $translationSources = NoticeBoard::query()
            ->where('language', $defaultLocale)
            ->whereNotNull('translation_key')
            ->when($notice, fn ($query) => $query->whereKeyNot($notice->getKey()))
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'translation_key']);
        $translationSourceId = null;

        if ($notice && $notice->language !== $defaultLocale && filled($notice->translation_key)) {
            $translationSourceId = $translationSources
                ->firstWhere('translation_key', $notice->translation_key)?->id;
        }

        return [$this->localization->editorLocales(), $translationSources, $defaultLocale, $translationSourceId];
    }

    /** @return array<int, string> */
    private function localeIds(): array
    {
        return $this->localization->editorLocales()->pluck('id')->all();
    }

    private function translationKeyFor(Request $request, ?NoticeBoard $notice = null): string
    {
        $locale = (string) $request->input('language');
        $defaultLocale = (string) config('app.fallback_locale', 'en');
        $sourceId = $request->integer('translation_source_id') ?: null;

        if ($notice && !$request->exists('translation_source_id')) {
            // Older admin clients do not know about pairing yet. An ordinary
            // content update must not silently break an existing translation.
            $translationKey = (string) $notice->translation_key;
        } elseif ($sourceId) {
            $source = NoticeBoard::query()
                ->whereKey($sourceId)
                ->where('language', $defaultLocale)
                ->first();

            if (!$source || ($notice && $source->is($notice)) || $locale === $defaultLocale) {
                throw ValidationException::withMessages([
                    'translation_source_id' => 'Choose an English event only when this record is a translated version.',
                ]);
            }

            $translationKey = (string) $source->translation_key;
        } else {
            $currentSourceExists = $notice
                && $notice->language !== $defaultLocale
                && filled($notice->translation_key)
                && NoticeBoard::query()
                    ->where('language', $defaultLocale)
                    ->where('translation_key', $notice->translation_key)
                    ->exists();

            // Keep established independent identities stable. Choosing
            // "separate event" on a currently paired translation deliberately
            // gives that record a fresh identity and breaks only that pairing.
            $translationKey = $notice && !$currentSourceExists
                ? (string) $notice->translation_key
                : ($notice && $locale === $defaultLocale
                    ? (string) $notice->translation_key
                    : (string) SupportStr::uuid());
        }

        if ($translationKey === '') {
            $translationKey = (string) SupportStr::uuid();
        }

        $duplicate = NoticeBoard::withTrashed()
            ->where('translation_key', $translationKey)
            ->where('language', $locale)
            ->when($notice, fn ($query) => $query->whereKeyNot($notice->getKey()))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'translation_source_id' => 'That event already has a version in this language. Edit or restore the existing version instead.',
            ]);
        }

        return $translationKey;
    }

    /** @return array<string, mixed> */
    private function eventRules(): array
    {
        return [
            'published_at' => ['required', 'date'],
            'location' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn (): bool => request('content_kind') === 'event'
                    && request('event_attendance_mode') !== 'online'),
            ],
            'content_kind' => ['nullable', Rule::in(['article', 'event'])],
            'event_start_at' => ['nullable', 'required_if:content_kind,event', 'date'],
            'event_end_at' => ['nullable', 'date', 'after_or_equal:event_start_at'],
            'event_status' => [
                'nullable',
                'required_if:content_kind,event',
                Rule::in(['scheduled', 'postponed', 'cancelled', 'rescheduled', 'moved-online']),
            ],
            'event_attendance_mode' => [
                'nullable',
                'required_if:content_kind,event',
                Rule::in(['offline', 'online', 'mixed']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function eventPayload(Request $request, ?NoticeBoard $notice = null): array
    {
        if (!$request->exists('content_kind') && $notice) {
            return [
                'content_kind' => $notice->content_kind ?: 'article',
                'event_start_at' => $notice->event_start_at,
                'event_end_at' => $notice->event_end_at,
                'event_status' => $notice->event_status,
                'event_attendance_mode' => $notice->event_attendance_mode,
            ];
        }

        $kind = $request->input('content_kind') === 'event' ? 'event' : 'article';

        return [
            'content_kind' => $kind,
            'event_start_at' => $kind === 'event' ? $request->input('event_start_at') : null,
            'event_end_at' => $kind === 'event' ? $request->input('event_end_at') : null,
            'event_status' => $kind === 'event' ? $request->input('event_status') : null,
            'event_attendance_mode' => $kind === 'event' ? $request->input('event_attendance_mode') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function geographyRules(Request $request): array
    {
        return [
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
                        $fail('Choose an activity division before choosing a district.');
                    }
                },
                Rule::exists('districts', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->where('division_id', $request->input('division_id'))),
            ],
        ];
    }

    /** @return array<string, ?int> */
    private function geographyPayload(Request $request, ?NoticeBoard $notice = null): array
    {
        if (! $request->exists('division_id') && ! $request->exists('district_id') && $notice) {
            return [
                'division_id' => $notice->division_id === null ? null : (int) $notice->division_id,
                'district_id' => $notice->district_id === null ? null : (int) $notice->district_id,
            ];
        }

        $divisionId = $request->filled('division_id') ? $request->integer('division_id') : null;

        return [
            'division_id' => $divisionId,
            'district_id' => $divisionId && $request->filled('district_id')
                ? $request->integer('district_id')
                : null,
        ];
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection} */
    private function geographyOptions(): array
    {
        $divisions = Division::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
        $districts = District::query()
            ->where('status', 1)
            ->whereIn('division_id', $divisions->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'division_id', 'name']);

        return [$divisions, $districts];
    }

    private function plainText(mixed $value): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
