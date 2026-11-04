import { useState } from 'react';
import { Form } from '@inertiajs/react';
import CodeField from '@/components/platform/CodeField';
import TextField from '@/components/platform/TextField';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

/** Second step of signing in: the code from the authenticator app, or one recovery code. */
export default function TwoFactorChallenge() {
  const t = useT();
  const [recovery, setRecovery] = useState(false);

  return (
    <AuthLayout title={t('account.challenge.title')} description={t(recovery ? 'account.challenge.recovery_subtitle' : 'account.challenge.subtitle')}>
      <Form key={recovery ? 'recovery' : 'code'} action="/two-factor-challenge" method="post" resetOnError className="flex flex-col gap-4">
        {({ errors, processing }) => (
          <>
            {recovery ? (
              <TextField name="recovery_code" dir="ltr" label={t('account.challenge.recovery_code')} autoComplete="off" autoFocus required error={errors.recovery_code} />
            ) : (
              <div className="flex flex-col gap-2">
                <CodeField name="code" autoFocus invalid={Boolean(errors.code)} aria-label={t('account.challenge.code')} />
                {errors.code && <FieldError className="text-center">{errors.code}</FieldError>}
              </div>
            )}
            <Button type="submit" size="lg" disabled={processing}>{t('account.challenge.submit')}</Button>
            <Button type="button" variant="link" size="sm" onClick={() => setRecovery(!recovery)}>
              {t(recovery ? 'account.challenge.use_code' : 'account.challenge.use_recovery')}
            </Button>
          </>
        )}
      </Form>
    </AuthLayout>
  );
}
