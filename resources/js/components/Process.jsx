export const STEPS = [
  { n: '١', phase: 'الخطوة الأولى', title: 'نفهم احتياجك', desc: 'نجلس معك لنفهم فكرتك وأهدافك وميزانيتك.', get: 'تستلم: عرضاً واضحاً بالسعر والمدة', coord: 'e1' },
  { n: '٢', phase: 'الخطوة الثانية', title: 'نصمّم الحل', desc: 'نرسم شكل الموقع أو التطبيق، وتوافق عليه قبل أن نبدأ البرمجة.', get: 'تستلم: تصميماً تجرّبه بنفسك', coord: 'f1' },
  { n: '٣', phase: 'الخطوة الثالثة', title: 'نبني ونطلعك أولاً بأول', desc: 'نعمل على مراحل قصيرة، وتشاهد ما أنجزناه كل أسبوعين.', get: 'تستلم: نسخة تعمل بعد كل مرحلة', coord: 'g1' },
  { n: '٤', phase: 'الخطوة الرابعة', title: 'نطلق ونبقى معك', desc: 'ننشر المشروع ونتابع عمله، ونقدّم الدعم والتحديثات بعد الإطلاق.', get: 'تستلم: مشروعاً يعمل ودعماً مستمراً', coord: 'h1' },
];

export default function Process() {
  return (
    <section className="block" id="process" aria-labelledby="process-title" style={{ paddingTop: 0 }}>
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">كيف نعمل</span>
          <h2 id="process-title">أربع خطوات<br />واضحة.</h2>
          <p>تعرف في كل خطوة ماذا نفعل، وتستلم في نهايتها شيئاً تراه بعينك.</p>
        </div>

        <ol className="rank">
          {STEPS.map((s) => (
            <li className="sq" key={s.coord}>
              <div className="top"><span className="num">{s.n}</span><span className="phase">{s.phase}</span></div>
              <div>
                <h3>{s.title}</h3>
                <p>{s.desc}</p>
                <p className="get">{s.get}</p>
              </div>
              <span className="coord mono">{s.coord}</span>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
