export type ServiceFeeRow = {
  id: number
  name: string
  amount: number
  is_active: boolean
}

export type ServiceFeeForm = {
  name: string
  amount: number | string
  is_active: boolean
}
