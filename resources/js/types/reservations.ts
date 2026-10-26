export interface BookingResource {
  id: number;
  branch_id: number;
  reference: string;
  status: string;
  channel: 'staff' | 'online';
  starts_at: string;
  date: string;
  start: string;
  end: string;
  service: string | null;
  color: string | null;
  resource_id: number | null;
  resource: string | null;
  employee_id: number | null;
  employee: string | null;
  customer_name: string;
  customer_phone: string;
  party_size: number;
  note: string | null;
  price: string;
  deposit: string;
  deposit_paid: boolean;
  invoice_id: number | null;
  next: string[];
  branch?: string;
  [key: string]: unknown;
}

export interface Slot {
  starts_at: string;
  time: string;
  resource_ids?: number[];
  employee_ids?: number[];
}
