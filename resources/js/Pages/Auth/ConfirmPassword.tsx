import { Form } from '@inertiajs/react';
import TextField from '@/components/platform/TextField';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

/** Asked before sensitive account changes, at most every few hours. */
export default function ConfirmPassword() {
  const t = useT();

  return (
    <AuthLayout title={t('account.confirm.title')} description={t('account.confirm.subtitle')}>
      <Form action="/user/confirm-password" method="post" resetOnError className="flex flex-col gap-4">
        {({ errors, processing }) => (
          <>
            <TextField name="password" type="password" dir="ltr" label={t('account.fields.password')} autoComplete="current-password" autoFocus required error={errors.password} />
            <Button type="submit" size="lg" disabled={processing}>{t('account.confirm.submit')}</Button>
          </>
        )}
      </Form>
    </AuthLayout>
  );
}
