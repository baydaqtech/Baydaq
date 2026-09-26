import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ABOUT_IN_CONTACT, ABOUT_TEXT } from './About';

const SERVICE_OPTIONS = [
  'مواقع وتطبيقات',
  'الذكاء الاصطناعي',
  'الاستضافة والحماية',
  'استشارات تقنية',
  'لست متأكداً بعد',
];

const EMPTY = { name: '', email: '', company: '', service: 'الذكاء الاصطناعي', message: '' };

// Form or WhatsApp in the contact card. The form stays on until the real WhatsApp number
// below is in; then set this to false and the WhatsApp panel takes the form's place.
const SHOW_CONTACT_FORM = true;

// TODO: real WhatsApp number. `digits` is the international number without "+" or spaces.
const WHATSAPP = { digits: '000000000000', display: '+000 00 000 0000' };
const WHATSAPP_GREETING = 'مرحباً، أريد التحدث عن مشروع.';
const whatsappLink = `https://wa.me/${WHATSAPP.digits}?text=${encodeURIComponent(WHATSAPP_GREETING)}`;

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
        {ABOUT_IN_CONTACT && (
          <div className="contact-about">
            {/* The nav's "about" link lands here. A bare anchor, so the page's board-patch
                script (which decorates anything with id="about" taller than 100px) skips it. */}
            <span className="about-anchor" id="about" aria-hidden="true" />
            <span className="eyebrow">من نحن</span>
            <p>{ABOUT_TEXT}</p>
          </div>
        )}
        <div className="contact">
          <div className="contact-side">
            <div>
              <span className="eyebrow">تواصل معنا</span>
              <h2 id="contact-title">حدّثنا عن<br /><span className="gold">مشروعك.</span></h2>
              {SHOW_CONTACT_FORM
                ? <p>اكتب لنا باختصار ما تحتاجه، وسنتواصل معك خلال يوم عمل واحد لنتفق على الخطوة التالية.</p>
                : <p>راسلنا على واتساب بما تحتاجه، وسنرد عليك خلال يوم عمل واحد لنتفق على الخطوة التالية.</p>}
            </div>
            <dl>
              <div><dt>البريد الإلكتروني</dt><dd className="ltr">hello@baydaq.tech</dd></div>
              {SHOW_CONTACT_FORM && <div><dt>الهاتف</dt><dd className="ltr">{WHATSAPP.display}</dd></div>}
              <div><dt>ساعات العمل</dt><dd>الأحد إلى الخميس، ٩ صباحاً إلى ٥ مساءً</dd></div>
            </dl>
          </div>

          {!SHOW_CONTACT_FORM && (
            <div className="contact-wa">
              <span className="wa-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v9.5a1.5 1.5 0 0 1-1.5 1.5H10l-5.5 4v-15A1.5 1.5 0 0 1 5 4.5z" /><path d="M8.5 9.5h7M8.5 12.5h4.5" /></svg>
              </span>
              <h3>أسرع طريقة للوصول إلينا</h3>
              <p>اضغط الزر فيفتح واتساب برسالة جاهزة، أو احفظ الرقم وراسلنا متى شئت.</p>
              <p className="wa-number">{WHATSAPP.display}</p>
              <a className="btn btn-gold" href={whatsappLink} target="_blank" rel="noopener noreferrer">
                ابدأ المحادثة على واتساب <span className="arr" aria-hidden="true">←</span>
              </a>
            </div>
          )}

          {SHOW_CONTACT_FORM && (
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
          )}
        </div>
      </div>
    </section>
  );
}
