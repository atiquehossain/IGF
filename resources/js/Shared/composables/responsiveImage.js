const LIVE_MEDIA = '/storage/media/ignite-live/';
const imagePresentationCache = new Map();
const backgroundPresentationCache = new Map();
const MAX_CACHE_ENTRIES = 250;

function variant(width, height, basename, widths, avifWidths = []) {
  return Object.freeze({ width, height, basename, widths, avifWidths });
}

const RESPONSIVE_PUBLIC_IMAGES = Object.freeze({
  '/image/logo-footer.png': variant(81, 73, '/image/logo-footer', []),
  '/image/banner/slider-1.png': variant(1588, 688, '/image/banner/slider-1', [640, 1024, 1588], [640, 1024, 1588]),
  '/image/banner/slider-1-1588.webp': variant(1588, 688, '/image/banner/slider-1', [640, 1024, 1588], [640, 1024, 1588]),
  '/image/banner/slider-2.png': variant(1588, 688, '/image/banner/slider-2', [640, 1024, 1588], [640, 1024, 1588]),
  '/image/banner/slider-2-1588.webp': variant(1588, 688, '/image/banner/slider-2', [640, 1024, 1588], [640, 1024, 1588]),
  [`${LIVE_MEDIA}rsz-edited-size-629-e2949cd0a7-404-px-embark-e11-40844d8249fb.jpg`]: variant(629, 404, `${LIVE_MEDIA}rsz-edited-size-629-e2949cd0a7-404-px-embark-e11-40844d8249fb-perf`, [629]),
  [`${LIVE_MEDIA}dms2sp0pfxgane9lzjpco3enlkyd4xjeygndfbym-24b5036254cd.jpg`]: variant(1590, 690, `${LIVE_MEDIA}dms2sp0pfxgane9lzjpco3enlkyd4xjeygndfbym-24b5036254cd-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}9me3alpg8medhhf0jbids6pkbcuva3wqauewpza9-4ec17f01da4a.webp`]: variant(1590, 690, `${LIVE_MEDIA}9me3alpg8medhhf0jbids6pkbcuva3wqauewpza9-4ec17f01da4a-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}welcome-bg-f7b67abb8b86.webp`]: variant(1590, 690, `${LIVE_MEDIA}welcome-bg-f7b67abb8b86-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}e4nfvn5wvxl5vi5fj4p40uwcia7yl3qu11hdzn2t-e37f5490f23c.jpg`]: variant(1590, 690, `${LIVE_MEDIA}e4nfvn5wvxl5vi5fj4p40uwcia7yl3qu11hdzn2t-e37f5490f23c-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}rsz-volunteer-orientation-5ed54757bfa9.jpg`]: variant(410, 240, `${LIVE_MEDIA}rsz-volunteer-orientation-5ed54757bfa9-perf`, [410]),
  [`${LIVE_MEDIA}53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg`]: variant(1590, 690, `${LIVE_MEDIA}53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}ywuogg10l98ly4qdoa3ujrb2bzr9218wp43dqcka-0d6907c3637e.webp`]: variant(1590, 690, `${LIVE_MEDIA}ywuogg10l98ly4qdoa3ujrb2bzr9218wp43dqcka-0d6907c3637e-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}rsz-edited-size-630-e2949cd0a7-398-px-e13-1-f7f7e3a09ec6.jpg`]: variant(410, 240, `${LIVE_MEDIA}rsz-edited-size-630-e2949cd0a7-398-px-e13-1-f7f7e3a09ec6-perf`, [410]),
  [`${LIVE_MEDIA}rsz-together-for-their-tomorrow-01a8ed105cdf.jpg`]: variant(410, 240, `${LIVE_MEDIA}rsz-together-for-their-tomorrow-01a8ed105cdf-perf`, [410]),
  [`${LIVE_MEDIA}p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg`]: variant(1590, 690, `${LIVE_MEDIA}p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}xzz5dafv1xrvdkoj2xlr9lzuprmkljgzw2dpoo1y-f7a315ee992f.webp`]: variant(1590, 690, `${LIVE_MEDIA}xzz5dafv1xrvdkoj2xlr9lzuprmkljgzw2dpoo1y-f7a315ee992f-perf`, [640, 1024, 1590]),
  [`${LIVE_MEDIA}rsz-kiddovation-516bb86d7edd.jpg`]: variant(410, 240, `${LIVE_MEDIA}rsz-kiddovation-516bb86d7edd-perf`, [410]),
  [`${LIVE_MEDIA}oj97m6tfjvumxrbtgdokbtqxrnumxcioewcjpbnj-e949aa78b452.webp`]: variant(1590, 690, `${LIVE_MEDIA}oj97m6tfjvumxrbtgdokbtqxrnumxcioewcjpbnj-e949aa78b452-perf`, [640, 1024, 1590]),
});

function normalizedPath(value) {
  const source = String(value || '').trim();
  if (!source) return '';

  try {
    return new URL(source, 'https://igf.invalid').pathname;
  } catch {
    return source.split(/[?#]/u, 1)[0];
  }
}

function srcset(basename, extension, widths) {
  return widths
    .map(width => `${basename}-${width}.${extension} ${width}w`)
    .join(', ');
}

export function responsiveImagePresentation(value, sizes = '100vw') {
  const src = String(value || '').trim();
  const cacheKey = `${src}\u0000${sizes}`;
  if (imagePresentationCache.has(cacheKey)) return imagePresentationCache.get(cacheKey);
  const variant = RESPONSIVE_PUBLIC_IMAGES[normalizedPath(src)];

  if (!variant) {
    return remember(imagePresentationCache, cacheKey, {
      src,
      width: undefined,
      height: undefined,
      sizes,
      avifSrcset: '',
      webpSrcset: '',
    });
  }

  const webpSrcset = srcset(variant.basename, 'webp', variant.widths);
  return remember(imagePresentationCache, cacheKey, {
    src: variant.widths.length
      ? `${variant.basename}-${variant.widths.at(-1)}.webp`
      : src,
    width: variant.width,
    height: variant.height,
    sizes,
    avifSrcset: srcset(variant.basename, 'avif', variant.avifWidths),
    webpSrcset,
  });
}

function remember(cache, key, value) {
  if (cache.size >= MAX_CACHE_ENTRIES) cache.delete(cache.keys().next().value);
  cache.set(key, value);
  return value;
}

function candidateUrls(value) {
  return String(value || '')
    .split(', ')
    .filter(Boolean)
    .map(candidate => candidate.split(' ')[0]);
}

function imageSet(source, avif, webp) {
  const candidates = [];
  if (avif) candidates.push(`url("${avif}") type("image/avif")`);
  if (webp) candidates.push(`url("${webp}") type("image/webp")`);
  candidates.push(`url("${source}")`);
  return `image-set(${candidates.join(', ')})`;
}

export function responsiveBackgroundPresentation(value) {
  const cacheKey = String(value || '').trim();
  if (backgroundPresentationCache.has(cacheKey)) return backgroundPresentationCache.get(cacheKey);
  const media = responsiveImagePresentation(value);
  if (!media.src) return {};

  const escapedSource = media.src.replaceAll('"', '\\"');
  const style = {
    '--igf-hero-image': `url("${escapedSource}")`,
  };

  const avif = candidateUrls(media.avifSrcset);
  const webp = candidateUrls(media.webpSrcset);
  const candidateCount = Math.max(avif.length, webp.length);
  if (candidateCount) {
    const candidate = index => imageSet(
      escapedSource,
      avif[Math.min(index, avif.length - 1)],
      webp[Math.min(index, webp.length - 1)],
    );
    style['--igf-hero-image-set-small'] = candidate(0);
    style['--igf-hero-image-set-medium'] = candidate(Math.min(1, candidateCount - 1));
    style['--igf-hero-image-set-large'] = candidate(candidateCount - 1);
  }

  return remember(backgroundPresentationCache, cacheKey, style);
}
