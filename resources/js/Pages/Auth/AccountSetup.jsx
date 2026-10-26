import { Form } from '@inertiajs/react';
import NewPasswordFields from '@/components/platform/NewPasswordFields';
import StatusMessage from '@/components/platform/StatusMessage';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function AccountSetup({ token, email }) {
  const t = useT();

  return (
    <AuthLayout title={t('account.setup.title')} description={t('account.setup.subtitle')}>
      <Form action="/account/setup" method="post" resetOnError className="flex flex-col gap-4">
        {({ errors, processing }) => (
          <>
            <StatusMessage className="mb-0 bg-destructive/10 text-destructive">{errors.email || errors.token}</StatusMessage>
            <input type="hidden" name="token" value={token} />
            <input type="hidden" name="email" value={email ?? ''} />
            <p className="text-sm text-muted-foreground" dir="ltr">{email}</p>
            <NewPasswordFields errors={errors} />
            <Button type="submit" size="lg" disabled={processing}>
              {t('account.setup.submit')}
            </Button>
          </>
        )}
      </Form>
    </AuthLayout>
  );
}
