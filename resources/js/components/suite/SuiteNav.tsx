import { Link } from '@inertiajs/react';
import { ArrowLeftRight, BarChart3, Banknote, CalendarClock, CalendarDays, ChefHat, ClipboardList, ConciergeBell, CreditCard, DoorOpen, Package, ScanLine, UserRound, Contact, Cpu, FileText, Fingerprint, Home, IdCard, MapPin, Monitor, Network, Receipt, Settings2, ShieldCheck, ShoppingBag, Tags, Users } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { useAccess, useSuite, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

/**
 * Section tabs under the header. `keys` hides a tab from users holding none of them; the
 * server still checks each route. Modules append their own tabs here as they land.
 */
const NAV = [
  { path: '/', label: 'suite.nav.home', icon: Home, exact: true },
  { path: '/structure/locations', label: 'suite.structure.locations', icon: MapPin, keys: ['suite.structure.view', 'suite.structure.create', 'suite.structure.update'] },
  { path: '/structure/brands', label: 'suite.structure.brands', icon: Tags, keys: ['suite.structure.view', 'suite.structure.create', 'suite.structure.update'] },
  { path: '/structure', label: 'suite.nav.structure', icon: Network, exact: true, keys: ['suite.structure.view', 'suite.structure.create', 'suite.structure.update', 'suite.structure.delete'] },
  { path: '/access/roles', label: 'suite.nav.roles', icon: ShieldCheck, keys: ['suite.access.view', 'suite.access.create', 'suite.access.update', 'suite.access.delete'] },
  { path: '/access/members', label: 'suite.nav.members', icon: Users, keys: ['suite.access.view', 'suite.access.create', 'suite.access.update', 'suite.access.delete'] },
  { path: '/hr/employees', label: 'suite.module_nav.hr.employees', icon: IdCard, module: 'hr', keys: ['hr.employees.view', 'hr.employees.create', 'hr.employees.update'] },
  { path: '/hr/attendance', label: 'suite.module_nav.hr.attendance', icon: Fingerprint, module: 'hr', keys: ['hr.attendance.view'] },
  { path: '/hr/movements', label: 'suite.module_nav.hr.movements', icon: ArrowLeftRight, module: 'hr', keys: ['hr.movements.view', 'hr.movements.create'] },
  { path: '/hr/leave', label: 'suite.module_nav.hr.leave', icon: CalendarDays, module: 'hr', keys: ['hr.leave.view', 'hr.leave.approve'] },
  { path: '/hr/payroll', label: 'suite.module_nav.hr.payroll', icon: Banknote, module: 'hr', keys: ['hr.payroll.view'] },
  { path: '/hr/reports/headcount', label: 'suite.module_nav.hr.reports', icon: BarChart3, module: 'hr', keys: ['hr.reports.view'] },
  { path: '/hr/catalog', label: 'suite.module_nav.hr.catalog', icon: Settings2, module: 'hr', keys: ['hr.catalog.view', 'hr.catalog.create', 'hr.catalog.update'] },
  { path: '/invoicing/invoices', label: 'suite.module_nav.invoicing.invoices', icon: FileText, module: 'invoicing', keys: ['invoicing.invoices.view', 'invoicing.invoices.create'] },
  { path: '/invoicing/customers', label: 'suite.module_nav.invoicing.customers', icon: Contact, module: 'invoicing', keys: ['invoicing.customers.view', 'invoicing.customers.create', 'invoicing.customers.update'] },
  { path: '/invoicing/units', label: 'suite.module_nav.invoicing.einvoicing', icon: Cpu, module: 'invoicing', keys: ['invoicing.einvoicing.view', 'invoicing.einvoicing.onboard'] },
  { path: '/pos/registers', label: 'suite.module_nav.pos.registers', icon: Monitor, module: 'pos', keys: ['pos.registers.view', 'pos.registers.create', 'pos.registers.open_shift', 'pos.orders.sell'] },
  { path: '/pos/orders', label: 'suite.module_nav.pos.orders', icon: Receipt, module: 'pos', keys: ['pos.orders.view', 'pos.orders.sell'] },
  { path: '/pos/kitchen', label: 'suite.module_nav.pos.kitchen', icon: ChefHat, module: 'pos', keys: ['pos.orders.kitchen'] },
  { path: '/pos/catalog', label: 'suite.module_nav.pos.catalog', icon: ShoppingBag, module: 'pos', keys: ['pos.catalog.view', 'pos.catalog.create', 'pos.catalog.update'] },
  { path: '/reservations/calendar', label: 'suite.module_nav.reservations.calendar', icon: CalendarClock, module: 'reservations', keys: ['reservations.bookings.view', 'reservations.bookings.create'] },
  { path: '/reservations/bookings', label: 'suite.module_nav.reservations.bookings', icon: ClipboardList, module: 'reservations', keys: ['reservations.bookings.view'] },
  { path: '/reservations/catalog', label: 'suite.module_nav.reservations.catalog', icon: ConciergeBell, module: 'reservations', keys: ['reservations.catalog.view', 'reservations.catalog.create', 'reservations.catalog.update'] },
  { path: '/subscriptions/members', label: 'suite.module_nav.subscriptions.members', icon: UserRound, module: 'subscriptions', keys: ['subscriptions.members.view'] },
  { path: '/subscriptions/check-in', label: 'suite.module_nav.subscriptions.check_in', icon: DoorOpen, module: 'subscriptions', keys: ['subscriptions.check_ins.view', 'subscriptions.check_ins.check_in'] },
  { path: '/subscriptions/plans', label: 'suite.module_nav.subscriptions.plans', icon: CreditCard, module: 'subscriptions', keys: ['subscriptions.plans.view', 'subscriptions.plans.create', 'subscriptions.plans.update'] },
  { path: '/assets/audits', label: 'suite.module_nav.assets.audits', icon: ScanLine, module: 'assets', keys: ['assets.audits.view'] },
  { path: '/assets', label: 'suite.module_nav.assets.register', icon: Package, module: 'assets', keys: ['assets.register.view'] },
];

export default function SuiteNav() {
  const t = useT();
  const { can } = useAccess();
  const { modules } = useSuite();
  const url = useSuiteUrl();
  const current = window.location.pathname.replace(/^\/suite\/[^/]+/, '') || '/';
  const visible = NAV.filter((item) => (!item.module || modules.includes(item.module)) && (!item.keys || can(...item.keys)));
  // The most specific matching tab wins, so /assets/audits lights "Stock-takes", not "Assets".
  const matches = (item: (typeof NAV)[number]) => (item.exact ? current === item.path : current === item.path || current.startsWith(`${item.path}/`));

  return (
    <nav className="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6">
      {visible.map((item) => {
        const active = matches(item) && !visible.some((other) => other.path.length > item.path.length && matches(other));

        return (
          <Link
            key={item.path}
            href={url(item.path)}
            className={cn(
              'flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm transition-colors',
              active ? 'border-brass text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
            )}
          >
            <item.icon className="size-4" />
            {t(item.label)}
          </Link>
        );
      })}
    </nav>
  );
}
