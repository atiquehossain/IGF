function text(value) {
  return String(value || '').trim()
}

export function parseLegacyBannerName(value) {
  const name = text(value)
  const match = name.match(/<b>(.*?)<\/b>/i)

  return {
    headline: match ? text(match[1]) : '',
    subheadline: match ? text(name.replace(match[0], '')) : name,
  }
}

export function normalizeBannerMediaUrl(value) {
  const source = text(value).replace(/\\/g, '/')
  if (!source || /^(?:javascript|data):/i.test(source)) return ''
  if (/^(?:https?:)?\/\//i.test(source) || source.startsWith('/')) return source
  if (/^(?:storage|image)\//i.test(source)) return `/${source}`

  const filename = source.split('/').filter(Boolean).pop() || ''
  return filename ? `/storage/photos/1/banner/${encodeURIComponent(filename)}` : ''
}

export function normalizeBannerLink(value) {
  const href = text(value)
  if (!href || /^(?:javascript|data|vbscript):/i.test(href)) return ''
  if (/^(?:https?:\/\/|mailto:|tel:|\/|#)/i.test(href)) return href
  return `/${href.replace(/^\/+/, '')}`
}

export function presentBanner(raw = {}, defaults = {}) {
  const legacy = parseLegacyBannerName(raw.name)
  const headline = text(raw.headline || raw.title || legacy.headline)
  const subheadline = text(raw.subheadline || raw.subtitle || legacy.subheadline)
  const imageUrl = normalizeBannerMediaUrl(raw.image_url || raw.path || raw.image)

  return {
    ...raw,
    eyebrow: text(raw.eyebrow || defaults.default_eyebrow),
    title: headline,
    subtitle: subheadline,
    imageUrl,
    imageAlt: text(raw.image_alt || headline || subheadline),
    ctaLabel: text(raw.cta_label || defaults.default_cta_label),
    ctaUrl: normalizeBannerLink(raw.cta_url || raw.url),
  }
}
