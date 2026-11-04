import { Form } from '@inertiajs/react';
import NewPasswordFields from '@/components/platform/NewPasswordFields';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function ChangePassword() {
  const t = useT();

  return (
    <AuthLayout title={t('account.change.title')} description={t('account.change.subtitle')}>
      <Form action="/password/change" method="put" resetOnError className="flex flex-col gap-4">
        {({ errors, processing }) => (
          <>
            <NewPasswordFields errors={errors} />
            <Button type="submit" size="lg" disabled={processing}>
              {t('account.change.submit')}
            </Button>
          </>
        )}
      </Form>
    </AuthLayout>
  );
}
