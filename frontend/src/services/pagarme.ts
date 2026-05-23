export type PublicCheckoutConfig = {
  provider: string
  payment_methods: string[]
  credit_card_enabled: boolean
  public_key: string | null
  token_url: string
  token_query_param: string
  token_expires_in_seconds: number
}

export type PagarMeCardTokenPayload = {
  holder_name: string
  number: string
  exp_month: string
  exp_year: string
  cvv: string
}

export type PagarMeCardTokenResult = {
  token: string
  brand: string | null
  last_four_digits: string | null
}

export async function tokenizePagarMeCard(
  config: PublicCheckoutConfig,
  payload: PagarMeCardTokenPayload,
): Promise<PagarMeCardTokenResult> {
  if (!config.public_key) {
    throw new Error('A tokenizacao do cartao nao esta disponivel para este ambiente agora.')
  }

  const tokenUrl = new URL(config.token_url)
  tokenUrl.searchParams.set(config.token_query_param || 'appId', config.public_key)

  const response = await fetch(tokenUrl.toString(), {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      type: 'card',
      card: {
        holder_name: payload.holder_name.trim(),
        number: payload.number.replace(/\D+/g, ''),
        exp_month: payload.exp_month.replace(/\D+/g, ''),
        exp_year: normalizeCardYear(payload.exp_year),
        cvv: payload.cvv.replace(/\D+/g, ''),
      },
    }),
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(resolvePagarMeErrorMessage(data)
      || 'Nao foi possivel tokenizar o cartao agora. Confirme os dados e o dominio autorizado na Pagar.me.')
  }

  const token = typeof data?.id === 'string'
    ? data.id
    : (typeof data?.token === 'string' ? data.token : '')

  if (!token) {
    throw new Error('A Pagar.me nao retornou o token do cartao.')
  }

  return {
    token,
    brand: typeof data?.card?.brand === 'string' ? data.card.brand : null,
    last_four_digits: typeof data?.card?.last_four_digits === 'string'
      ? data.card.last_four_digits
      : (typeof data?.card?.last_4_digits === 'string' ? data.card.last_4_digits : null),
  }
}

function normalizeCardYear(value: string) {
  const digits = value.replace(/\D+/g, '')
  return digits.length === 2 ? `20${digits}` : digits
}

function resolvePagarMeErrorMessage(data: any) {
  if (!data || typeof data !== 'object') {
    return ''
  }

  if (typeof data.message === 'string' && data.message.trim() !== '') {
    return data.message.trim()
  }

  if (typeof data.title === 'string' && data.title.trim() !== '') {
    return data.title.trim()
  }

  return flattenErrorValues(data.errors ?? data.details ?? [])[0] || ''
}

function flattenErrorValues(value: unknown): string[] {
  if (typeof value === 'string') {
    return value.trim() ? [value.trim()] : []
  }

  if (Array.isArray(value)) {
    return value.flatMap(flattenErrorValues)
  }

  if (value && typeof value === 'object') {
    return Object.values(value).flatMap(flattenErrorValues)
  }

  return []
}
