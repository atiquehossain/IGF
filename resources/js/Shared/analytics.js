export function trackAnalyticsPageView() {
  const measurementId = String(window.igfAnalyticsId || '').trim().toUpperCase();
  if (typeof window.gtag !== 'function' || !/^G-[A-Z0-9]+$/.test(measurementId)) return;

  window.gtag('config', measurementId, {
    page_location: window.location.href,
    page_path: `${window.location.pathname}${window.location.search}`,
  });
}
