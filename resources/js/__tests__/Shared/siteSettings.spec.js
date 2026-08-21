import { donationAmountFromUrl, formatDate, formatDateTime, formatMoney, formatNumber, formatTime, interpolateSetting } from '@/Shared/composables/siteSettings'

describe('public site-setting formatters', () => {
  test('interpolates only known placeholders', () => {
    expect(interpolateSetting('View {name} in {place}', { name: 'Amina' }))
      .toBe('View Amina in {place}')
  })

  test('keeps public donation formatting on the protected BDT default', () => {
    expect(formatMoney(1500, { number_locale: 'en-BD' })).toBe('৳1,500')
    expect(formatMoney(1500, {
      number_locale: 'en-BD',
      currency_code: 'USD',
      currency_symbol: '$',
      currency_position: 'after',
    })).toBe('৳1,500')
  })

  test('uses the configured safe date locale and timezone', () => {
    const formatted = formatDateTime('2026-08-19T00:00:00Z', {
      date_locale: 'en-BD',
      timezone: 'Asia/Dhaka',
    })
    expect(formatted).toContain('2026')
    expect(formatted).toMatch(/6:00/)

    const dateOnly = formatDate('2026-08-19T00:00:00Z', {
      date_locale: 'en-BD',
      timezone: 'Asia/Dhaka',
    })
    expect(dateOnly).toContain('2026')
    expect(dateOnly).not.toMatch(/6:00/)
  })

  test('formats date parts, times, and ordinary numbers with the configured regional choices', () => {
    const bangla = {
      number_locale: 'bn-BD',
      date_locale: 'bn-BD',
      timezone: 'Asia/Dhaka',
    }

    expect(formatNumber(23000, bangla)).toBe(new Intl.NumberFormat('bn-BD').format(23000))
    expect(formatDate('2026-08-19', bangla, { day: '2-digit' }))
      .toBe(new Intl.DateTimeFormat('bn-BD', { day: '2-digit', timeZone: 'Asia/Dhaka' }).format(new Date('2026-08-19')))
    expect(formatTime('2026-08-19T00:00:00Z', { date_locale: 'en-US', timezone: 'UTC' }))
      .toBe(new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', timeZone: 'UTC' }).format(new Date('2026-08-19T00:00:00Z')))
  })

  test('carries a campaign amount into the one-time donation form', () => {
    expect(donationAmountFromUrl('/donate?amount=1000')).toBe(1000)
    expect(donationAmountFromUrl('/donate?amount=1000&custom_amount=2500')).toBe(2500)
    expect(donationAmountFromUrl('/donate?amount=not-a-number')).toBeNull()
    expect(donationAmountFromUrl('/donate?amount=10000001')).toBeNull()
    expect(donationAmountFromUrl('/donate?amount=1000&custom_amount=10000001')).toBeNull()
  })

  test('accepts only visible suggested query amounts when custom giving is disabled', () => {
    const options = { allowCustomAmount: false, visibleSuggestedAmounts: [500, 1000] }

    expect(donationAmountFromUrl('/donate?amount=1000', options)).toBe(1000)
    expect(donationAmountFromUrl('/donate?custom_amount=750', options)).toBeNull()
    expect(donationAmountFromUrl('/donate?amount=1000&custom_amount=750', options)).toBe(1000)
    expect(donationAmountFromUrl('/donate?custom_amount=500', options)).toBe(500)
  })
})
