<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Gallery;
use App\Services\PublicArchiveSeoService;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GalleryController extends Controller
{
    public function __construct(
        private PublicArchiveSeoService $archiveSeo,
        private PublicStructuredDataService $structuredData,
        private SeoMetadataService $seo,
    ) {
    }

    public function gallery(Request $request, $slug = null)
    {
        $search = trim((string) $request->query('search', ''));
        $albumId = $request->integer('album_id') ?: null;
        $locale = app()->getLocale();

        $albums = Album::select('id', 'name')
            ->where('status', 1)
            ->where('language', $locale)
            ->orderBy('name')
            ->get();

        $results = Gallery::query()
            ->select('galleries.id', 'galleries.name', 'galleries.description', 'galleries.path', 'galleries.url', 'galleries.grid_column', 'galleries.grid_row', 'albums.name as album_name')
            ->leftJoin('albums', 'albums.id', '=', 'galleries.album_id')
            ->where('galleries.status', 1)
            ->where('galleries.type', 'gallery')
            ->where('galleries.language', $locale)
            ->when($search !== '', fn ($query) => $query->where('galleries.name', 'like', '%' . $search . '%'))
            ->when($albumId, fn ($query) => $query->where('galleries.album_id', $albumId))
            ->orderByDesc('galleries.updated_at')
            ->paginate(12)
            ->withQueryString();

        $this->archiveSeo->abortIfOutOfRange($results);

        $results->getCollection()->transform(function ($item) {
            $item->alt_text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item->description)) ?? '');
            $libraryUrl = $this->safeMediaLibraryUrl((string) $item->url);
            if ($libraryUrl !== null) {
                $item->path = $libraryUrl;
                $item->main_path = $libraryUrl;

                return $item;
            }

            $filename = ltrim((string) $item->path, '/');
            $item->path = '/storage/photos/1/gallery/' . $item->id . '/430X360/' . $filename;
            $item->main_path = '/storage/photos/1/gallery/' . $item->id . '/main/' . $filename;
            return $item;
        });

        $metaTag = $this->archiveSeo->apply(array_merge([
            'meta_keyword' => 'Ignite Global Foundation gallery, community programs Bangladesh',
            'meta_title' => 'Photo Gallery | Ignite Global Foundation',
            'meta_description' => 'See people, partnerships, and community-led programs in the Ignite Global Foundation photo gallery.',
        ], (array) $request->attributes->get('route_seo', [])), $request, $results, route('frontend.gallery'));
        if (empty($metaTag['schema_markup']) || $results->currentPage() > 1) {
            $canonical = (string) $metaTag['canonical_url'];
            $metaTag['schema_markup'] = $this->structuredData->collection(
                'Photo Gallery',
                (string) $metaTag['meta_description'],
                $canonical,
                [
                    ['name' => 'Home', 'url' => (string) $this->seo->localizedUrl(url('/'), $locale)],
                    ['name' => 'Photo Gallery', 'url' => $canonical],
                ]
            );
        }

        return Inertia::render('gallery')->with([
            'status' => true,
            'title' => 'Photo Gallery',
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'seoAlternates' => $this->archiveSeo->alternateUrls((string) $metaTag['canonical_url']),
            'properties' => [
                'page' => $results->currentPage(),
                'total_page' => $results->lastPage(),
                'total_count' => $results->total(),
                'search' => $search,
                'album_id' => $albumId,
            ],
            'data' => [
                'albums' => $albums,
                'items' => $results->items(),
            ],
        ]);
    }

    private function safeMediaLibraryUrl(string $value): ?string
    {
        $path = parse_url(trim($value), PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#^/storage/media/[a-z0-9/_-]+\.(?:avif|gif|jpe?g|png|webp)$#i', $path)) {
            return null;
        }

        return $path;
    }
}
