export type UserRow = {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  methods: ('google' | 'email')[]
  is_blocked: boolean
  created_at: string
  paid_orders: number
  total_spent: number
}

export type UserForm = {
  name: string
  email: string
  phone: string
}
