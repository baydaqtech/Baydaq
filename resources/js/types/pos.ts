import type { Option } from '@/types/resources';

export interface ScopedOption extends Option {
  company_id: number;
}

export interface MenuModifier {
  id: number;
  name: string;
  price: string;
}

export interface MenuModifierGroup {
  id: number;
  name: string;
  min: number;
  max: number;
  modifiers: MenuModifier[];
}

/** A sellable product at a branch, as the till and the QR menu receive it. */
export interface MenuProduct {
  id: number;
  category_id: number | null;
  name: string;
  description: string | null;
  price: string;
  vat_rate: string;
  image: string | null;
  calories: number | null;
  high_salt: boolean;
  caffeine_mg: number | null;
  allergens: string[];
  modifier_groups: MenuModifierGroup[];
}

export interface MenuCategory {
  id: number;
  name: string;
  color: string | null;
}

export interface OrderLineResource {
  id: number;
  product_id: number | null;
  name: string;
  quantity: string;
  unit_price: string;
  total: string;
  modifiers: { name: string; price: string }[];
  note: string | null;
}

export interface OrderResource {
  id: number;
  number: number;
  channel: 'pos' | 'qr_menu';
  type: string;
  status: string;
  kitchen_status: string;
  table_no: string | null;
  customer_name: string | null;
  note: string | null;
  created_at: string;
  subtotal: string;
  discount: string;
  vat: string;
  total: string;
  invoice_id: number | null;
  lines: OrderLineResource[];
  branch?: string | null;
  [key: string]: unknown;
}

/** A line in a cart before it is sent: product, chosen modifiers and quantity. */
export interface CartLine {
  key: string;
  product: MenuProduct;
  modifiers: MenuModifier[];
  quantity: number;
  note?: string;
}

export function cartLineUnitPrice(line: CartLine): number {
  return Number(line.product.price) + line.modifiers.reduce((sum, modifier) => sum + Number(modifier.price), 0);
}

export function cartTotal(lines: CartLine[]): number {
  return Math.round(lines.reduce((sum, line) => sum + cartLineUnitPrice(line) * line.quantity, 0) * 100) / 100;
}

export function cartPayload(lines: CartLine[]) {
  return lines.map((line) => ({ product_id: line.product.id, quantity: line.quantity, modifier_ids: line.modifiers.map((modifier) => modifier.id), note: line.note ?? null }));
}
