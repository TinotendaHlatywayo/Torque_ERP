import { useEffect, useState } from 'react'
import { BoxIcon } from '@/components/public/icons'
import { safePublicImageUrl } from '@/lib/publicApi'
import { cn } from '@/lib/utils'

type ProductPhotoProps = {
  src: string | null | undefined
  alt: string
  className?: string
  imageClassName?: string
  caption?: string
  eager?: boolean
}

export function ProductPhoto({ src, alt, className, imageClassName, caption = 'Product photo', eager = false }: ProductPhotoProps) {
  const [failed, setFailed] = useState(false)
  const imageUrl = safePublicImageUrl(src)

  useEffect(() => {
    setFailed(false)
  }, [src])

  return (
    <div className={cn('relative flex items-center justify-center overflow-hidden bg-white', className)}>
      {imageUrl && !failed ? (
        <img
          src={imageUrl}
          alt={alt}
          loading={eager ? 'eager' : 'lazy'}
          onError={() => setFailed(true)}
          className={cn('h-full w-full object-contain p-4', imageClassName)}
        />
      ) : (
        <div
          role="img"
          aria-label={`${alt} - ${caption} unavailable`}
          className="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-white via-brand-surface to-brand-pale p-4 text-center"
        >
          <span className="flex h-14 w-14 items-center justify-center rounded-full border border-brand-green/20 bg-white text-brand-green-deep shadow-sm">
            <BoxIcon className="h-7 w-7" />
          </span>
          <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-brand-ink/60">{caption}</span>
        </div>
      )}
    </div>
  )
}
