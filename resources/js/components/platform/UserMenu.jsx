import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Languages, LogOut, Monitor, Moon, Sun } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale, useT } from '@/lib/i18n';
import { useColorMode } from '@/lib/theme';

function initials(name) {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('');
}

/** Avatar menu: who is signed in, the language switch, and sign out. */
export default function UserMenu() {
  const t = useT();
  const { locale, dir, localeUrls } = useLocale();
  const { user } = usePage().props.auth;
  const other = locale === 'ar' ? 'en' : 'ar';
  const { suite, theme } = usePage().props;
  const [mode, setMode] = useColorMode((suite?.theme ?? theme)?.mode ?? 'light');

  return (
    <DropdownMenu dir={dir}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-9 gap-2 px-1.5">
          <Avatar className="size-7">
            <AvatarFallback className="bg-brass/20 text-xs text-walnut">{initials(user.name)}</AvatarFallback>
          </Avatar>
          <span className="hidden max-w-40 truncate sm:inline">{user.name}</span>
          <ChevronDown className="size-4 opacity-60" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56" dir={dir}>
        <DropdownMenuLabel className="font-normal">
          <div className="truncate text-sm font-medium">{user.name}</div>
          <div className="truncate text-xs text-muted-foreground" dir="ltr">{user.email}</div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <a href={localeUrls[other]} lang={other}>
            <Languages />
            {t(`common.language.${other}`)}
          </a>
        </DropdownMenuItem>
        <div className="flex items-center justify-between gap-1 px-2 py-1.5">
          {[['light', Sun], ['dark', Moon], ['system', Monitor]].map(([value, Icon]) => (
            <Button key={value} type="button" size="icon-sm" variant={mode === value ? 'secondary' : 'ghost'} aria-label={t(`common.mode.${value}`)} title={t(`common.mode.${value}`)} onClick={() => setMode(value)}><Icon /></Button>
          ))}
        </div>
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link href="/logout" method="post" as="button" className="w-full">
            <LogOut />
            {t('common.nav.logout')}
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
