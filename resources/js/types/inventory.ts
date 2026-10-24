import type { Option } from '@/types/resources';

/** A stock variant as the scan box and line editors know it. */
export interface CatalogVariant {
  id: number;
  label: string;
  sku: string | null;
  barcode: string | null;
  unit: string;
  unit_cost: string;
  units: Option[];
}

export interface MovableAsset {
  id: number;
  tag: string;
  label: string;
  serial: string | null;
  status: string;
  holder: string | null;
}

/** holder ("warehouse:3" / "employee:12") → variant id → quantity on hand */
export type StockMap = Record<string, Record<number, string>>;

export interface DraftLine {
  key: string;
  variant_id?: number;
  asset_id?: number;
  label: string;
  quantity: string;
  unit: string;
  units: Option[];
  condition: string;
}

export interface DocumentLine {
  id: number;
  label: string;
  is_asset: boolean;
  asset_id: number | null;
  variant_id: number | null;
  quantity: string;
  received: string | null;
  unit: string | null;
  entered: string | null;
  condition: string | null;
  note: string | null;
}

export interface IncidentSummary {
  id: number;
  number: string;
  kind: string;
  status: string;
  label: string;
  quantity: string;
  unit: string | null;
  value: string;
  source: string;
  description: string | null;
  holder: string | null;
  responsible: string | null;
  responsible_employee_id: number | null;
  resolution: string | null;
  resolution_note: string | null;
  charged_amount: string | null;
  created_at: string;
  resolved_at: string | null;
  is_asset: boolean;
  photos: { id: number; url: string }[];
  [key: string]: unknown;
}

export interface Explanation {
  kind: string;
  quantity: string;
  description: string;
}
