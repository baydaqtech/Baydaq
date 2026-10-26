import { Form, Link, usePage } from '@inertiajs/react';
import StatusMessage from '@/components/platform/StatusMessage';
import TextField from '@/components/platform/TextField';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function ForgotPassword() {
  const t = useT();
  const { status } = usePage().props;

  return (
    <AuthLayout title={t('account.forgot.title')} description={t('account.forgot.subtitle')}>
      <StatusMessage>{status}</StatusMessage>
      <Form action="/forgot-password" method="post" className="flex flex-col gap-4">
        {({ errors, processing }) => (
          <>
            <TextField
              name="email"
              type="email"
              dir="ltr"
              label={t('account.fields.email')}
              autoComplete="username"
              autoFocus
              required
              error={errors.email}
            />
            <Button type="submit" size="lg" disabled={processing}>
              {t('account.forgot.submit')}
            </Button>
          </>
        )}
      </Form>
      <Link href="/login" className="mt-6 block text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
        {t('account.forgot.back_to_login')}
      </Link>
    </AuthLayout>
  );
}
