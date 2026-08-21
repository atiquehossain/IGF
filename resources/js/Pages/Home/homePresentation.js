export function homeHeroCoverage(blocks = []) {
  const heroes = Array.isArray(blocks)
    ? blocks.filter(block => block?.type === 'hero')
    : []

  return {
    desktop: heroes.some(block => Boolean(block?.show_on_desktop)),
    mobile: heroes.some(block => Boolean(block?.show_on_mobile)),
  }
}

export function managedHomeBannerPresentation(blocks = [], hasSliders = false) {
  if (!hasSliders) return { show: false, visibilityClass: '' }

  const coverage = homeHeroCoverage(blocks)
  if (coverage.desktop && coverage.mobile) return { show: false, visibilityClass: '' }
  if (coverage.desktop) return { show: true, visibilityClass: 'home-managed-banner--mobile-only' }
  if (coverage.mobile) return { show: true, visibilityClass: 'home-managed-banner--desktop-only' }

  return { show: true, visibilityClass: '' }
}
