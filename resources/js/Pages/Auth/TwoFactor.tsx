import { Form, Link, router, usePage } from '@inertiajs/react';
import { Copy, KeyRound, ShieldCheck, ShieldAlert } from 'lucide-react';
import { toast } from 'sonner';
import CodeField from '@/components/platform/CodeField';
import ConfirmAction from '@/components/platform/ConfirmAction';
import StatusMessage from '@/components/platform/StatusMessage';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

interface Props {
  enabled: boolean;
  pending: boolean;
  required: boolean;
  qr: string | null;
  setupKey: string | null;
  recoveryCodes?: string[];
  home: string;
}

type Errors = { code?: string; confirmTwoFactorAuthentication?: { code?: string } };

/** "AB12CD34…" → "AB12 CD34 …", easier to type into an authenticator by hand. */
function grouped(key: string): string {
  return key.replace(/(.{4})/g, '$1 ').trim();
}

/**
 * Two-step sign-in: turn it on, scan the QR code, prove it with a first code, keep the recovery
 * codes. Admins and account owners may be required to have it before they continue.
 */
export default function TwoFactor({ enabled, pending, required, qr, setupKey, recoveryCodes, home }: Props) {
  const t = useT();
  const a = (key: string) => t(`account.two_factor.${key}`);
  const { status } = usePage<{ status?: string }>().props;
  const copy = (text: string) => navigator.clipboard?.writeText(text).then(() => toast.success(a('copied')));

  return (
    <AuthLayout title={a('title')} description={a('subtitle')}>
      {status === 'two-factor-required' && <StatusMessage>{a('required_notice')}</StatusMessage>}

      <div className="mb-4 flex items-center justify-between gap-2">
        <span className="flex items-center gap-2 text-sm font-medium">
          {enabled ? <ShieldCheck className="size-4 text-emerald-600" /> : <ShieldAlert className="size-4 text-amber-600" />}
          {a('status')}
        </span>
        <Badge variant={enabled ? 'default' : 'outline'}>{a(enabled ? 'on' : pending ? 'pending' : 'off')}</Badge>
      </div>

      {!enabled && !pending && (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-muted-foreground">{a('explain')}</p>
          <Form action="/user/two-factor-authentication" method="post">
            {({ processing }) => <Button type="submit" size="lg" className="w-full" disabled={processing}><KeyRound data-icon="inline-start" />{a('turn_on')}</Button>}
          </Form>
        </div>
      )}

      {pending && qr && setupKey && (
        <div className="flex flex-col gap-4">
          <ol className="flex list-decimal flex-col gap-1 ps-5 text-sm text-muted-foreground">
            <li>{a('step_install')}</li>
            <li>{a('step_scan')}</li>
            <li>{a('step_code')}</li>
          </ol>
          <img src={qr} alt={a('qr_alt')} className="mx-auto size-44 rounded-lg bg-white p-2 ring-1 ring-border" />
          <div className="flex items-center justify-between gap-2 rounded-lg bg-muted px-3 py-2">
            <code dir="ltr" className="text-xs break-all">{grouped(setupKey)}</code>
            <Button type="button" variant="ghost" size="icon-sm" aria-label={a('copy_key')} onClick={() => copy(setupKey)}><Copy /></Button>
          </div>
          <Form action="/user/confirmed-two-factor-authentication" method="post" resetOnError className="flex flex-col gap-3">
            {({ errors, processing }) => {
              const error = (errors as Errors).confirmTwoFactorAuthentication?.code ?? (errors as Errors).code;

              return (
                <>
                  <CodeField name="code" autoFocus invalid={Boolean(error)} aria-label={a('code')} />
                  {error && <FieldError className="text-center">{error}</FieldError>}
                  <Button type="submit" size="lg" disabled={processing}>{a('confirm')}</Button>
                </>
              );
            }}
          </Form>
          <Button type="button" variant="ghost" size="sm" onClick={() => router.delete('/user/two-factor-authentication', { preserveScroll: true })}>{a('cancel')}</Button>
        </div>
      )}

      {enabled && (
        <div className="flex flex-col gap-4">
          {recoveryCodes && recoveryCodes.length > 0 ? (
            <div className="flex flex-col gap-2">
              <p className="text-sm text-muted-foreground">{a('codes_explain')}</p>
              <ul dir="ltr" className="grid gap-1 rounded-lg bg-muted p-3 font-mono text-xs">
                {recoveryCodes.map((code) => <li key={code}>{code}</li>)}
              </ul>
              <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" onClick={() => copy(recoveryCodes.join('\n'))}><Copy data-icon="inline-start" />{a('copy_codes')}</Button>
                <ConfirmAction href="/user/two-factor-recovery-codes" variant="ghost" size="sm" title={a('new_codes_title')} description={a('new_codes_explain')}>{a('new_codes')}</ConfirmAction>
              </div>
            </div>
          ) : (
            <Button type="button" variant="outline" onClick={() => router.reload({ only: ['recoveryCodes'] })}>{a('show_codes')}</Button>
          )}
          <Button asChild size="lg"><Link href={home}>{a('continue')}</Link></Button>
          {!required && (
            <ConfirmAction href="/user/two-factor-authentication" method="delete" variant="ghost" size="sm" title={a('turn_off_title')} description={a('turn_off_explain')}>
              {a('turn_off')}
            </ConfirmAction>
          )}
        </div>
      )}

      {!enabled && !required && (
        <Button asChild variant="link" size="sm" className="mt-2 w-full"><Link href={home}>{a('later')}</Link></Button>
      )}
    </AuthLayout>
  );
}
