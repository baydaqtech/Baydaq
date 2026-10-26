import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';

/** The employee's own till PIN (P1). */
export default function PinCard({ hasPin }: { hasPin: boolean }) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const form = useForm({ pin: '', pin_confirmation: '' });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="flex items-center gap-2 text-base"><KeyRound className="size-4" />{t('hr.me.pin')}</CardTitle>
        <Button size="sm" variant="outline" onClick={() => setOpen(!open)}>{t('common.actions.edit')}</Button>
      </CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <p className="text-muted-foreground">{hasPin ? t('hr.me.pin_set') : t('hr.me.pin_none')} {t('hr.me.pin_hint')}</p>
        {open && (
          <form onSubmit={(event) => { event.preventDefault(); form.put('/me/pin', { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } }); }} className="grid gap-3 sm:grid-cols-3 sm:items-end">
            <FormField label={t('hr.me.pin_new')} error={form.errors.pin}><Input type="password" inputMode="numeric" autoComplete="off" dir="ltr" maxLength={6} value={form.data.pin} onChange={(event) => form.setData('pin', event.target.value)} /></FormField>
            <FormField label={t('hr.me.pin_confirm')}><Input type="password" inputMode="numeric" autoComplete="off" dir="ltr" maxLength={6} value={form.data.pin_confirmation} onChange={(event) => form.setData('pin_confirmation', event.target.value)} /></FormField>
            <Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button>
          </form>
        )}
      </CardContent>
    </Card>
  );
}
