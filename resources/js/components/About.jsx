// Chess lines in this section (the Ruqaa headline, the pawn caption, the piece tags) are hidden
// for now but kept; set to true to show them again.
const SHOW_CHESS_LINES = false;
// The pawn-to-vizier drawing beside the copy and the three numbered principles under it are
// hidden for now too; set either to true to bring it back.
const SHOW_PAWN_FIGURE = false;
const SHOW_PRINCIPLES = false;

const PRINCIPLES = [
  { n: '١', piece: 'knight', tag: 'الحصان: يجد طريقه حيث لا يصل غيره', title: 'حلول على مقاسك', desc: 'سواء كنت تبدأ مشروعاً جديداً أو تطوّر نظاماً قائماً، نصمّم ما يناسب حجم عملك وميزانيتك.' },
  { n: '٢', piece: 'pawn', tag: 'البيدق: يتقدّم حتى يصبح وزيراً', title: 'جاهز للنمو', desc: 'نبني بطريقة تسمح لك بإضافة مزايا جديدة لاحقاً، دون أن تبدأ من الصفر.' },
  { n: '٣', piece: 'rook', tag: 'القلعة: تحمي وتبقى ثابتة', title: 'معك بعد الإطلاق', desc: 'لا نسلّم المشروع ونختفي. نتابع ونصلح ونطوّر معك متى احتجت.' },
];

export default function About() {
  return (
    <section className="block heritage" id="about" aria-labelledby="about-title" style={{ paddingTop: 0 }}>
      <div className={'wrap about-grid' + (SHOW_PAWN_FIGURE ? '' : ' solo')}>
        <div className="heritage-copy">
          <span className="eyebrow" id="about-title">من نحن</span>
          {SHOW_CHESS_LINES && <p className="ruqaa gold">البيدق إذا بلغ آخر الرقعة ترقّى.</p>}
          <p>
            بيدق تكنولوجي شركة تقنية تساعد الشركات على بناء مواقعها وتطبيقاتها وتطويرها. نبدأ معك من فكرتك مهما كانت صغيرة، ونحوّلها خطوة بعد خطوة إلى منتج يعمل ويخدم عملك.
          </p>
        </div>

        {SHOW_PAWN_FIGURE && (
          <figure className="promo" aria-label="بيدق يتقدّم من الخانة e2 إلى e8 فيصبح وزيراً">
            <div className="trail-row" aria-hidden="true">
              {[0, 1, 2, 3, 4, 5, 6].map((i) => <span className="t" key={i} />)}
              <span className="trail" />
              <span className="p ghost"><svg viewBox="0 0 45 45"><use href="#p-pawn" /></svg></span>
              <span className="p walker"><svg viewBox="0 0 45 45"><use href="#p-pawn" /></svg></span>
              <span className="p queen"><svg viewBox="0 0 45 45"><use href="#p-queen" /></svg></span>
            </div>
            <div className="trail-labels mono" aria-hidden="true">
              {['e2', 'e3', 'e4', 'e5', 'e6', 'e7', 'e8=Q'].map((l) => <span key={l}>{l}</span>)}
            </div>
            {SHOW_CHESS_LINES && <figcaption className="trail-cap">ست خطوات إلى الأمام… والبيدق يصبح وزيراً.</figcaption>}
          </figure>
        )}
      </div>

      {SHOW_PRINCIPLES && (
        <div className="wrap">
          <ul className="principles">
            {PRINCIPLES.map((p) => (
              <li key={p.piece}>
                <div className="pr-stage" aria-hidden="true">
                  <span className="pr-num">{p.n}</span>
                  <span className="pr-base" />
                  <svg className="pr-piece" viewBox="0 0 45 45"><use href={`#p-${p.piece}`} /></svg>
                </div>
                {SHOW_CHESS_LINES && <span className="pr-tag">{p.tag}</span>}
                <h3>{p.title}</h3>
                <p>{p.desc}</p>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
