import { homeHeroCoverage, managedHomeBannerPresentation } from '@/Pages/Home/homePresentation'

describe('homepage hero and managed-banner precedence', () => {
  test('hides the managed banner only when builder heroes cover both devices', () => {
    const blocks = [
      { type: 'hero', show_on_desktop: true, show_on_mobile: false },
      { type: 'hero', show_on_desktop: false, show_on_mobile: true },
    ]

    expect(homeHeroCoverage(blocks)).toEqual({ desktop: true, mobile: true })
    expect(managedHomeBannerPresentation(blocks, true)).toEqual({ show: false, visibilityClass: '' })
  })

  test('keeps a managed fallback banner on the device without a builder hero', () => {
    expect(managedHomeBannerPresentation([
      { type: 'hero', show_on_desktop: true, show_on_mobile: false },
    ], true)).toEqual({ show: true, visibilityClass: 'home-managed-banner--mobile-only' })

    expect(managedHomeBannerPresentation([
      { type: 'hero', show_on_desktop: false, show_on_mobile: true },
    ], true)).toEqual({ show: true, visibilityClass: 'home-managed-banner--desktop-only' })
  })

  test('shows no fallback when there are no managed banners', () => {
    expect(managedHomeBannerPresentation([], false)).toEqual({ show: false, visibilityClass: '' })
  })
})
