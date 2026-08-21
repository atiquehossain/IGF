import {
  normalizeBannerLink,
  normalizeBannerMediaUrl,
  parseLegacyBannerName,
  presentBanner,
} from '@/Shared/composables/bannerPresentation'

describe('banner presentation', () => {
  test('prefers explicit structured fields over the legacy combined name', () => {
    expect(presentBanner({
      name: '<b>Legacy headline</b> Legacy support',
      eyebrow: 'Our work',
      headline: 'Structured headline',
      subheadline: 'Structured support',
      image_alt: 'A teacher and students in a classroom',
      cta_label: 'See the program',
      cta_url: '/programs',
    }, { default_eyebrow: 'Default eyebrow' })).toMatchObject({
      eyebrow: 'Our work',
      title: 'Structured headline',
      subtitle: 'Structured support',
      imageAlt: 'A teacher and students in a classroom',
      ctaLabel: 'See the program',
      ctaUrl: '/programs',
    })
  })

  test('keeps the legacy b-tag heading format as a fallback only', () => {
    expect(parseLegacyBannerName('<b>Education</b> Every child can learn')).toEqual({
      headline: 'Education',
      subheadline: 'Every child can learn',
    })
  })

  test('accepts complete media paths and safely resolves old filenames', () => {
    expect(normalizeBannerMediaUrl('https://cdn.example.test/banner.webp'))
      .toBe('https://cdn.example.test/banner.webp')
    expect(normalizeBannerMediaUrl('/storage/media/banner.webp'))
      .toBe('/storage/media/banner.webp')
    expect(normalizeBannerMediaUrl('legacy banner.webp'))
      .toBe('/storage/photos/1/banner/legacy%20banner.webp')
    expect(normalizeBannerMediaUrl('javascript:alert(1)')).toBe('')
  })

  test('rejects unsafe banner calls to action', () => {
    expect(normalizeBannerLink('javascript:alert(1)')).toBe('')
    expect(normalizeBannerLink('vbscript:msgbox(1)')).toBe('')
    expect(normalizeBannerLink('donate')).toBe('/donate')
  })
})
