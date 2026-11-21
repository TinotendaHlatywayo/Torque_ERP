import { ChangeEvent, FormEvent, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { Category, Product, Unit } from '@/lib/catalogue'
import { safePublicImageUrl } from '@/lib/publicApi'

type Draft = {
  name: string
  sku: string
  barcode: string
  description: string
  category_id: string
  base_unit_id: string
  selling_price: string
  track_stock: boolean
  is_active: boolean
  reorder_level: string
  public_slug: string
  public_description: string
  is_public: boolean
  public_image_id: string
  public_image_url: string
  public_image_alt: string
}

function draftFrom(product: Product | null): Draft {
  return {
    name: product?.name ?? '',
    sku: product?.sku ?? '',
    barcode: product?.barcode ?? '',
    description: product?.description ?? '',
    category_id: product?.category?.id ?? '',
    base_unit_id: product?.base_unit?.id ?? '',
    selling_price: product?.selling_price ?? '0.00',
    track_stock: product?.track_stock ?? true,
    is_active: product?.is_active ?? true,
    reorder_level: product?.stock.reorder_level ?? '',
    public_slug: product?.public_slug ?? '',
    public_description: product?.public_description ?? '',
    is_public: product?.is_public ?? false,
    public_image_id: product?.public_image_id ?? '',
    public_image_url: product?.public_image_url ?? '',
    public_image_alt: product?.public_image_alt ?? '',
  }
}

export function ProductForm({
  branchId,
  product,
  categories,
  units,
  canManage,
  onSaved,
  onCancel,
}: {
  branchId: string
  product: Product | null
  categories: Category[]
  units: Unit[]
  canManage: boolean
  onSaved: (product: Product) => Promise<void>
  onCancel: () => void
}) {
  const [draft, setDraft] = useState<Draft>(() => draftFrom(product))
  const [feedback, setFeedback] = useState<string | null>(null)
  const [imageUploading, setImageUploading] = useState(false)
  const [uploadedPreviewUrl, setUploadedPreviewUrl] = useState<string | null>(null)

  const categoryOptions = categories.filter((category) => category.is_active || category.id === draft.category_id)
  const unitOptions = units.filter((unit) => unit.is_active || unit.id === draft.base_unit_id)
  const safeImageUrl = safePublicImageUrl(draft.public_image_url)
  const directImageUrl = safeImageUrl?.startsWith('/') ? `${api.defaults.baseURL ?? ''}${safeImageUrl}` : safeImageUrl
  const imagePreviewUrl = uploadedPreviewUrl ?? product?.public_image_preview_url ?? directImageUrl

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: draft.name.trim(),
        sku: draft.sku.trim(),
        barcode: draft.barcode.trim() || null,
        description: draft.description.trim() || null,
        category_id: draft.category_id || null,
        base_unit_id: draft.base_unit_id,
        selling_price: draft.selling_price.trim(),
        track_stock: draft.track_stock,
        is_active: draft.is_active,
        reorder_level: draft.reorder_level.trim() || null,
        public_slug: draft.public_slug.trim() || null,
        public_description: draft.public_description.trim() || null,
        is_public: draft.is_public,
        public_image_id: draft.public_image_id.trim() || null,
        public_image_url: draft.public_image_url.trim() || null,
        public_image_alt: draft.public_image_alt.trim() || null,
      }

      if (product) {
        const response = await api.patch(`/api/branches/${branchId}/products/${product.id}`, payload)
        return response.data.data as Product
      }

      const response = await api.post(`/api/branches/${branchId}/products`, payload)
      return response.data.data as Product
    },
    onSuccess: async (saved) => {
      setFeedback(product ? 'Product updated.' : 'Product created.')
      await onSaved(saved)
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)
    saveMutation.mutate()
  }

  const uploadImage = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return

    const form = new FormData()
    form.append('image', file)
    setImageUploading(true)
    setFeedback(null)

    try {
      const response = await api.post<{ data: { id: string; name: string; preview_url: string } }>(
        `/api/branches/${branchId}/products/media`,
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )
      const uploaded = response.data.data
      setDraft((current) => ({
        ...current,
        public_image_id: uploaded.id,
        public_image_url: '',
        public_image_alt: current.public_image_alt || file.name.replace(/\.[^.]+$/, ''),
      }))
      setUploadedPreviewUrl(uploaded.preview_url)
      setFeedback('Image uploaded and converted to the best WebP/AVIF variant.')
    } catch (error) {
      setFeedback(apiErrorMessage(error))
    } finally {
      setImageUploading(false)
      event.target.value = ''
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">
          {canManage ? (product ? `Edit ${product.name}` : 'Add product') : product?.name ?? 'Product details'}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {feedback && (
          <div role={saveMutation.isError ? 'alert' : 'status'} className="mb-4 rounded-md border bg-muted p-3 text-sm">{feedback}</div>
        )}
        <form onSubmit={submit}>
          <fieldset disabled={!canManage} className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="product-name">Name</Label>
              <Input
                id="product-name"
                value={draft.name}
                onChange={(event) => setDraft({ ...draft, name: event.target.value })}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-sku">SKU</Label>
              <Input
                id="product-sku"
                value={draft.sku}
                onChange={(event) => setDraft({ ...draft, sku: event.target.value })}
                placeholder="OF-100"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-barcode">Barcode</Label>
              <Input
                id="product-barcode"
                value={draft.barcode}
                onChange={(event) => setDraft({ ...draft, barcode: event.target.value })}
                placeholder="Optional, unique in this branch"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-category">Category</Label>
              <select
                id="product-category"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={draft.category_id}
                onChange={(event) => setDraft({ ...draft, category_id: event.target.value })}
              >
                <option value="">Uncategorised</option>
                {categoryOptions.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}{category.is_active ? '' : ' (archived)'}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-base-unit">Base unit</Label>
              <select
                id="product-base-unit"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={draft.base_unit_id}
                onChange={(event) => setDraft({ ...draft, base_unit_id: event.target.value })}
                required
              >
                <option value="">Select a unit</option>
                {unitOptions.map((unit) => (
                  <option key={unit.id} value={unit.id}>
                    {unit.code} — {unit.name}{unit.is_active ? '' : ' (archived)'}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-price">Selling price</Label>
              <Input
                id="product-price"
                type="text"
                inputMode="decimal"
                value={draft.selling_price}
                onChange={(event) => setDraft({ ...draft, selling_price: event.target.value })}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-reorder">Reorder level</Label>
              <Input
                id="product-reorder"
                type="text"
                inputMode="decimal"
                value={draft.reorder_level}
                onChange={(event) => setDraft({ ...draft, reorder_level: event.target.value })}
                placeholder="Optional, in base units"
              />
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="product-description">Description</Label>
              <textarea
                id="product-description"
                className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                value={draft.description}
                onChange={(event) => setDraft({ ...draft, description: event.target.value })}
                placeholder="Optional"
              />
            </div>
            <div className="flex flex-wrap gap-4 text-sm">
              <label className="inline-flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={draft.track_stock}
                  onChange={(event) => setDraft({ ...draft, track_stock: event.target.checked })}
                />
                Track stock
              </label>
              <label className="inline-flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={draft.is_active}
                  onChange={(event) => setDraft({ ...draft, is_active: event.target.checked })}
                />
                Active
              </label>
            </div>
            <div className="space-y-3 rounded-md border bg-muted/20 p-4 md:col-span-2">
              <div>
                <h3 className="text-sm font-semibold">Public catalogue</h3>
                <p className="mt-1 text-xs text-muted-foreground">Only products you publish appear on the public website. Internal notes and stock counts stay private.</p>
              </div>
              <label className="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" checked={draft.is_public} onChange={(event) => setDraft({ ...draft, is_public: event.target.checked })} />
                Publish this product to the website
              </label>
              {draft.is_public && <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2"><Label htmlFor="product-public-slug">Public URL slug</Label><Input id="product-public-slug" value={draft.public_slug} onChange={(event) => setDraft({ ...draft, public_slug: event.target.value.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-|-$/g, '') })} placeholder="front-brake-pads" required /></div>
                <div className="space-y-2 md:col-span-2"><Label htmlFor="product-image-upload">Upload product image from your computer</Label><input id="product-image-upload" type="file" accept="image/jpeg,image/png,image/webp" disabled={!canManage || imageUploading} onChange={(event) => void uploadImage(event)} className="block w-full text-sm text-muted-foreground file:mr-3 file:min-h-10 file:cursor-pointer file:rounded-md file:border file:border-input file:bg-background file:px-3 file:text-sm file:font-medium" /><p className="text-xs text-muted-foreground">{imageUploading ? 'Uploading and converting...' : 'JPG, PNG or WebP up to 8 MB. The server keeps the smallest AVIF/WebP variant for the website.'}</p></div>
                <div className="space-y-2"><Label htmlFor="product-public-image-url">Or product image URL</Label><Input id="product-public-image-url" value={draft.public_image_url} onChange={(event) => { setUploadedPreviewUrl(null); setDraft({ ...draft, public_image_url: event.target.value }) }} placeholder="/images/products/front-brake-pads.svg or https://..." maxLength={500} /></div>
                <div className="space-y-2"><Label htmlFor="product-public-media">Or CMS media ID (optional)</Label><Input id="product-public-media" value={draft.public_image_id} onChange={(event) => { setUploadedPreviewUrl(null); setDraft({ ...draft, public_image_id: event.target.value }) }} placeholder="Upload in Content Studio, then paste its ID" /></div>
                {(draft.public_image_id || draft.public_image_url) && <div className="space-y-2 md:col-span-2"><Label htmlFor="product-public-alt">Image description</Label><Input id="product-public-alt" value={draft.public_image_alt} onChange={(event) => setDraft({ ...draft, public_image_alt: event.target.value })} maxLength={250} required /></div>}
                {imagePreviewUrl && <div className="md:col-span-2"><p className="mb-2 text-xs font-semibold text-muted-foreground">Image preview</p><img src={imagePreviewUrl} alt={draft.public_image_alt || draft.name || 'Product image preview'} className="h-40 w-full rounded-md border bg-white object-contain p-3" /></div>}
                <div className="space-y-2 md:col-span-2"><Label htmlFor="product-public-description">Customer-facing description</Label><textarea id="product-public-description" value={draft.public_description} onChange={(event) => setDraft({ ...draft, public_description: event.target.value })} maxLength={5000} rows={4} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" required /></div>
              </div>}
            </div>
            {canManage && unitOptions.length === 0 && (
              <p className="text-sm text-destructive md:col-span-2">
                Create an active unit of measure before adding products.
              </p>
            )}
          </fieldset>
          <div className="mt-4 flex items-center justify-end gap-2">
            {!canManage && <p className="mr-auto text-sm text-muted-foreground">Read-only catalogue view.</p>}
            <Button type="button" variant="outline" onClick={onCancel}>
              {canManage ? 'Cancel' : 'Close'}
            </Button>
            {canManage && (
              <Button type="submit" disabled={saveMutation.isPending || unitOptions.length === 0}>
                {saveMutation.isPending ? 'Saving...' : product ? 'Save product' : 'Create product'}
              </Button>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
