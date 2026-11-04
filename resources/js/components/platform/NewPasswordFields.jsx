import TextField from '@/components/platform/TextField';
import { useT } from '@/lib/i18n';

/** "New password" + "confirm password", shared by reset, forced change and account setup. */
export default function NewPasswordFields({ errors }) {
  const t = useT();

  return (
    <>
      <TextField
        name="password"
        type="password"
        dir="ltr"
        label={t('account.fields.new_password')}
        hint={t('account.fields.password_hint')}
        autoComplete="new-password"
        autoFocus
        required
        error={errors.password}
      />
      <TextField
        name="password_confirmation"
        type="password"
        dir="ltr"
        label={t('account.fields.password_confirmation')}
        autoComplete="new-password"
        required
        error={errors.password_confirmation}
      />
    </>
  );
}
