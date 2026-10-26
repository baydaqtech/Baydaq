import ProductCard from '../components/ProductCard';
import SitePage from '../components/SitePage';
import { useT } from '../lib/i18n';

export default function Products({ products }) {
  const t = useT();

  return (
    <SitePage title={t('landing.pages.products.title')} lede={t('landing.pages.products.lede')}>
      <div className="product-grid">
        {products.map((product) => <ProductCard key={product.slug} product={product} />)}
      </div>
      <div className="page-cta">
        <div>
          <h2>{t('landing.pages.products.cta_title')}</h2>
          <p>{t('landing.pages.products.cta_text')}</p>
        </div>
        <div className="page-cta-btns">
          <a className="btn btn-gold" href="/request/service">{t('landing.hero.cta_service')}</a>
          <a className="btn btn-line" href="/request/partnership">{t('landing.hero.cta_partnership')}</a>
        </div>
      </div>
    </SitePage>
  );
}
