import { useT } from '../lib/i18n';

/** One Baydaq product: logo (or the pawn mark), name, short description, optional link. */
export default function ProductCard({ product }) {
  const t = useT();

  return (
    <article className="product-card">
      <div className="product-logo" aria-hidden="true">
        {product.logo_url
          ? <img src={product.logo_url} alt="" loading="lazy" />
          : <svg viewBox="0 0 100 100"><use href="#logo-mark" /></svg>}
      </div>
      <h3>{product.name}</h3>
      {product.description && <p>{product.description}</p>}
      {product.url && (
        <a className="product-link" href={product.url} target="_blank" rel="noopener">
          {t('landing.products.visit')} <span aria-hidden="true">↗</span>
        </a>
      )}
    </article>
  );
}
