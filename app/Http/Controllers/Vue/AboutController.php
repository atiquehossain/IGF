<?php

namespace App\Http\Controllers\Vue;

use App\Helper\StaticUtil;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use App\Services\PublicSystemPageMetaService;
use App\Services\SeoMetadataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AboutController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageBlockContentResolver $blockResolver,
        private PublicSystemPageMetaService $systemMeta,
        private SeoMetadataService $seo,
    ) {
    }

    public function about(Request $request, $slug = null)
    {
        $about = Page::with(['banner', 'visibleBlocks.reusableBlock'])
            ->publiclyAvailable()
            ->where('language', app()->getLocale())
            ->where('slug', 'about-us')
            ->firstOrFail();

        $foundersLetter = Page::publiclyAvailable()
            ->where('language', app()->getLocale())
            ->where('slug', "founder's-letter")
            ->first();

        $about->setAttribute('description', $this->sanitizer->sanitizeHtml($about->description));
        $about->setAttribute('inline_css', $this->sanitizer->sanitizeCss($about->inline_css));
        $about->visibleBlocks->each(function ($block) {
            $block->setAttribute('content', $this->blockResolver->resolve($block));
            $block->setAttribute('settings', $block->resolvedSettings());
            $block->setAttribute('is_reusable', (bool) $block->reusable_block_id);
            $block->unsetRelation('reusableBlock');
        });

        if ($foundersLetter) {
            $foundersLetter->setAttribute('description', $this->sanitizer->sanitizeHtml($foundersLetter->description));
        }

        $metaTag = $this->seo->metaForModel(
            $about,
            $this->systemMeta->forPage($about, $request),
            url()->current(),
            (string) $about->language,
        );
        $metaTag['canonical_url'] = $metaTag['canonical_url'] ?: url()->current();
        if ($about->visibility === 'unlisted') {
            $metaTag['robots'] = 'noindex,nofollow';
        }
        StaticUtil::ssr($metaTag);

        return Inertia::render('about')->with([
            'status' => true,
            'title' => $about->name,
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'data' => [
                'banner' => $about->banner,
                'founders_letter' => $foundersLetter,
                'about_us' => $about,
            ],
        ]);
    }
}
