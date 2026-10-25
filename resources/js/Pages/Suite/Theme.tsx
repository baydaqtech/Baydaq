import { useForm } from '@inertiajs/react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';

interface ThemeForm { app_name: string; primary_color: string; accent_color: string; radius: string; mode: string; logo_url: string | null }

const RADII: Record<string, string> = { small: '0.35rem', medium: '0.625rem', large: '1rem' };

/** Text colour that reads on a background (same rule as the server). */
function readableOn(hex: string): string {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const c = parseInt(hex.slice(i, i + 2), 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.179 ? '#1A1A1A' : '#FFFFFF';
}

/** The owner's branding, with a live preview. */
export default function ThemeSettings({ theme }: { theme: ThemeForm }) {
  const t = useT();
  const url = useSuiteUrl();
  const c = (key: string) => t(`suite.theme.${key}`);
  const form = useForm<{ app_name: string; primary_color: string; accent_color: string; radius: string; mode: string; logo: File | null; remove_logo: boolean }>({
    app_name: theme.app_name, primary_color: theme.primary_color, accent_color: theme.accent_color, radius: theme.radius, mode: theme.mode, logo: null, remove_logo: false,
  });
  const { data, setData, errors } = form;
  const valid = (hex: string) => /^#[0-9A-Fa-f]{6}$/.test(hex);
  const foreground = valid(data.primary_color) ? readableOn(data.primary_color) : '#FFFFFF';

  return (
    <SuiteLayout title={c('title')}>
      <PageHeader title={c('title')} description={c('lede')} actions={<ConfirmAction href={url('/settings/theme')} method="delete" size="sm" variant="ghost" title={c('reset')}>{c('reset')}</ConfirmAction>} />
      <div className="grid gap-4 lg:grid-cols-[1.2fr_1fr]">
        <Card>
          <CardContent>
            <form className="grid gap-4 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post(url('/settings/theme'), { forceFormData: true, preserveScroll: true }); }}>
              <FormField label={c('app_name')} error={errors.app_name} className="sm:col-span-2"><Input value={data.app_name} maxLength={60} onChange={(event) => setData('app_name', event.target.value)} /></FormField>
              {(['primary_color', 'accent_color'] as const).map((field) => (
                <FormField key={field} label={c(field === 'primary_color' ? 'primary' : 'accent')} error={errors[field]}>
                  <div className="flex gap-2">
                    <input type="color" aria-label={c(field === 'primary_color' ? 'primary' : 'accent')} className="h-9 w-12 cursor-pointer rounded border" value={valid(data[field]) ? data[field] : '#000000'} onChange={(event) => setData(field, event.target.value.toUpperCase())} />
                    <Input dir="ltr" value={data[field]} maxLength={7} onChange={(event) => setData(field, event.target.value)} />
                  </div>
                </FormField>
              ))}
              <FormField label={c('radius')} error={errors.radius}><SelectInput value={data.radius} onChange={(value) => setData('radius', value)} options={['small', 'medium', 'large'].map((value) => ({ value, label: c(`radii.${value}`) }))} /></FormField>
              <FormField label={c('mode')} error={errors.mode}><SelectInput value={data.mode} onChange={(value) => setData('mode', value)} options={['light', 'dark', 'system'].map((value) => ({ value, label: t(`common.mode.${value}`) }))} /></FormField>
              <FormField label={c('logo')} error={errors.logo} className="sm:col-span-2">
                <div className="flex items-center gap-3">
                  {theme.logo_url && !data.remove_logo && <img src={theme.logo_url} alt="" className="h-10 max-w-32 rounded border bg-white object-contain p-1" />}
                  <Input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" onChange={(event) => setData('logo', event.target.files?.[0] ?? null)} />
                </div>
              </FormField>
              {theme.logo_url && <label className="flex items-center gap-2 text-sm sm:col-span-2"><Checkbox checked={data.remove_logo} onCheckedChange={(state) => setData('remove_logo', state === true)} />{c('remove_logo')}</label>}
              <div className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></div>
            </form>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{c('preview')}</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-3">
            <div className="flex items-center gap-2 p-3 text-sm font-semibold" style={{ background: valid(data.primary_color) ? data.primary_color : undefined, color: foreground, borderRadius: RADII[data.radius] }}>
              {data.app_name || t('suite.title')}
            </div>
            <div className="border p-4 text-sm" style={{ borderRadius: RADII[data.radius] }}>
              <p className="mb-3 text-muted-foreground">{c('preview_text')}</p>
              <button type="button" className="px-4 py-2 text-sm font-medium" style={{ background: data.primary_color, color: foreground, borderRadius: RADII[data.radius], outline: `2px solid ${data.accent_color}`, outlineOffset: 2 }}>{c('preview_button')}</button>
            </div>
            <p className="text-xs text-muted-foreground">{c('contrast').replace(':color', foreground)}</p>
          </CardContent>
        </Card>
      </div>
    </SuiteLayout>
  );
}
