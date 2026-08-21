/**
 * Resolve public SEO using the same authority contract as app.blade.php.
 *
 * Controller metadata is a backwards-compatible fallback. A curated static
 * route may replace that fallback, while SEO attached to the Page/item itself
 * is always the final authority for that content.
 */
export const resolveSeoMetadata = ({
  seoDefaults = {},
  metaTag = {},
  routeSeo = {},
  contentSeo = {},
} = {}) => ({
  ...(seoDefaults ?? {}),
  ...(metaTag ?? {}),
  ...(routeSeo ?? {}),
  ...(contentSeo ?? {}),
});

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
