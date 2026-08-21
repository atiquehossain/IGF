import { resolveSeoAlternates, resolveSeoMetadata } from '@/Shared/seoMetadata';

describe('public SEO authority resolution', () => {
  test('curated route SEO wins over a controller fallback during hydration', () => {
    expect(resolveSeoMetadata({
      seoDefaults: { meta_title: 'Default', canonical_url: '/default' },
      metaTag: { meta_title: 'Controller fallback', canonical_url: '/controller' },
      routeSeo: { meta_title: 'Curated route', canonical_url: '/curated' },
    })).toMatchObject({
      meta_title: 'Curated route',
      canonical_url: '/curated',
    });
  });

  test('owned Page or item SEO is the final authority during hydration', () => {
    expect(resolveSeoMetadata({
      seoDefaults: { meta_title: 'Default', robots: 'index,follow' },
      metaTag: { meta_title: 'Controller fallback' },
      routeSeo: { meta_title: 'Stale route', robots: 'noindex,nofollow' },
      contentSeo: { meta_title: 'Owned content', robots: 'index,follow' },
    })).toMatchObject({
      meta_title: 'Owned content',
      robots: 'index,follow',
    });
  });

  test('uses only verified alternates and preserves each translated slug', () => {
    expect(resolveSeoAlternates({
      cluster: {
        links: [
          { locale: 'en', url: 'https://ignite.test/page/english-story' },
          { locale: 'bn', url: 'https://ignite.test/page/bangla-story?lang=bn' },
        ],
        x_default: 'https://ignite.test/page/english-story',
      },
      canonicalUrl: 'https://ignite.test/page/english-story',
      currentLocale: 'en',
    })).toEqual({
      links: [
        { locale: 'en', url: 'https://ignite.test/page/english-story' },
        { locale: 'bn', url: 'https://ignite.test/page/bangla-story?lang=bn' },
      ],
      xDefault: 'https://ignite.test/page/english-story',
    });
  });

  test('falls back to the current canonical only and never invents a missing locale', () => {
    expect(resolveSeoAlternates({
      canonicalUrl: 'https://ignite.test/page/english-only',
      currentLocale: 'en',
    })).toEqual({
      links: [{ locale: 'en', url: 'https://ignite.test/page/english-only' }],
      xDefault: 'https://ignite.test/page/english-only',
    });
  });
});
