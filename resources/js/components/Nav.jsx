export default function Nav() {
  return (
    <header className="nav">
      <div className="wrap">
        <a className="brand" href="#top" aria-label="بيدق تكنولوجي، الصفحة الرئيسية">
          <svg viewBox="0 0 100 100" aria-hidden="true"><use href="#logo-mark" /></svg>
          <b>بيدق</b><span>تكنولوجي</span>
        </a>
        <ul>
          <li><a href="#services">خدماتنا</a></li>
          <li><a href="#process">كيف نعمل</a></li>
          <li><a href="#about">من نحن</a></li>
          <li><a href="#contact">تواصل معنا</a></li>
        </ul>
        <a className="btn btn-gold" href="#contact">تواصل معنا</a>
      </div>
      <span className="progress" aria-hidden="true" />
    </header>
  );
}
