import { describe, expect, it } from 'vitest';
import { contrastForeground, managedThemeTokens } from '@/Shared/utils/themeColors';

function contrastRatio(first, second) {
  const luminance = (value) => {
    const shorthand = value.length === 4;
    const digits = shorthand
      ? value.slice(1).split('').map(channel => channel.repeat(2)).join('')
      : value.slice(1);
    const channels = digits.match(/[\da-f]{2}/gi).map(channel => Number.parseInt(channel, 16) / 255);
    const [red, green, blue] = channels.map(channel => (
      channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
    ));

    return (0.2126 * red) + (0.7152 * green) + (0.0722 * blue);
  };
  const firstLuminance = luminance(first);
  const secondLuminance = luminance(second);

  return (Math.max(firstLuminance, secondLuminance) + 0.05)
    / (Math.min(firstLuminance, secondLuminance) + 0.05);
}

describe('managed theme contrast tokens', () => {
  it('chooses the higher-contrast black or white foreground', () => {
    expect(contrastForeground('#ff7500')).toBe('#000000');
    expect(contrastForeground('#9c4500')).toBe('#ffffff');
    expect(contrastForeground('#fff')).toBe('#000000');
    expect(contrastForeground('#000')).toBe('#ffffff');
  });

  it('publishes managed colors and paired foreground tokens', () => {
    expect(managedThemeTokens({ primary_color: '#ffffff', accent_color: '#000000' })).toMatchObject({
      '--igf-primary': '#ffffff',
      '--igf-accent': '#000000',
      '--igf-on-primary': '#000000',
      '--igf-on-accent': '#ffffff',
    });
  });

  it('fails safely to black when an unexpected client-side value is received', () => {
    expect(contrastForeground('not-a-color')).toBe('#000000');
  });

  it.each(['#ff7500', '#9c4500', '#777', '#ffffff', '#000000', '#00ffcc'])(
    'keeps the computed foreground for %s at WCAG AA contrast',
    (background) => {
      expect(contrastRatio(contrastForeground(background), background)).toBeGreaterThanOrEqual(4.5);
    },
  );
});
