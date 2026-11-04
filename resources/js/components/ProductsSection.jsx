import { useT } from '../lib/i18n';
import ProductCard from './ProductCard';

// The landing page shows the first few; /products lists them all.
const ON_LANDING = 6;

export default function ProductsSection({ products }) {
  const t = useT();

  if (!products.length) {
    return null;
  }

  return (
    <section className="block" id="products" aria-labelledby="products-title">
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">{t('landing.products.eyebrow')}</span>
          <h2 id="products-title">{t('landing.products.title')}</h2>
          <p>{t('landing.products.lede')}</p>
        </div>
        <div className="product-grid">
          {products.slice(0, ON_LANDING).map((product) => <ProductCard key={product.slug} product={product} />)}
        </div>
        {products.length > ON_LANDING && (
          <div className="sec-more">
            <a className="btn btn-line" href="/products">{t('landing.products.all')}</a>
          </div>
        )}
      </div>
    </section>
  );
}
