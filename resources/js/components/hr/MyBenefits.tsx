import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import type { InsuranceMember } from '@/Pages/Hr/Insurance/Index';
import type { AttendeeItem, TrainingItem } from '@/Pages/Hr/Training/Index';

export type MyInsurance = (InsuranceMember & { insurer: string; policy_number: string | null; policy_ends_on: string })[];
export type MyTrainings = (TrainingItem & { attendance: AttendeeItem })[];

/** My medical cover (and my dependants'). */
export function MyInsuranceCard({ members }: { members: MyInsurance }) {
  const t = useT();
  const { locale } = useLocale();

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('hr.insurance.mine')}</CardTitle></CardHeader>
      <CardContent className="divide-y text-sm">
        {members.length === 0 && <p className="text-muted-foreground">{t('hr.insurance.none_mine')}</p>}
        {members.map((member) => (
          <div key={member.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
            <span>{member.relation === 'self' ? t('hr.insurance.relations.self') : `${t(`hr.insurance.relations.${member.relation}`)}: ${member.name}`} · {member.insurer} <Badge variant="outline">{member.class}</Badge></span>
            <span className="text-muted-foreground"><span dir="ltr">{member.card_number}</span> · {formatDate(member.ends_on ?? member.policy_ends_on, locale)}</span>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

/** My training sessions and certificates. */
export function MyTrainingCard({ trainings }: { trainings: MyTrainings }) {
  const t = useT();
  const { locale } = useLocale();

  if (trainings.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('hr.training.mine')}</CardTitle></CardHeader>
      <CardContent className="divide-y text-sm">
        {trainings.map((training) => (
          <div key={training.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
            <span><span className="font-medium">{training.title}</span> · {formatDate(training.starts_on, locale)}{training.location && ` · ${training.location}`}</span>
            <span className="flex items-center gap-2">
              {training.attendance.certificate_expires_on && <span className="text-muted-foreground">{t('hr.training.expires', { date: formatDate(training.attendance.certificate_expires_on, locale) })}</span>}
              <Badge variant="outline">{t(`hr.training.attendance.${training.attendance.status}`)}</Badge>
            </span>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
