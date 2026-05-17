function pad(value) {
  return String(value).padStart(2, '0')
}

export function parseDateTime(value) {
  if (!value) return null

  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }

  const normalized = String(value).trim()
  if (!normalized) return null

  const directDate = new Date(normalized)
  if (!Number.isNaN(directDate.getTime())) {
    return directDate
  }

  const fullMatch = normalized.match(
    /^(\d{4})-(\d{2})-(\d{2})(?:[ T])(\d{2}):(\d{2})(?::(\d{2}))?$/
  )

  if (fullMatch) {
    const [, year, month, day, hour, minute, second = '00'] = fullMatch
    const date = new Date(
      Number(year),
      Number(month) - 1,
      Number(day),
      Number(hour),
      Number(minute),
      Number(second)
    )

    return Number.isNaN(date.getTime()) ? null : date
  }

  const dateOnlyMatch = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (dateOnlyMatch) {
    const [, year, month, day] = dateOnlyMatch
    const date = new Date(Number(year), Number(month) - 1, Number(day))
    return Number.isNaN(date.getTime()) ? null : date
  }

  const slashDate = new Date(normalized.replace(/-/g, '/'))
  return Number.isNaN(slashDate.getTime()) ? null : slashDate
}

export function formatDateTime(value, { fallback = '-', withSeconds = true } = {}) {
  const date = parseDateTime(value)
  if (!date) return fallback

  const dateText = [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
  ].join('-')

  const timeParts = [
    pad(date.getHours()),
    pad(date.getMinutes()),
  ]

  if (withSeconds) {
    timeParts.push(pad(date.getSeconds()))
  }

  return `${dateText} ${timeParts.join(':')}`
}
