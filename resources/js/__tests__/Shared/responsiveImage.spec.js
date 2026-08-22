import { describe, expect, test } from 'vitest';
import {
  responsiveBackgroundPresentation,
  responsiveImagePresentation,
} from '../../Shared/composables/responsiveImage';

describe('responsive public image presentation', () => {
  test('provides AVIF and WebP width candidates for bundled banner images', () => {
    const media = responsiveImagePresentation('/image/banner/slider-1.png');

    expect(media).toMatchObject({ width: 1588, height: 688, sizes: '100vw' });
    expect(media.avifSrcset).toContain('/image/banner/slider-1-640.avif 640w');
    expect(media.avifSrcset).toContain('/image/banner/slider-1-1588.avif 1588w');
    expect(media.webpSrcset).toContain('/image/banner/slider-1-1024.webp 1024w');
  });

  test('matches known media when the public URL is absolute and cache-busted', () => {
    const media = responsiveImagePresentation(
      'https://www.example.org/storage/media/ignite-live/welcome-bg-f7b67abb8b86.webp?v=7',
      '(max-width: 900px) 100vw, 45vw',
    );

    expect(media).toMatchObject({ width: 1590, height: 690 });
    expect(media.webpSrcset).toContain('welcome-bg-f7b67abb8b86-perf-640.webp 640w');
    expect(media.webpSrcset).toContain('welcome-bg-f7b67abb8b86-perf-1590.webp 1590w');
    expect(media.sizes).toBe('(max-width: 900px) 100vw, 45vw');
  });

  test('does not invent variant URLs for unknown or administrator-supplied images', () => {
    expect(responsiveImagePresentation('/storage/media/2026/08/custom.jpg')).toEqual({
      src: '/storage/media/2026/08/custom.jpg',
      width: undefined,
      height: undefined,
      sizes: '100vw',
      avifSrcset: '',
      webpSrcset: '',
    });
  });

  test('keeps an original CSS fallback and supplies viewport-specific image sets', () => {
    const style = responsiveBackgroundPresentation('/image/banner/slider-2.png');

    expect(style['--igf-hero-image']).toBe('url("/image/banner/slider-2-1588.webp")');
    expect(style['--igf-hero-image-set-small']).toContain('slider-2-640.avif');
    expect(style['--igf-hero-image-set-medium']).toContain('slider-2-1024.webp');
    expect(style['--igf-hero-image-set-large']).toContain('slider-2-1588.avif');
    expect(style['--igf-hero-image-set-large']).toContain('url("/image/banner/slider-2-1588.webp")');
  });

  test('provides intrinsic logo dimensions without fabricating a srcset', () => {
    expect(responsiveImagePresentation('/image/logo-footer.png')).toMatchObject({
      width: 81,
      height: 73,
      avifSrcset: '',
      webpSrcset: '',
    });
    expect(responsiveBackgroundPresentation('')).toEqual({});
  });
});
