import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { resolveSeoAlternates } from '../seoMetadata';

const withExplicitLocale = (url, locale, parameter) => {
  const source = String(url || '').trim();
  if (!source) return source;

  try {
    const isAbsolute = /^https?:\/\//i.test(source);
    const parsed = new URL(source, 'http://localhost');
    parsed.searchParams.set(String(parameter || 'lang'), String(locale));

    return isAbsolute ? parsed.toString() : `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return source;
  }
};

/**
 * Expose only server-verified public locale links. Never manufacture a
 * translated URL by changing the current query string: translated content can
 * have a different slug, or no published counterpart at all.
 */
export function usePublicLocaleSwitcher() {
  const inertiaPage = usePage();
  const currentLocale = computed(() => inertiaPage.props?.seoLocale?.current
    || inertiaPage.props?.locale
    || 'en');
  const links = computed(() => {
    const parameter = inertiaPage.props?.seoLocale?.query_parameter || 'lang';
    return resolveSeoAlternates({
      cluster: inertiaPage.props?.seoAlternates,
      canonicalUrl: inertiaPage.url || '/',
      currentLocale: currentLocale.value,
    }).links.map((link) => ({
      ...link,
      url: withExplicitLocale(link.url, link.locale, parameter),
    }));
  });
  const enabled = computed(() => Boolean(inertiaPage.props?.publicLocaleSwitcherEnabled)
    && inertiaPage.props?.siteSettings?.header?.show_language_switcher === true
    && links.value.length > 1);
  const languageLabel = (locale) => ({
    en: inertiaPage.props?.siteSettings?.header?.english_language_label || 'EN',
    bn: inertiaPage.props?.siteSettings?.header?.bangla_language_label || 'বাংলা',
  }[locale] || String(locale).toUpperCase());

  return {
    currentLocale,
    enabled,
    languageLabel,
    links,
  };
}
