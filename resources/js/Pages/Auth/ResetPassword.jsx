import { Form } from '@inertiajs/react';
import NewPasswordFields from '@/components/platform/NewPasswordFields';
import TextField from '@/components/platform/TextField';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function ResetPassword({ token, email }) {
  const t = useT();

  return (
    <AuthLayout title={t('account.reset.title')} description={t('account.reset.subtitle')}>
      <Form action="/reset-password" method="post" resetOnError={['password', 'password_confirmation']} className="flex flex-col gap-4">
        {({ errors, processing }) => (
          <>
            <input type="hidden" name="token" value={token} />
            <TextField
              name="email"
              type="email"
              dir="ltr"
              label={t('account.fields.email')}
              defaultValue={email ?? ''}
              autoComplete="username"
              required
              error={errors.email}
            />
            <NewPasswordFields errors={errors} />
            <Button type="submit" size="lg" disabled={processing}>
              {t('account.reset.submit')}
            </Button>
          </>
        )}
      </Form>
    </AuthLayout>
  );
}
