import type { PageProps } from '@inertiajs/core';

export interface SuiteBranch {
  id: number;
  code: string;
  name: string;
  is_head_office: boolean;
}

export interface SuiteCompany {
  id: number;
  slug: string;
  name: string;
  branches: SuiteBranch[];
}

export interface SuiteOrganization {
  id: number;
  slug: string;
  name: string;
  companies: SuiteCompany[];
}

export interface SuiteScopeProp {
  level: 'all' | 'organization' | 'company';
  segment: string;
  organization: { slug: string; name: string } | null;
  company: { slug: string; name: string } | null;
  branches: number[];
}

export interface SuiteShortcut {
  id: number;
  name: string | null;
  label: string;
  segment: string;
  branches: number[];
  pinned: boolean;
}

export interface SuiteProp {
  scope: SuiteScopeProp;
  shortcuts: SuiteShortcut[];
  tree: SuiteOrganization[];
  can: string[];
  is_owner: boolean;
  /** Modules the tenant has (active or lapsed). */
  modules: string[];
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  locale: string;
  roles: string[];
  permissions: string[];
}

export interface SuitePageProps extends PageProps {
  locale: string;
  dir: 'rtl' | 'ltr';
  localeUrls: Record<string, string>;
  auth: { user: AuthUser | null };
  suite: SuiteProp;
}

/* Structure resources */
export interface BranchResource {
  id: number;
  company_id: number;
  code: string;
  name: string;
  name_ar: string;
  name_en: string;
  city: string | null;
  city_ar: string | null;
  city_en: string | null;
  is_head_office: boolean;
  status: string;
}

export interface CompanyResource {
  id: number;
  organization_id: number;
  slug: string;
  name: string;
  name_ar: string;
  name_en: string;
  cr_number: string | null;
  vat_number: string | null;
  unified_number: string | null;
  gosi_number: string | null;
  qiwa_id: string | null;
  street: string | null;
  building_number: string | null;
  district: string | null;
  city: string | null;
  postal_code: string | null;
  timezone: string;
  loyalty_percent: string | null;
  status: string;
  branches?: BranchResource[];
}

export interface OrganizationResource {
  id: number;
  slug: string;
  name: string;
  name_ar: string;
  name_en: string;
  status: string;
  companies?: CompanyResource[];
}

/* Access resources */
export interface AccessRoleResource {
  id: number;
  key: string | null;
  name: string;
  name_ar: string;
  name_en: string;
  is_locked: boolean;
  permissions?: string[];
  assignments_count?: number;
}

export interface MemberAssignment {
  id: number;
  role: string;
  is_owner: boolean;
  level: 'client' | 'organization' | 'company' | 'branch';
  node: string | null;
}

export interface MemberResource {
  id: number;
  name: string;
  email: string;
  pending: boolean;
  last_login_at: string | null;
  assignments: MemberAssignment[];
  [key: string]: unknown;
}

/** FeatureRegistry::matrix(): group → features with their actions and extra abilities. */
export type FeatureMatrix = Record<string, { feature: string; label_file: string; actions: string[]; abilities: string[] }[]>;
