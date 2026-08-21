const COMPONENT_CONTENT_KEYS = Object.freeze({
  'Home/home': 'homePage',
  about: 'about_us',
  zakat: 'zakat',
  category: 'category',
  event: 'event',
  page: 'page',
})

export function resolvePageCss(component, data = {}) {
  const key = COMPONENT_CONTENT_KEYS[String(component || '')]
  if (!key) return ''

  if (key === 'category') {
    return [data?.category?.inline_css, data?.landing_page?.inline_css]
      .filter(css => typeof css === 'string' && css.trim() !== '')
      .join('\n')
  }

  const css = data?.[key]?.inline_css
  return typeof css === 'string' ? css : ''
}
