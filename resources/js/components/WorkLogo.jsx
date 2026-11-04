// Each project's own lockup, drawn from its brand: OpsMaster ships an outlined logo; the
// others are their mark with the name set in the page's font, so Arabic reads the same everywhere.

/** Nuzul's arch, with the dot and the smile under it. */
function ArchMark() {
  return (
    <svg className="wl-mark" viewBox="0 0 40 48" aria-hidden="true">
      <path d="M7 45V20a13 13 0 0 1 26 0v25" />
      <circle cx="20" cy="20" r="2.4" />
      <path d="M14 27v2.5a6 6 0 0 0 12 0V27" />
    </svg>
  );
}

/** PC Home's coral disc holding a game controller. */
function ControllerDisc() {
  return (
    <svg className="wl-disc" viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="12" />
      <g transform="translate(2.3 2.3) scale(.8)">
        <path d="M6.4 8.6C4.9 8.6 3.8 9.7 3.5 11.2L2.2 16.4C1.7 18.6 4.2 20.2 5.7 18.5L8.1 15.9C8.6 15.4 9.2 15.1 9.9 15.1H14.1C14.8 15.1 15.4 15.4 15.9 15.9L18.3 18.5C19.8 20.2 22.3 18.6 21.8 16.4L20.5 11.2C20.2 9.7 19.1 8.6 17.6 8.6Z" fill="#fff" />
        <rect x="5.5" y="11.35" width="4.6" height="1.5" rx=".55" />
        <rect x="7.05" y="9.8" width="1.5" height="4.6" rx=".55" />
        <circle cx="16.2" cy="11.2" r=".95" />
        <circle cx="18.3" cy="13.1" r=".95" />
      </g>
    </svg>
  );
}

/** A project's logo, by its slug; `name` is its name in the page's language. */
export default function WorkLogo({ slug, name }) {
  switch (slug) {
    case 'opsmaster':
      return <img className="wl-img" src="/images/work/opsmaster/logo.svg" alt={name} width="2929" height="574" />;
    case 'nuzul':
    case 'nuzul-umrah':
      return <span className={`wl wl-${slug}`}><ArchMark /><span className="wl-name">{name}</span></span>;
    case 'healthy-meals':
      return <span className="wl wl-meals"><span className="wl-square" aria-hidden="true">هـ</span><span className="wl-name">{name}</span></span>;
    case 'pchome':
      // The wordmark is artwork: it never mirrors or translates.
      return (
        <span className="wl wl-pchome" dir="ltr" role="img" aria-label={name}>
          <span className="wl-coral">pc</span><span>h</span><ControllerDisc /><span>me</span>
        </span>
      );
    default:
      return <span className="wl"><span className="wl-name">{name}</span></span>;
  }
}
