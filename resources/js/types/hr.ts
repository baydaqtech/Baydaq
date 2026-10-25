import type { Option } from '@/types/resources';

export interface EmployeeCan {
  view_salaries?: boolean;
  view_iban?: boolean;
  view_national_id?: boolean;
  update?: boolean;
  link_login?: boolean;
  view_documents?: boolean;
  view_movements?: boolean;
  manage_contracts?: boolean;
  manage_documents?: boolean;
  manage_movements?: boolean;
  reset_device?: boolean;
  view_pay?: boolean;
  manage_pay?: boolean;
  approve_pay?: boolean;
  view_leave?: boolean;
  manage_leave?: boolean;
  approve_leave?: boolean;
  view_eosb?: boolean;
}

export interface EmployeeResource {
  id: number;
  company_id: number;
  branch_id: number;
  employee_number: string;
  name: string;
  name_ar: string;
  name_en: string;
  email: string | null;
  phone: string | null;
  section_id: number | null;
  section?: string | null;
  job_id: number | null;
  job?: string | null;
  manager_id: number | null;
  manager?: string | null;
  id_type: string | null;
  national_id: string | null;
  nationality: string | null;
  gender: string | null;
  birth_date: string | null;
  hired_on: string | null;
  gosi_registered_on: string | null;
  terminated_on: string | null;
  status: string;
  iban: string | null;
  notes: string | null;
  user_id: number | null;
  can: EmployeeCan;
  [key: string]: unknown;
}

export interface ContractAllowanceLine {
  id?: number;
  allowance_type_id: number | '';
  name?: string | null;
  calculation: 'fixed' | 'percent_of_basic';
  value: string;
  amount?: string;
}

export interface ContractResource {
  qiwa_authenticated?: boolean;
  id: number;
  type: string;
  status: string;
  starts_on: string;
  ends_on: string | null;
  probation_days: number;
  basic_salary: string;
  total: string | null;
  notes: string | null;
  allowances: ContractAllowanceLine[];
}

export interface DocumentFile {
  id: number;
  name: string;
  size: number;
  mime: string;
}

export interface EmployeeDocumentResource {
  id: number;
  document_type_id: number;
  type?: string;
  number: string | null;
  issued_on: string | null;
  expires_on: string | null;
  days_left: number | null;
  data: Record<string, unknown>;
  notes: string | null;
  files: DocumentFile[];
}

export interface MovementResource {
  id: number;
  employee_id: number;
  employee?: string;
  type: 'transfer' | 'secondment';
  status: string;
  from_branch: string;
  to_branch: string;
  starts_on: string;
  ends_on: string | null;
  reason: string | null;
  cancellable: boolean;
  [key: string]: unknown;
}

export interface DocumentField {
  key: string;
  label_ar: string;
  label_en: string;
  type: 'text' | 'number' | 'date' | 'select' | 'boolean';
  required: boolean;
  options?: string[];
}

export interface CatalogItem {
  id: number;
  name: string;
  name_ar: string;
  name_en: string;
  is_active: boolean;
  sort_order: number;
  section_ids?: number[];
  sections?: string[];
  blocked_company_ids?: number[];
  default_role_id?: number | null;
  calculation?: 'fixed' | 'percent_of_basic';
  default_value?: string;
  counts_for_gosi?: boolean;
  has_number?: boolean;
  has_expiry?: boolean;
  alert_days?: number[] | null;
  fields?: DocumentField[] | null;
  [key: string]: unknown;
}

export interface BranchOption extends Option {
  value: number;
  company_id: number;
}

export interface AuditEntry {
  id: number;
  event: string;
  changes: Record<string, unknown> | null;
  actor: string | null;
  at: string;
}
