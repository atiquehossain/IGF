/**
 * Resolve public SEO using the same authority contract as app.blade.php.
 *
 * Controller metadata is a backwards-compatible fallback. A curated static
 * route may replace that fallback, while SEO attached to the Page/item itself
 * is always the final authority for that content.
 */
const hasControlCharacters = (value) => [...String(value || '')]
  .some((character) => character.charCodeAt(0) <= 31 || character.charCodeAt(0) === 127);

const withoutControlCharacters = (value) => [...String(value || '')]
  .map((character) => (character.charCodeAt(0) <= 31 || character.charCodeAt(0) === 127 ? ' ' : character))
  .join('');

const safeHttpUrl = (value) => {
  const candidate = String(value || '').trim();
  if (!/^https?:\/\//i.test(candidate) || hasControlCharacters(candidate)) return '';
  try {
    const parsed = new URL(candidate);
    parsed.hash = '';
    return parsed.toString();
  } catch {
    return '';
  }
};

const plainText = (value) => withoutControlCharacters(
  String(value || '').replace(/<[^>]*>/g, ' ')
).replace(/\s+/g, ' ').trim();

export const resolveSeoMetadata = ({
  seoDefaults = {},
  metaTag = {},
  routeSeo = {},
  contentSeo = {},
  seoPolicy = {},
} = {}) => {
  const merged = {
    ...(seoDefaults ?? {}),
    ...(metaTag ?? {}),
    ...(routeSeo ?? {}),
    ...(contentSeo ?? {}),
    ...(seoPolicy ?? {}),
  };
  const fallbackImage = safeHttpUrl(seoDefaults?.og_image)
    || safeHttpUrl(seoDefaults?.twitter_image);
  const ogImage = safeHttpUrl(merged.og_image)
    || safeHttpUrl(merged.meta_image)
    || safeHttpUrl(merged.twitter_image)
    || fallbackImage;
  const twitterImage = safeHttpUrl(merged.twitter_image) || ogImage;
  const altLayer = [contentSeo, routeSeo, metaTag]
    .find((layer) => layer && Object.prototype.hasOwnProperty.call(layer, 'social_image_alt'));
  const authoredAlt = plainText(altLayer?.social_image_alt).slice(0, 420);
  const fallbackAlt = plainText(seoDefaults?.social_image_alt).slice(0, 420);
  const ogImageAlt = !ogImage
    ? ''
    : (fallbackImage && ogImage === fallbackImage ? fallbackAlt : authoredAlt);
  const twitterImageAlt = !twitterImage
    ? ''
    : (fallbackImage && twitterImage === fallbackImage ? fallbackAlt : authoredAlt);

  return {
    ...merged,
    og_image: ogImage,
    twitter_image: twitterImage,
    social_image_alt: authoredAlt || ogImageAlt,
    og_image_alt: ogImageAlt,
    twitter_image_alt: twitterImageAlt,
  };
};

/**
 * Preserve explicit owner-authored schema. When none exists, compose a single
 * content-grounded WebPage node with the server-verified Organization and
 * WebSite identities. Breadcrumb/Event/Article nodes remain controller-owned
 * because they require facts that cannot be inferred safely from a URL.
 */
export const resolveStructuredData = ({
  schema = null,
  identity = null,
  metadata = {},
  locale = 'en',
} = {}) => {
  if (typeof schema === 'string' && schema.trim()) return schema;
  if (schema && typeof schema === 'object' && Object.keys(schema).length > 0) {
    return JSON.stringify(schema);
  }

  const graph = Array.isArray(identity?.['@graph'])
    ? identity['@graph']
      .filter((node) => node && ['NGO', 'Organization', 'WebSite'].includes(node['@type']))
      .map((node) => JSON.parse(JSON.stringify(node)))
    : [];
  if (identity?.['@context'] !== 'https://schema.org' || graph.length === 0) return '';

  const url = safeHttpUrl(metadata?.canonical_url);
  const name = plainText(metadata?.meta_title).slice(0, 300);
  if (url && name) {
    const organization = graph.find((node) => ['NGO', 'Organization'].includes(node['@type']));
    const website = graph.find((node) => node['@type'] === 'WebSite');
    const page = {
      '@type': 'WebPage',
      '@id': `${url}#webpage`,
      url,
      name,
      inLanguage: String(locale || 'en'),
    };
    const description = plainText(metadata?.meta_description).slice(0, 1000);
    if (description) page.description = description;
    if (website?.['@id']) page.isPartOf = { '@id': website['@id'] };
    if (organization?.['@id']) page.about = { '@id': organization['@id'] };
    const image = safeHttpUrl(metadata?.og_image || metadata?.twitter_image);
    if (image) {
      page.primaryImageOfPage = {
        '@type': 'ImageObject',
        '@id': `${image}#primaryimage`,
        url: image,
      };
    }
    graph.push(page);
  }

  return JSON.stringify({ '@context': 'https://schema.org', '@graph': graph });
};

/**
 * Consume the server-verified translation cluster. Never manufacture another
 * language by changing only the query string: translated Pages/Categories can
 * have different slugs, and some records have no translation at all.
 */
export const resolveSeoAlternates = ({
  cluster = {},
  canonicalUrl = '',
  currentLocale = 'en',
} = {}) => {
  const unique = new Map();
  const supplied = Array.isArray(cluster?.links) ? cluster.links : [];
  supplied.forEach((link) => {
    const locale = String(link?.locale || '').trim();
    const url = String(link?.url || '').trim();
    if (locale && url && !unique.has(locale)) unique.set(locale, url);
  });

  const canonical = String(canonicalUrl || '').trim();
  const locale = String(currentLocale || 'en').trim() || 'en';
  if (unique.size === 0 && canonical) unique.set(locale, canonical);

  const links = [...unique.entries()].map(([linkLocale, url]) => ({
    locale: linkLocale,
    url,
  }));
  const xDefault = String(cluster?.x_default || '').trim()
    || unique.get(locale)
    || links[0]?.url
    || canonical;

  return { links, xDefault };
};
