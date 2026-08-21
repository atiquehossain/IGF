<?php

namespace App\Http\Controllers\Vue;

use App\Helper\StaticUtil;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use App\Services\SeoMetadataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ZakatController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageBlockContentResolver $blockResolver,
    ) {
    }

    public function zakat(Request $request, $slug = null)
    {
        $zakat = Page::with(['banner', 'visibleBlocks.reusableBlock'])
            ->publiclyAvailable()
            ->where('language', app()->getLocale())
            ->where('slug', 'zakat')
            ->firstOrFail();

        $zakat->setAttribute('description', $this->sanitizer->sanitizeHtml($zakat->description));
        $zakat->setAttribute('inline_css', $this->sanitizer->sanitizeCss($zakat->inline_css));
        $zakat->visibleBlocks->each(function ($block) {
            $block->setAttribute('content', $this->blockResolver->resolve($block));
            $block->setAttribute('settings', $block->resolvedSettings());
            $block->setAttribute('is_reusable', (bool) $block->reusable_block_id);
            $block->unsetRelation('reusableBlock');
        });

        $metaTag = app(SeoMetadataService::class)->metaForPage($zakat);
        $metaTag['meta_title'] = $metaTag['meta_title'] ?: 'Zakat Giving | Ignite Global Foundation';
        $metaTag['meta_description'] = $metaTag['meta_description'] ?: 'Calculate your Zakat and support eligible education, food, and livelihood programs through Ignite Global Foundation.';
        $metaTag['canonical_url'] = $metaTag['canonical_url'] ?: url()->current();
        if ($zakat->visibility === 'unlisted') {
            $metaTag['robots'] = 'noindex,nofollow';
        }
        StaticUtil::ssr($metaTag);

        return Inertia::render('zakat')->with([
            'status' => true,
            'title' => $zakat->name ?: 'Zakat',
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'data' => [
                'banner' => $zakat->banner,
                'zakat' => $zakat,
            ],
        ]);
    }
}
