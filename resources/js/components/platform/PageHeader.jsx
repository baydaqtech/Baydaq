/** Title row inside a page: heading, optional description, and actions at the end. */
export default function PageHeader({ title, description, actions, children }) {
  return (
    <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
      <div className="min-w-0">
        {title && <h2 className="text-lg font-semibold text-foreground">{title}</h2>}
        {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
        {children}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}
