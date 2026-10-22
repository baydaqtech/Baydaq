import { Form, Link, usePage } from '@inertiajs/react';
import StatusMessage from '@/components/platform/StatusMessage';
import TextField from '@/components/platform/TextField';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function Login() {
  const t = useT();
  const { status } = usePage().props;

  return (
    <AuthLayout title={t('account.login.title')} description={t('account.login.subtitle')}>
      <StatusMessage>{status}</StatusMessage>
      <Form action="/login" method="post" resetOnError={['password']} className="flex flex-col gap-4">
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
            <TextField
              name="password"
              type="password"
              dir="ltr"
              label={t('account.fields.password')}
              autoComplete="current-password"
              required
              error={errors.password}
            />
            <div className="flex items-center justify-between gap-2 text-sm">
              <label className="flex items-center gap-2">
                <Checkbox name="remember" />
                {t('account.login.remember')}
              </label>
              <Link href="/forgot-password" className="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
                {t('account.login.forgot')}
              </Link>
            </div>
            <Button type="submit" size="lg" disabled={processing}>
              {t('account.login.submit')}
            </Button>
          </>
        )}
      </Form>
      <p className="mt-6 text-xs leading-relaxed text-muted-foreground">{t('account.login.no_signup')}</p>
    </AuthLayout>
  );
}
