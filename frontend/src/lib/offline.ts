export type SyncStatus = 'queued' | 'uploading' | 'accepted' | 'rejected' | 'conflict' | 'manual_review'

export type OfflineOperation = {
  operationId: string
  deviceId: string
  branchId: string
  actorId: number
  operationType: 'checkout' | 'stock_movement' | 'customer_create' | 'collection'
  payloadVersion: number
  clientTime: string
  idempotencyKey: string
  payload: Record<string, unknown>
  status: SyncStatus
}

export type DeviceIdentity = {
  deviceId: string
  registeredAt: string
  revoked: boolean
}
