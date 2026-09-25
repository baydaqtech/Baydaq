import { TECH_LOGOS } from '../lib/techLogos';

export default function TechStrip() {
  return (
    <div className="stack">
      <div className="wrap">
        <p>التقنيات التي نستخدمها</p>
        <ul aria-label="التقنيات التي نعمل بها" dangerouslySetInnerHTML={{ __html: TECH_LOGOS }} />
      </div>
    </div>
  );
}
