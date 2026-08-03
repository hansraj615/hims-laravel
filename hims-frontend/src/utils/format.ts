const currencyFormatter = new Intl.NumberFormat('en-IN', {
  style: 'currency',
  currency: 'INR',
  maximumFractionDigits: 2,
})

export function formatCurrency(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  const numeric = typeof value === 'string' ? Number(value) : value
  if (Number.isNaN(numeric)) {
    return '-'
  }

  return currencyFormatter.format(numeric)
}

export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '-'
  }

  const isoDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value)
  const date = isoDateOnly ? new Date(`${value}T00:00:00`) : new Date(value)

  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()

  return `${day}-${month}-${year}`
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  const time = date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })

  return `${formatDate(value)} ${time}`
}

export function todayIso(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}
