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

// Form or WhatsApp in the contact card. WhatsApp for now: form messages are stored but
// nothing notifies anyone yet. Set to true to bring the form back (still wired to /contact).
const SHOW_CONTACT_FORM = false;

// `digits` is the international number without "+" or spaces (Saudi mobile 054 932 9916).
const WHATSAPP = { digits: '966549329916', display: '+966 54 932 9916' };
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
                : <p>راسلنا على واتساب بما تحتاجه في أي وقت، وسنرد عليك سريعاً لنتفق على الخطوة التالية.</p>}
            </div>
            <dl>
              {SHOW_CONTACT_FORM && <div><dt>الهاتف</dt><dd className="ltr">{WHATSAPP.display}</dd></div>}
              <div><dt>أوقات التواصل</dt><dd>متاحون في أي وقت، طوال أيام الأسبوع</dd></div>
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
              <a className="btn btn-wa" href={whatsappLink} target="_blank" rel="noopener noreferrer">
                ابدأ المحادثة على واتساب
                {/* WhatsApp glyph from Simple Icons (CC0) */}
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" /></svg>
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
