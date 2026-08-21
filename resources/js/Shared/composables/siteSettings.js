const FALLBACK_REGIONAL = Object.freeze({
  currency_code: 'BDT',
  currency_symbol: '৳',
  currency_position: 'before',
  number_locale: 'en-BD',
  date_locale: 'en-BD',
  timezone: 'Asia/Dhaka',
})

export function regionalSettings(value = {}) {
  return {
    ...FALLBACK_REGIONAL,
    ...(value || {}),
    currency_code: FALLBACK_REGIONAL.currency_code,
    currency_symbol: FALLBACK_REGIONAL.currency_symbol,
    currency_position: FALLBACK_REGIONAL.currency_position,
  }
}

export function interpolateSetting(template, replacements = {}) {
  return String(template || '').replace(/\{([a-z0-9_]+)\}/gi, (match, key) => {
    return Object.prototype.hasOwnProperty.call(replacements, key)
      ? String(replacements[key] ?? '')
      : match
  })
}

export function donationAmountFromUrl(value, options = {}) {
  try {
    const url = new URL(String(value || '/'), 'https://local.invalid')
    const customAmount = Number(url.searchParams.get('custom_amount'))
    const suggestedAmount = Number(url.searchParams.get('amount'))
    const isSupported = amount => Number.isFinite(amount) && amount > 0 && amount <= 10000000

    if (options.allowCustomAmount === false) {
      const visibleAmounts = new Set((options.visibleSuggestedAmounts || [])
        .map(Number)
        .filter(isSupported))
      const matched = [customAmount, suggestedAmount]
        .find(amount => isSupported(amount) && visibleAmounts.has(amount))

      return matched ?? null
    }

    const amount = customAmount > 0 ? customAmount : suggestedAmount
    return isSupported(amount) ? amount : null
  } catch {
    return null
  }
}

export function formatNumber(value, regional = {}, options = {}) {
  const settings = regionalSettings(regional)
  const amount = Number(value || 0)
  try {
    return new Intl.NumberFormat(settings.number_locale, options).format(Number.isFinite(amount) ? amount : 0)
  } catch {
    return new Intl.NumberFormat(FALLBACK_REGIONAL.number_locale, options).format(Number.isFinite(amount) ? amount : 0)
  }
}

export function formatMoney(value, regional = {}, options = {}) {
  const settings = regionalSettings(regional)
  const currencyCode = String(options.currencyCode || settings.currency_code || 'BDT').toUpperCase()
  const configuredCode = String(settings.currency_code || 'BDT').toUpperCase()
  const marker = currencyCode === configuredCode
    ? (String(settings.currency_symbol || '').trim() || currencyCode)
    : currencyCode
  const number = formatNumber(value, settings, {
    minimumFractionDigits: options.minimumFractionDigits ?? 0,
    maximumFractionDigits: options.maximumFractionDigits ?? options.minimumFractionDigits ?? 0,
  })

  return settings.currency_position === 'after' ? `${number} ${marker}` : `${marker}${number}`
}

export function formatDateTime(value, regional = {}) {
  if (!value) return ''
  const settings = regionalSettings(regional)
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const options = { dateStyle: 'long', timeStyle: 'short', timeZone: settings.timezone }
  try {
    return new Intl.DateTimeFormat(settings.date_locale, options).format(date)
  } catch {
    return new Intl.DateTimeFormat(FALLBACK_REGIONAL.date_locale, {
      dateStyle: 'long',
      timeStyle: 'short',
      timeZone: FALLBACK_REGIONAL.timezone,
    }).format(date)
  }
}

export function formatDate(value, regional = {}, options = {}) {
  if (!value) return ''
  const settings = regionalSettings(regional)
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const dateOptions = {
    ...(Object.keys(options).length ? options : { dateStyle: 'long' }),
    timeZone: settings.timezone,
  }
  try {
    return new Intl.DateTimeFormat(settings.date_locale, dateOptions).format(date)
  } catch {
    return new Intl.DateTimeFormat(FALLBACK_REGIONAL.date_locale, {
      ...(Object.keys(options).length ? options : { dateStyle: 'long' }),
      timeZone: FALLBACK_REGIONAL.timezone,
    }).format(date)
  }
}

export function formatTime(value, regional = {}) {
  if (!value) return ''
  const settings = regionalSettings(regional)
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const options = { hour: 'numeric', minute: '2-digit', timeZone: settings.timezone }
  try {
    return new Intl.DateTimeFormat(settings.date_locale, options).format(date)
  } catch {
    return new Intl.DateTimeFormat(FALLBACK_REGIONAL.date_locale, {
      ...options,
      timeZone: FALLBACK_REGIONAL.timezone,
    }).format(date)
  }
}
