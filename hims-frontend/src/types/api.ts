export type ApiEnvelope<TData> = {
  success: boolean
  message: string
  data: TData
  meta: Record<string, unknown>
  errors: Record<string, unknown> | null
  request_id: string
}
