export const SERVICES = [
  {
    piece: 'queen', tag: 'Queen · الوزير', title: 'مواقع وتطبيقات',
    desc: 'نبني موقع شركتك، أو تطبيق جوال، أو نظاماً خاصاً لإدارة عملك، من التصميم حتى النشر.',
    tags: ['مواقع إلكترونية', 'تطبيقات جوال', 'متاجر إلكترونية', 'أنظمة إدارة'],
  },
  {
    piece: 'knight', tag: 'Knight · الحصان', title: 'الذكاء الاصطناعي',
    desc: 'نوفّر وقتك ووقت فريقك: مساعد ذكي يردّ على عملائك، وأتمتة للمهام المتكرّرة، وتقارير تفهم منها بياناتك.',
    tags: ['مساعد لخدمة العملاء', 'أتمتة المهام', 'تحليل البيانات', 'يدعم العربية'],
  },
  {
    piece: 'rook', tag: 'Rook · القلعة', title: 'الاستضافة والحماية',
    desc: 'نجهّز لك خوادم سحابية سريعة وآمنة ونراقبها باستمرار، حتى يبقى موقعك وتطبيقك يعملان دون انقطاع.',
    tags: ['استضافة سحابية', 'حماية البيانات', 'نسخ احتياطي', 'مراقبة مستمرة'],
  },
  {
    piece: 'bishop', tag: 'Bishop · الفيل', title: 'استشارات تقنية',
    desc: 'لا تعرف من أين تبدأ؟ نراجع وضعك الحالي، ونقترح عليك خطة واضحة بالأولويات والتكلفة.',
    tags: ['خطة تقنية', 'تصميم تجربة المستخدم', 'إدارة المشاريع', 'مراجعة الأنظمة الحالية'],
  },
];

export default function Services() {
  return (
    <section className="block" id="services" aria-labelledby="services-title">
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">خدماتنا</span>
          <h2 id="services-title">ماذا نقدّم لك؟</h2>
          <p>أربع خدمات تغطّي ما تحتاجه شركتك تقنياً، من الفكرة إلى التشغيل.</p>
        </div>

        <div className="services">
          {SERVICES.map((s) => (
            <article className="svc" key={s.piece}>
              {/* The mini board is filled in by the page-motion script, as in the prototype. */}
              <div className="mini" data-piece={s.piece} aria-hidden="true" />
              <div className="svc-body">
                <span className="who mono">{s.tag}</span>
                <h3>{s.title}</h3>
                <p>{s.desc}</p>
                <ul className="tags">
                  {s.tags.map((t) => <li key={t}>{t}</li>)}
                </ul>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
