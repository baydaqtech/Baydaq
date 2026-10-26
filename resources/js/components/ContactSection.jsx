import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { useCopy, useT } from '../lib/i18n';
import { ABOUT_IN_CONTACT } from './About';

// Form or WhatsApp in the contact card. WhatsApp for now: form messages are stored but
// nothing notifies anyone yet. Set to true to bring the form back (still wired to /contact).
const SHOW_CONTACT_FORM = false;

// `digits` is the international number without "+" or spaces (Saudi mobile 054 932 9916).
const WHATSAPP = { digits: '966549329916', display: '+966 54 932 9916' };
// The number is not printed on the page (strangers were calling it); only the WhatsApp button uses it.
// Set to true to show it again.
const SHOW_PHONE_NUMBER = false;

export default function ContactSection() {
  const t = useT();
  const form = useCopy('landing.contact.form');
  const whatsappLink = `https://wa.me/${WHATSAPP.digits}?text=${encodeURIComponent(t('landing.contact.whatsapp_greeting'))}`;
  const EMPTY = { name: '', email: '', company: '', service: form.default_option, message: '' };
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
  const status = firstError || (sent ? form.sent : '');

  return (
    <section className="block" id="contact" aria-labelledby="contact-title" style={{ paddingTop: 0 }}>
      <div className="wrap">
        {ABOUT_IN_CONTACT && (
          <div className="contact-about">
            {/* The nav's "about" link lands here. A bare anchor, so the page's board-patch
                script (which decorates anything with id="about" taller than 100px) skips it. */}
            <span className="about-anchor" id="about" aria-hidden="true" />
            <span className="eyebrow">{t('landing.about.eyebrow')}</span>
            <p>{t('landing.about.text')}</p>
          </div>
        )}
        <div className="contact">
          <div className="contact-side">
            <div>
              <span className="eyebrow">{t('landing.contact.eyebrow')}</span>
              <h2 id="contact-title">{t('landing.contact.title_1')}<br /><span className="gold">{t('landing.contact.title_2')}</span></h2>
              {SHOW_CONTACT_FORM
                ? <p>{t('landing.contact.lede_form')}</p>
                : <p>{t('landing.contact.lede_whatsapp')}</p>}
            </div>
            <dl>
              {SHOW_CONTACT_FORM && SHOW_PHONE_NUMBER && <div><dt>{t('landing.contact.phone')}</dt><dd className="ltr">{WHATSAPP.display}</dd></div>}
              <div><dt>{t('landing.contact.hours_label')}</dt><dd>{t('landing.contact.hours')}</dd></div>
            </dl>
            {/* The written alternative to WhatsApp: the two request forms (no email address is published). */}
            <div className="contact-requests">
              <p>{t('landing.contact.or_request')}</p>
              <div>
                <a className="btn btn-line btn-sm" href="/request/partnership">{t('landing.contact.request_partnership')}</a>
                <a className="btn btn-line btn-sm" href="/request/service">{t('landing.contact.request_service')}</a>
              </div>
            </div>
          </div>

          {!SHOW_CONTACT_FORM && (
            <div className="contact-wa">
              <span className="wa-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v9.5a1.5 1.5 0 0 1-1.5 1.5H10l-5.5 4v-15A1.5 1.5 0 0 1 5 4.5z" /><path d="M8.5 9.5h7M8.5 12.5h4.5" /></svg>
              </span>
              <h3>{t('landing.contact.whatsapp_title')}</h3>
              <p>{t('landing.contact.whatsapp_text')}</p>
              {/* The number itself stays off the page to cut down on cold calls; the button carries it. */}
              {SHOW_PHONE_NUMBER && <p className="wa-number">{WHATSAPP.display}</p>}
              <a className="btn btn-wa" href={whatsappLink} target="_blank" rel="noopener noreferrer">
                {t('landing.contact.whatsapp_button')}
                {/* WhatsApp glyph from Simple Icons (CC0) */}
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" /></svg>
              </a>
            </div>
          )}

          {SHOW_CONTACT_FORM && (
            <form id="contact-form" onSubmit={submit} noValidate>
              <div className="field">
                <label htmlFor="c-name">{form.name}</label>
                <input id="c-name" name="name" type="text" autoComplete="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
              </div>
              <div className="field">
                <label htmlFor="c-email">{form.email}</label>
                <input id="c-email" name="email" type="email" autoComplete="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
              </div>
              <div className="field">
                <label htmlFor="c-company">{form.company}</label>
                <input id="c-company" name="company" type="text" autoComplete="organization" value={data.company} onChange={(e) => setData('company', e.target.value)} />
              </div>
              <div className="field">
                <label htmlFor="c-service">{form.service}</label>
                <select id="c-service" name="service" value={data.service} onChange={(e) => setData('service', e.target.value)}>
                  {form.options.map((o) => <option key={o}>{o}</option>)}
                </select>
              </div>
              <div className="field full">
                <label htmlFor="c-msg">{form.message}</label>
                <textarea id="c-msg" name="message" value={data.message} onChange={(e) => setData('message', e.target.value)} />
              </div>
              <div className="form-foot">
                <button className="btn btn-gold" type="submit" disabled={processing}>
                  {processing ? form.sending : form.submit} <span className="arr" aria-hidden="true">←</span>
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
