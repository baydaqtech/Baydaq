import { useState } from 'react';
import { useForm } from '@inertiajs/react';

const SERVICE_OPTIONS = [
  'مواقع وتطبيقات',
  'الذكاء الاصطناعي',
  'الاستضافة والحماية',
  'استشارات تقنية',
  'لست متأكداً بعد',
];

const EMPTY = { name: '', email: '', company: '', service: 'الذكاء الاصطناعي', message: '' };

export default function ContactSection() {
  const { data, setData: setField, post, processing, errors } = useForm(EMPTY);
  // Like the prototype, the success line stays until the visitor edits the form again.
  const [sent, setSent] = useState(false);

  function setData(key, value) {
    setSent(false);
    setField(key, value);
  }

  function submit(e) {
    e.preventDefault();
    setSent(false);
    post('/contact', {
      preserveScroll: true,
      onSuccess: () => { setField(EMPTY); setSent(true); },
    });
  }

  const firstError = errors.name || errors.email || errors.message || errors.service;
  const status = firstError || (sent ? 'وصلت رسالتك. سنعود إليك خلال يوم عمل واحد.' : '');

  return (
    <section className="block" id="contact" aria-labelledby="contact-title" style={{ paddingTop: 0 }}>
      <div className="wrap">
        <div className="contact">
          <div className="contact-side">
            <div>
              <span className="eyebrow">تواصل معنا</span>
              <h2 id="contact-title">حدّثنا عن<br /><span className="gold">مشروعك.</span></h2>
              <p>اكتب لنا باختصار ما تحتاجه، وسنتواصل معك خلال يوم عمل واحد لنتفق على الخطوة التالية.</p>
            </div>
            <dl>
              <div><dt>البريد الإلكتروني</dt><dd className="ltr">hello@baydaq.tech</dd></div>
              <div><dt>الهاتف</dt><dd className="ltr">+000 00 000 0000</dd></div>
              <div><dt>ساعات العمل</dt><dd>الأحد إلى الخميس، ٩ صباحاً إلى ٥ مساءً</dd></div>
            </dl>
          </div>

          <form id="contact-form" onSubmit={submit} noValidate>
            <div className="field">
              <label htmlFor="c-name">الاسم</label>
              <input id="c-name" name="name" type="text" autoComplete="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
            </div>
            <div className="field">
              <label htmlFor="c-email">البريد الإلكتروني</label>
              <input id="c-email" name="email" type="email" autoComplete="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
            </div>
            <div className="field">
              <label htmlFor="c-company">الشركة (اختياري)</label>
              <input id="c-company" name="company" type="text" autoComplete="organization" value={data.company} onChange={(e) => setData('company', e.target.value)} />
            </div>
            <div className="field">
              <label htmlFor="c-service">الخدمة المطلوبة</label>
              <select id="c-service" name="service" value={data.service} onChange={(e) => setData('service', e.target.value)}>
                {SERVICE_OPTIONS.map((o) => <option key={o}>{o}</option>)}
              </select>
            </div>
            <div className="field full">
              <label htmlFor="c-msg">ماذا تحتاج؟</label>
              <textarea id="c-msg" name="message" value={data.message} onChange={(e) => setData('message', e.target.value)} />
            </div>
            <div className="form-foot">
              <button className="btn btn-gold" type="submit" disabled={processing}>
                {processing ? 'جارٍ الإرسال…' : 'أرسل الرسالة'} <span className="arr" aria-hidden="true">←</span>
              </button>
              <span className="form-status" id="form-status" role="status">{status}</span>
            </div>
          </form>
        </div>
      </div>
    </section>
  );
}
