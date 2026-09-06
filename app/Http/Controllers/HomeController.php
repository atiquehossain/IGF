<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Mail\ConfirmNewsletterSubscription;
use App\Models\Category;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\Subscriber;
use App\Services\ContentSanitizer;
use App\Services\SiteSettingService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class HomeController extends Controller
{
    private const GENERIC_SUBSCRIPTION_MESSAGE = 'If this address can receive updates, a confirmation link has been sent.';
    public function __construct(
        private ContentSanitizer $sanitizer,
        private SiteSettingService $siteSettings,
    ) {
    }

    private $meta_tag = [
        'meta_keyword' => '',
        'meta_title' => '',
        'meta_description' => '',
    ];

    public function contact(Request $request, $slug = null)
    {
        $title = 'Contact Us';
        return Inertia::render('contactUs')->with([
            'status' => true,
            'title' => $title,
            'meta_tag' => [
                'meta_keyword' => 'contact Ignite Global Foundation, nonprofit Bangladesh',
                'meta_title' => 'Contact Us | Ignite Global Foundation',
                'meta_description' => 'Contact Ignite Global Foundation about programs, partnerships, donations, volunteering, and community-led work in Bangladesh.',
            ],
            'data' => [],
        ]);
    }

    public function about(Request $request, $slug = null)
    {
        $title = '';
        try {

            $sponsers_host = Page::select('pages.*')
                ->publiclyAvailable()
                ->where('slug', 'donor-and-hosting-agency')
                ->first();

            $page = Page::select('pages.*')
                ->publiclyAvailable()
                ->with(['banner'])
                ->where('slug', @$slug)
                ->where('language', app()->getLocale())
                ->first();

            $banner = @$page->banner;
            $title = @$page->name;
            $meta_tag = [
                'meta_keyword' => @$page->meta_keyword,
                'meta_title' => @$page->meta_title,
                'meta_description' => @$page->meta_description,
            ];

            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $meta_tag,
                'data' => [
                    "page" => $page,
                    "banner" => $banner,
                    'sponsers_host' => $sponsers_host,
                ],
            ];
            return Inertia::render('about')->with($response);
        } catch (Exception $e) {
            return Inertia::render('errors-404');
        }
    }

    public function members(Request $request)
    {
        try {
            $search = $request->search;
            $ourMembers = LatestNews::select([
                    'latest_news.id',
                    'latest_news.name',
                    'latest_news.description',
                    'latest_news.url',
                    'latest_news.path',
                ])
                ->where('type', 'our-members')
                ->where('status', 1)
                ->where('name', 'like', '%' . $search . '%')
                ->where('language', app()->getLocale())
                ->orderBy('name', 'ASC')
                ->get()
                ->each(function (LatestNews $member): void {
                    $member->setAttribute('path', '/storage/photos/1/our_members/' . ltrim((string) $member->path, '/'));
                    $member->setAttribute('url', $this->sanitizer->sanitizeUrl($member->url));
                })
                ->chunk(4);

            $response = [
                'status' => true,
                'title' => '',
                'meta_tag' => $this->meta_tag,
                'data' => [
                    'ourMembers' => $ourMembers,
                ],
            ];
            return Inertia::render('members')->with($response);
        } catch (Exception $e) {
            return Inertia::render('errors-404');
        }
    }

    // publication
    public function notice(Request $request, $slug = null)
    {
        try {
            $results = NoticeBoard::select('notice_boards.*')
                ->publiclyReleased()
                ->selectRaw('DATE_FORMAT(published_at, "%d-%m-%Y") as date_at')
                ->selectRaw('DATE_FORMAT(created_at, "%d-%m-%Y") as cdate_at')
                ->selectRaw('CONCAT("/storage/photos/1/notice_board/", notice_boards.file_path) as path')
                ->where('notice_type', 'notice-board')
                ->where('language', app()->getLocale())
                ->orderBy('notice_boards.published_at', 'desc')
                ->paginate(12);

            $response = [
                'status' => true,
                'properties' => [
                    'page' => $results->currentPage(),
                    'total_page' => $results->lastPage(),
                ],
                'data' => [
                    "items" => $results->items(),
                ],
            ];
            return Inertia::render('notice')->with($response);
        } catch (Exception $e) {
            return Inertia::render('errors-404');
        }
    }

    public function recentPost(Request $request, $slug = null)
    {
        $title = '';
        try {
            $search = $request->search;
            $category = Category::select('categories.*')
                ->with(['banner'])
                ->where('status', 1)
                ->where('slug', $slug)
                ->where('language', app()->getLocale())
                ->first();

            $recentPost = Page::select('pages.*', 'categories.name as category_name')
                ->publiclyAvailable()
                ->leftjoin('categories', 'categories.id', '=', 'pages.category_id')
                ->selectRaw('(SELECT count(id) FROM comments where comments.page_id = pages.id) as total_commtnts')
                ->selectRaw('DATE_FORMAT(pages.published_at, "%d-%m-%Y") as published_at')
                ->where('categories.status', 1)
                ->where('pages.language', app()->getLocale())
                ->orderBy('pages.published_at', 'desc')
                ->limit(6)
                ->get();
            $page = Page::select('pages.*', 'categories.name as category_name')
                ->publiclyAvailable()
                ->leftjoin('categories', 'categories.id', '=', 'pages.category_id')
                ->selectRaw('(SELECT count(id) FROM comments where comments.page_id = pages.id) as total_commtnts')
                ->selectRaw('DATE_FORMAT(pages.published_at, "%d-%m-%Y") as published_at')
                ->where(function ($query) use ($search) {
                    if (!empty($search)) {
                        $query->where('pages.name', 'like', '%' . $search . '%');
                        $query->orWhere('pages.description', 'like', '%' . $search . '%');
                    }
                })
                ->where('categories.status', 1)
                ->where('pages.language', app()->getLocale())
                ->orderBy('pages.published_at', 'desc')
                ->paginate(6);

            $title = @$category->name;
            $meta_tag = [
                'meta_keyword' => @$category->meta_keyword,
                'meta_title' => @$category->meta_title,
                'meta_description' => @$category->meta_description,
            ];

            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $meta_tag,
                'properties' => [
                    'page' => $page->currentPage(),
                    'total_page' => $page->lastPage(),

                ],
                'data' => [
                    "search" => $search,
                    "banner" => $category->banner,
                    "category" => $category,
                    'recent_post' => $recentPost,
                    "items" => $page->items(),
                ],
            ];
            return Inertia::render('category')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $this->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }

    public function events(Request $request)
    {
        $title = '';
        try {
            $response = [
                'status' => true,
                'title' => 'Event Calander',
                'meta_tag' => $this->meta_tag,
                'data' => [],
            ];
            return Inertia::render('events')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $this->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'consent' => ['required', 'accepted'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $locale = app()->getLocale();
        $copy = (array) data_get($this->siteSettings->values($locale, true), 'shared_blocks', []);

        try {
            [$subscriber, $shouldSend] = DB::transaction(function () use ($email, $locale): array {
                $subscriber = Subscriber::query()->firstOrCreate(
                    ['email' => $email],
                    ['uuid' => (string) Str::uuid(), 'language' => $locale]
                );
                $subscriber = Subscriber::query()->lockForUpdate()->findOrFail($subscriber->getKey());

                if (blank($subscriber->uuid)) {
                    $subscriber->forceFill(['uuid' => (string) Str::uuid()])->save();
                }

                if ($subscriber->confirmed_at !== null) {
                    return [$subscriber, false];
                }

                if ($subscriber->language !== $locale) {
                    $subscriber->forceFill(['language' => $locale])->save();
                }

                $cooldown = max(1, (int) config('privacy.newsletter.resend_cooldown_minutes', 15));
                if ($subscriber->confirmation_sent_at?->isAfter(now()->subMinutes($cooldown))) {
                    return [$subscriber, false];
                }

                $subscriber->forceFill(['confirmation_sent_at' => now()])->save();

                return [$subscriber, true];
            });

            if ($shouldSend) {
                $confirmationUrl = URL::temporarySignedRoute(
                    'frontend.subscribe.confirm',
                    now()->addMinutes(max(1, (int) config('privacy.newsletter.confirmation_ttl_minutes', 1440))),
                    ['subscriber' => $subscriber->uuid, 'lang' => $locale]
                );

                try {
                    Mail::to($subscriber->email)->send(new ConfirmNewsletterSubscription(
                        $confirmationUrl,
                        $locale,
                        $copy,
                    ));
                } catch (Throwable $exception) {
                    Log::warning('Newsletter confirmation dispatch failed.', [
                        'subscriber_id' => $subscriber->getKey(),
                        'exception_class' => $exception::class,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Newsletter subscription request failed.', [
                'exception_class' => $exception::class,
            ]);
        }

        return back()->with('message', [
            'type' => 'success',
            'text' => trim((string) ($copy['newsletter_request_message'] ?? ''))
                ?: self::GENERIC_SUBSCRIPTION_MESSAGE,
        ]);
    }

    public function confirmSubscription(Request $request, string $subscriber)
    {
        $locale = DB::transaction(function () use ($subscriber): string {
            $record = Subscriber::query()
                ->where('uuid', $subscriber)
                ->lockForUpdate()
                ->first();

            if ($record !== null && $record->confirmed_at === null) {
                $record->forceFill(['confirmed_at' => now()])->save();
            }

            return trim((string) ($record?->language ?: app()->getLocale())) ?: 'en';
        });
        $request->session()->put('locale', $locale);
        app()->setLocale($locale);
        $copy = (array) data_get($this->siteSettings->values($locale, true), 'shared_blocks', []);
        $routeParameters = $locale === (string) config('app.fallback_locale', 'en')
            ? []
            : ['lang' => $locale];

        return redirect()->route('frontend.home', $routeParameters)->with('message', [
            'type' => 'success',
            'text' => trim((string) ($copy['newsletter_confirmed_message'] ?? ''))
                ?: 'Your email subscription is confirmed.',
        ]);
    }
}
