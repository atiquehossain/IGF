import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import { beforeEach, describe, expect, test, vi } from 'vitest';
import AnalyticsConsent from '@/Shared/AnalyticsConsent.vue';
import { trackAnalyticsPageView } from '@/Shared/analytics';

const analyticsSettings = {
  google_analytics_id: 'G-TEST12345',
  consent_title: 'Choose privacy',
  consent_message: 'Optional analytics help us improve.',
  accept_label: 'Allow analytics',
  decline_label: 'No thanks',
  privacy_label: 'Privacy policy',
  privacy_url: '/page/privacy-policy',
  settings_label: 'Privacy settings',
};

describe('privacy-safe analytics consent', () => {
  beforeEach(() => {
    document.cookie = 'igf_analytics_consent=; Path=/; Max-Age=0';
    document.getElementById('igf-google-analytics')?.remove();
    delete window.dataLayer;
    delete window.gtag;
    delete window.igfAnalyticsId;
    delete window['ga-disable-G-TEST12345'];
    usePage().props = { siteSettings: { analytics: { ...analyticsSettings } } };
  });

  test('does not load analytics before the visitor makes a choice', async () => {
    const wrapper = mount(AnalyticsConsent);
    await vi.waitFor(() => expect(wrapper.get('[role="dialog"]').isVisible()).toBe(true));

    expect(document.getElementById('igf-google-analytics')).toBeNull();
    expect(wrapper.text()).toContain('Optional analytics help us improve.');
    expect(wrapper.get('a').attributes('href')).toBe('/page/privacy-policy');
  });

  test('loads GA only after acceptance and keeps a way to reopen preferences', async () => {
    const wrapper = mount(AnalyticsConsent);
    await vi.waitFor(() => expect(wrapper.find('[role="dialog"]').exists()).toBe(true));
    await wrapper.get('.igf-consent__accept').trigger('click');

    const script = document.getElementById('igf-google-analytics');
    expect(script?.getAttribute('src')).toBe('https://www.googletagmanager.com/gtag/js?id=G-TEST12345');
    expect(document.cookie).toContain('igf_analytics_consent=accepted');
    expect(window.igfAnalyticsId).toBe('G-TEST12345');
    expect(wrapper.get('.igf-consent-settings').text()).toContain('Privacy settings');

    const callsBeforeNavigation = window.dataLayer.length;
    window.history.replaceState({}, '', '/after-consent?page=2');
    trackAnalyticsPageView();
    expect(window.dataLayer).toHaveLength(callsBeforeNavigation + 1);
    expect(window.dataLayer.at(-1)[0]).toBe('config');
    expect(window.dataLayer.at(-1)[1]).toBe('G-TEST12345');
    expect(window.dataLayer.at(-1)[2]).toMatchObject({ page_path: '/after-consent?page=2' });

    await wrapper.get('.igf-consent-settings').trigger('click');
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
  });

  test('records a decline without loading analytics', async () => {
    const wrapper = mount(AnalyticsConsent);
    await vi.waitFor(() => expect(wrapper.find('[role="dialog"]').exists()).toBe(true));
    await wrapper.get('.igf-consent__decline').trigger('click');

    expect(document.getElementById('igf-google-analytics')).toBeNull();
    expect(document.cookie).toContain('igf_analytics_consent=declined');
    expect(window['ga-disable-G-TEST12345']).toBe(true);
    expect(window.igfAnalyticsId).toBeUndefined();
  });

  test('renders nothing when no valid GA4 ID is configured', async () => {
    usePage().props.siteSettings.analytics.google_analytics_id = 'UA-OLD-ID';
    const wrapper = mount(AnalyticsConsent);
    await Promise.resolve();

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    expect(wrapper.find('.igf-consent-settings').exists()).toBe(false);
  });
});
