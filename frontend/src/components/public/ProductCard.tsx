import { Link } from 'react-router-dom'
import { CheckIcon } from '@/components/public/icons'
import { ProductPhoto } from '@/components/public/ProductPhoto'
import { useCart } from '@/hooks/useCart'
import type { ProductSummary } from '@/lib/publicApi'
import { cn } from '@/lib/utils'

type ProductCardProps = {
  product: ProductSummary
  className?: string
}

export function ProductCard({ product, className }: ProductCardProps) {
  const { addItem } = useCart()

  return (
    <article
      className={cn(
        'group flex h-full flex-col overflow-hidden rounded-lg border border-brand-ink/10 bg-white transition-shadow duration-200 hover:border-brand-green/40 hover:shadow-lg motion-reduce:transition-none',
        className,
      )}
    >
      <Link
        to={`/shop/${product.slug}`}
        className="relative block aspect-square focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-inset"
        aria-label={`View ${product.name}`}
      >
        <ProductPhoto
          src={product.image_url}
          alt={product.name}
          caption="Part photo"
          className="h-full w-full border-b border-brand-ink/5"
          imageClassName="p-6 transition-transform duration-300 motion-safe:group-hover:scale-105"
        />
        {product.can_reserve && (
          <span className="absolute left-2 top-2 rounded bg-brand-green-deep px-2 py-1 text-[10px] font-black uppercase tracking-wider text-white shadow-sm">
            Reserve eligible
          </span>
        )}
      </Link>

      <div className="flex flex-1 flex-col gap-2 p-4">
        {product.category_name && (
          <p className="truncate text-[11px] font-bold uppercase tracking-[0.14em] text-brand-green-deep">{product.category_name}</p>
        )}

        <h3 className="line-clamp-2 text-sm font-bold leading-snug text-brand-ink sm:text-[15px]">
          <Link
            to={`/shop/${product.slug}`}
            className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
          >
            {product.name}
          </Link>
        </h3>

        <p className="line-clamp-2 min-h-10 text-xs leading-relaxed text-brand-ink/70">{product.short_description}</p>

        <div className="mt-1 flex items-center gap-1.5 text-[11px] font-bold text-brand-success">
          <CheckIcon className="h-4 w-4" />
          <span>Available to order</span>
        </div>

        <div className="mt-auto flex flex-wrap items-end justify-between gap-2 pt-3">
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-wider text-brand-ink/50">Price</p>
            <p className="text-xl font-black tracking-tight text-brand-ink">{product.price}</p>
          </div>
          <button
            type="button"
            onClick={() => addItem({
              productSlug: product.slug,
              name: product.name,
              sku: product.slug,
              priceValue: product.price_value,
              currencyCode: product.currency_code,
              imageUrl: product.image_url,
              quantity: 1,
              maxStock: null,
            })}
            className="inline-flex min-h-11 items-center rounded-full border border-brand-green-deep bg-brand-pale px-4 text-xs font-bold text-brand-green-deep transition-colors duration-200 hover:bg-brand-green-deep hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
            aria-label={`Add ${product.name} to cart`}
          >
            Add to cart
          </button>
        </div>
      </div>
    </article>
  )
}
