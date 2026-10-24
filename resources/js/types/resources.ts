/** A Laravel paginated resource collection as Inertia receives it. */
export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; last_page: number; from: number | null; to: number | null; total: number; per_page: number };
  links: { first: string | null; last: string | null; prev: string | null; next: string | null };
}

export interface Option {
  value: string | number;
  label: string;
}
