export type PublicCheckoutConfig = {
  provider: string
  provider_label?: string
  active_provider?: string
  available_providers?: Array<{
    key: string
    label: string
    configured: boolean
    credit_card_enabled: boolean
    payment_methods: string[]
  }>
  payment_methods: string[]
  credit_card_enabled: boolean
  boleto_enabled?: boolean
  boleto_configured?: boolean
  public_key: string | null
  sdk_url?: string | null
  tokenization?: string | null
  token_url: string | null
  token_query_param: string | null
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

export type MercadoPagoCardFormData = {
  token: string
  paymentMethodId: string
  issuerId?: string
  installments?: string | number
}

declare global {
  interface Window {
    MercadoPago?: any
  }
}

let mercadoPagoSdkPromise: Promise<void> | null = null

export async function tokenizePagarMeCard(
  config: PublicCheckoutConfig,
  payload: PagarMeCardTokenPayload,
): Promise<PagarMeCardTokenResult> {
  if (!config.public_key || !config.token_url) {
    throw new Error('A tokenização do cartão não está disponível para este ambiente agora.')
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
      || 'Não foi possível tokenizar o cartão agora. Confirme os dados e o domínio autorizado na Pagar.me.')
  }

  const token = typeof data?.id === 'string'
    ? data.id
    : (typeof data?.token === 'string' ? data.token : '')

  if (!token) {
    throw new Error('A Pagar.me não retornou o token do cartão.')
  }

  return {
    token,
    brand: typeof data?.card?.brand === 'string' ? data.card.brand : null,
    last_four_digits: typeof data?.card?.last_four_digits === 'string'
      ? data.card.last_four_digits
      : (typeof data?.card?.last_4_digits === 'string' ? data.card.last_4_digits : null),
  }
}

export async function createMercadoPagoCardForm(
  config: PublicCheckoutConfig,
  amountCents: number,
): Promise<any> {
  if (!config.public_key) {
    throw new Error('A tokenização do cartão não está disponível para este ambiente agora.')
  }

  await loadMercadoPagoSdk(config.sdk_url || 'https://sdk.mercadopago.com/js/v2')

  if (!window.MercadoPago) {
    throw new Error('Não foi possível carregar o Mercado Pago no navegador.')
  }

  const mp = new window.MercadoPago(config.public_key, { locale: 'pt-BR' })

  return mp.cardForm({
    amount: String((amountCents / 100).toFixed(2)),
    iframe: true,
    form: {
      id: 'checkout-form',
      cardNumber: { id: 'mp-card-number', placeholder: '0000 0000 0000 0000' },
      expirationDate: { id: 'mp-expiration-date', placeholder: 'MM/AA' },
      securityCode: { id: 'mp-security-code', placeholder: '123' },
      cardholderName: { id: 'mp-cardholder-name' },
      issuer: { id: 'mp-issuer' },
      installments: { id: 'mp-installments' },
      identificationType: { id: 'mp-identification-type' },
      identificationNumber: { id: 'mp-identification-number' },
    },
    callbacks: {
      onFormMounted: (error: unknown) => {
        if (error) {
          console.warn('Mercado Pago card form error', error)
        }
      },
      onSubmit: (event: Event) => {
        event.preventDefault()
      },
    },
  })
}

function loadMercadoPagoSdk(src: string): Promise<void> {
  if (window.MercadoPago) {
    return Promise.resolve()
  }

  if (mercadoPagoSdkPromise) {
    return mercadoPagoSdkPromise
  }

  mercadoPagoSdkPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector<HTMLScriptElement>(`script[src="${src}"]`)
    if (existing) {
      existing.addEventListener('load', () => resolve(), { once: true })
      existing.addEventListener('error', () => reject(new Error('Falha ao carregar o SDK do Mercado Pago.')), { once: true })
      return
    }

    const script = document.createElement('script')
    script.src = src
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Falha ao carregar o SDK do Mercado Pago.'))
    document.head.appendChild(script)
  })

  return mercadoPagoSdkPromise
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
