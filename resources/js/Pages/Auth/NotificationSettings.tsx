import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

interface Kind { kind: string; label: string; mail: boolean }

/** Which alerts also arrive by email; all of them always appear in the notification centre. */
export default function NotificationSettings({ kinds, home }: { kinds: Kind[]; home: string }) {
  const t = useT();
  const a = (key: string) => t(`account.notifications.${key}`);
  const form = useForm({ mail: Object.fromEntries(kinds.map((kind) => [kind.kind, kind.mail])) as Record<string, boolean> });

  return (
    <AuthLayout title={a('title')} description={a('subtitle')}>
      <form className="flex flex-col gap-4" onSubmit={(event) => { event.preventDefault(); form.put('/account/notifications', { preserveScroll: true }); }}>
        <ul className="flex flex-col divide-y">
          <li className="flex justify-end pb-1 text-xs text-muted-foreground">{a('email')}</li>
          {kinds.map((kind) => (
            <li key={kind.kind} className="flex items-center justify-between gap-3 py-2.5">
              <label htmlFor={`mail-${kind.kind}`} className="text-sm">{kind.label}</label>
              <Switch
                id={`mail-${kind.kind}`}
                checked={form.data.mail[kind.kind]}
                onCheckedChange={(checked) => form.setData('mail', { ...form.data.mail, [kind.kind]: checked })}
              />
            </li>
          ))}
        </ul>
        <Button type="submit" size="lg" disabled={form.processing}>{a('save')}</Button>
        <Button asChild variant="link" size="sm"><Link href={home}>{a('back')}</Link></Button>
      </form>
    </AuthLayout>
  );
}
