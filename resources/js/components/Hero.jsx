export default function Hero() {
  return (
    <section className="hero" aria-labelledby="hero-title">
      <div className="wrap">
        <div className="hero-copy">
          <span className="chip"><i />مواقع · تطبيقات · ذكاء اصطناعي · استضافة</span>
          <h1 id="hero-title">
            <span className="type1">كل حركة</span><br /><span className="gold">محسوبة.</span>
          </h1>
          <p className="lede">
            نصمّم ونبني المواقع والتطبيقات وحلول الذكاء الاصطناعي للشركات، ونبقى معك بعد الإطلاق. خطة واضحة، وتنفيذ دقيق، ونتائج تراها في عملك.
          </p>
          <div className="actions">
            <a className="btn btn-gold" href="#contact">حدّثنا عن مشروعك <span className="arr" aria-hidden="true">←</span></a>
            <a className="btn btn-line" href="#services">ماذا نقدّم؟</a>
          </div>
          <ul className="hero-foot">
            <li><b>✓</b> سعر ومدة واضحان قبل البدء</li>
            <li><b>✓</b> تسليم على مراحل</li>
            <li><b>✓</b> دعم بعد الإطلاق</li>
          </ul>
        </div>

        <div className="game">
          <div className="board-frame">
            <div className="board" id="board" role="group" aria-label="رقعة شطرنج: أنت تلعب بالأبيض ضد بيدق" />
          </div>
          <div className="game-bar">
            <p className="status" id="status" role="status" aria-live="polite">
              <i className="w" /><span>أنت تلعب بالأبيض.</span>
            </p>
            <div className="game-btns">
              <button className="btn btn-line btn-sm" id="undo" type="button">تراجع</button>
              <button className="btn btn-line btn-sm" id="reset" type="button">لعبة جديدة</button>
            </div>
          </div>
          <ol className="moves-log" id="moves" aria-label="سجل الحركات" data-empty="سجل الحركات يظهر هنا." />
        </div>
      </div>
    </section>
  );
}
