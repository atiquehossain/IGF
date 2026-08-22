import { resolveSeoAlternates, resolveSeoMetadata, resolveStructuredData } from '@/Shared/seoMetadata';

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

  test('deployment indexing policy overrides every page owner during hydration', () => {
    expect(resolveSeoMetadata({
      seoDefaults: { robots: 'index,follow' },
      routeSeo: { robots: 'index,nofollow' },
      contentSeo: { robots: 'index,follow' },
      seoPolicy: { robots: 'noindex,nofollow,noarchive' },
    })).toMatchObject({
      robots: 'noindex,nofollow,noarchive',
    });
  });

  test('empty owned social fields use the managed fallback without reviving stale route media', () => {
    expect(resolveSeoMetadata({
      seoDefaults: {
        og_image: 'https://ignite.test/brand-share.jpg',
        twitter_image: 'https://ignite.test/brand-share.jpg',
      },
      routeSeo: { og_image: 'https://ignite.test/stale-route.jpg' },
      contentSeo: { og_image: '', twitter_image: '' },
    })).toMatchObject({
      og_image: 'https://ignite.test/brand-share.jpg',
      twitter_image: 'https://ignite.test/brand-share.jpg',
    });
  });

  test('page-specific social media remains authoritative over the brand fallback', () => {
    expect(resolveSeoMetadata({
      seoDefaults: { og_image: 'https://ignite.test/brand-share.jpg' },
      contentSeo: {
        og_image: 'https://cdn.example.org/story.jpg',
        twitter_image: 'https://cdn.example.org/story-x.jpg',
      },
    })).toMatchObject({
      og_image: 'https://cdn.example.org/story.jpg',
      twitter_image: 'https://cdn.example.org/story-x.jpg',
    });
  });

  test('unsafe social image schemes fail closed to the managed fallback', () => {
    expect(resolveSeoMetadata({
      seoDefaults: { og_image: 'https://ignite.test/brand-share.jpg' },
      contentSeo: { og_image: 'javascript:alert(1)', twitter_image: 'data:image/png;base64,abc' },
    })).toMatchObject({
      og_image: 'https://ignite.test/brand-share.jpg',
      twitter_image: 'https://ignite.test/brand-share.jpg',
    });
  });

  test('missing schema composes an honest WebPage from final metadata and server identity', () => {
    const json = resolveStructuredData({
      identity: {
        '@context': 'https://schema.org',
        '@graph': [
          { '@type': 'NGO', '@id': 'https://ignite.test/#organization', name: 'Ignite' },
          { '@type': 'WebSite', '@id': 'https://ignite.test/#website', name: 'Ignite' },
        ],
      },
      metadata: {
        meta_title: 'Clean water',
        meta_description: '<p>Safe water for communities.</p>',
        canonical_url: 'https://ignite.test/page/clean-water',
        og_image: 'https://ignite.test/share/clean-water.jpg',
      },
      locale: 'en',
    });
    const graph = JSON.parse(json)['@graph'];
    const page = graph.find((node) => node['@type'] === 'WebPage');

    expect(graph).toHaveLength(3);
    expect(page).toMatchObject({
      name: 'Clean water',
      description: 'Safe water for communities.',
      url: 'https://ignite.test/page/clean-water',
      inLanguage: 'en',
      isPartOf: { '@id': 'https://ignite.test/#website' },
      about: { '@id': 'https://ignite.test/#organization' },
      primaryImageOfPage: { url: 'https://ignite.test/share/clean-water.jpg' },
    });
  });

  test('explicit expert or content schema is preserved byte-for-byte', () => {
    const explicit = '{"@context":"https://schema.org","@type":"Article","headline":"Field update"}';
    expect(resolveStructuredData({ schema: explicit })).toBe(explicit);
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
