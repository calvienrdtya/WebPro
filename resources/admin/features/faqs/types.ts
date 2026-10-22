export type Option = { value: string; label: string }

export type FaqRow = {
  id: number
  category: string
  category_label: string
  question: string
  answer: string
  is_active: boolean
  show_on_home: boolean
  show_on_product: boolean
}

export type FaqForm = {
  category: string
  question: string
  answer: string
  is_active: boolean
  show_on_home: boolean
  show_on_product: boolean
}
