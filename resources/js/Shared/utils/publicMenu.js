export function safeMenuHref(value) {
  const href = String(value || '').trim();

  const hasControlCharacter = [...href].some((character) => {
    const code = character.charCodeAt(0);
    return code < 32 || code === 127;
  });
  if (!href || href.includes('\\') || /\s/u.test(href) || /%5c/i.test(href) || hasControlCharacter) return '#';
  if (/^#(?:[^\s]*)$/.test(href)) return href;
  if (/^\/(?![\\/])/.test(href)) return href;
  if (/^(https?:|mailto:|tel:)/i.test(href)) return href;

  return '#';
}

export function publicMenuHref(item) {
  if (!item || typeof item !== 'object') return '#';
  if (item.href) return safeMenuHref(item.href);
  if (item.link === 'custom') return safeMenuHref(item.slug);

  try {
    if (item.link && window.route?.().has(item.link)) {
      return safeMenuHref(window.route(item.link, item.slug ? [item.slug] : []));
    }
  } catch {
    // Fall through to the safe local CMS-page convention.
  }

  const slug = String(item.slug || '').replace(/^\/+/, '');
  return slug ? safeMenuHref(`/page/${slug}`) : '#';
}
