const HEX_COLOR = /^#([\da-f]{3}|[\da-f]{6})$/i;

function expandHex(value) {
  const match = String(value ?? '').trim().match(HEX_COLOR);
  if (!match) return null;

  const digits = match[1].length === 3
    ? match[1].split('').map((digit) => `${digit}${digit}`).join('')
    : match[1];

  return [0, 2, 4].map((offset) => Number.parseInt(digits.slice(offset, offset + 2), 16) / 255);
}

function relativeLuminance(value) {
  const channels = expandHex(value);
  if (!channels) return null;

  return channels
    .map((channel) => (channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4))
    .reduce((luminance, channel, index) => luminance + channel * [0.2126, 0.7152, 0.0722][index], 0);
}

export function contrastForeground(background) {
  const luminance = relativeLuminance(background);
  if (luminance === null) return '#000000';

  const contrastWithBlack = (luminance + 0.05) / 0.05;
  const contrastWithWhite = 1.05 / (luminance + 0.05);

  return contrastWithBlack >= contrastWithWhite ? '#000000' : '#ffffff';
}

export function managedThemeTokens(theme = {}) {
  const primary = theme.primary_color || '#ff7500';
  const accent = theme.accent_color || '#9c4500';
  const ink = theme.ink_color || '#191c1d';
  const surface = theme.surface_color || '#f8f9fa';

  return {
    '--igf-primary': primary,
    '--igf-accent': accent,
    '--igf-ink': ink,
    '--igf-surface': surface,
    '--igf-on-primary': contrastForeground(primary),
    '--igf-on-accent': contrastForeground(accent),
  };
}
