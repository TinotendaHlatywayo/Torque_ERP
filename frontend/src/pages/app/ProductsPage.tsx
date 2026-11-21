import { useDeferredValue, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { CategoryManager } from '@/components/catalogue/CategoryManager'
import { ProductForm } from '@/components/catalogue/ProductForm'
import { ProductUnitsPanel } from '@/components/catalogue/ProductUnitsPanel'
import { UnitManager } from '@/components/catalogue/UnitManager'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type {
  Category,
  CategoryListResponse,
  PaginatedProducts,
  Product,
  Unit,
  UnitListResponse,
} from '@/lib/catalogue'

type Tab = 'products' | 'categories' | 'units'

type Feedback = { type: 'error' | 'success'; text: string }

export default function ProductsPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.inventory.view') ?? false
  const canManage = branch?.permissions.includes('branch.inventory.manage') ?? false

  const [tab, setTab] = useState<Tab>('products')
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const deferredSearch = useDeferredValue(search)
  const [status, setStatus] = useState('active')
  const [categoryId, setCategoryId] = useState('')
  const [sort, setSort] = useState('name')
  const [direction, setDirection] = useState('asc')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)

  const refreshAll = async () => {
    await queryClient.invalidateQueries({ queryKey: ['catalogue', branchId] })
  }

  const productsQuery = useQuery({
    queryKey: [
      'catalogue',
      branchId,
      'products',
      { search: deferredSearch, status, categoryId, sort, direction, page, perPage },
    ],
    queryFn: async () => {
      const response = await api.get<PaginatedProducts>(`/api/branches/${branchId}/products`, {
        params: {
          q: deferredSearch || undefined,
          status,
          category_id: categoryId || undefined,
          sort,
          direction,
          page,
          per_page: perPage,
        },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const categoriesQuery = useQuery({
    queryKey: ['catalogue', branchId, 'categories'],
    queryFn: async () => {
      const response = await api.get<CategoryListResponse>(`/api/branches/${branchId}/categories`, {
        params: { status: 'all' },
      })
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const unitsQuery = useQuery({
    queryKey: ['catalogue', branchId, 'units'],
    queryFn: async () => {
      const response = await api.get<UnitListResponse>(`/api/branches/${branchId}/units`, {
        params: { status: 'all' },
      })
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const productDetailQuery = useQuery({
    queryKey: ['catalogue', branchId, 'product', editingId],
    queryFn: async () => {
      const response = await api.get<{ data: Product }>(`/api/branches/${branchId}/products/${editingId}`)
      return response.data.data
    },
    enabled: Boolean(branchId && editingId && canView),
  })

  const archiveMutation = useMutation({
    mutationFn: async (product: Product) => {
      if (product.is_active) {
        await api.delete(`/api/branches/${branchId}/products/${product.id}`)
      } else {
        await api.patch(`/api/branches/${branchId}/products/${product.id}`, { is_active: true })
      }
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Product status updated.' })
      await refreshAll()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const closeForm = () => {
    setShowForm(false)
    setEditingId(null)
  }

  const openCreate = () => {
    setFeedback(null)
    setEditingId(null)
    setShowForm(true)
  }

  const openEdit = (product: Product) => {
    setFeedback(null)
    setEditingId(product.id)
    setShowForm(true)
  }

  if (!meQuery.data) {
    return (
      <AppShell>
        <LoadingState text="Loading branch access..." />
      </AppShell>
    )
  }

  if (!branch) {
    return (
      <AppShell>
        <PermissionDeniedState
          title="No branch membership"
          description="Your account is active but is not assigned to a branch. Ask a platform administrator for access."
        />
      </AppShell>
    )
  }

  if (!canView) {
    return (
      <AppShell>
        <PermissionDeniedState description="Your role does not include permission to view the product catalogue." />
      </AppShell>
    )
  }

  const categories: Category[] = categoriesQuery.data ?? []
  const units: Unit[] = unitsQuery.data ?? []
  const products = productsQuery.data?.data ?? []
  const meta = productsQuery.data?.meta

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Products</h1>
            <p className="text-muted-foreground">
              {branch.name} ({branch.code}) — catalogue and unit conversions
            </p>
          </div>
          {canManage && tab === 'products' && (
            <Button onClick={openCreate}>Add product</Button>
          )}
        </div>

        <div className="flex flex-wrap gap-2 border-b pb-2" role="tablist" aria-label="Catalogue sections">
          {(['products', 'categories', 'units'] as Tab[]).map((value) => (
            <Button
              key={value}
              role="tab"
              aria-selected={tab === value}
              variant={tab === value ? 'default' : 'ghost'}
              size="sm"
              onClick={() => {
                setTab(value)
                setFeedback(null)
                if (value !== 'products') {
                  setShowForm(false)
                  setEditingId(null)
                }
              }}
            >
              {value === 'products' ? 'Products' : value === 'categories' ? 'Categories' : 'Units'}
            </Button>
          ))}
        </div>

        {feedback && (
          <div
            role={feedback.type === 'error' ? 'alert' : 'status'}
            className={
              feedback.type === 'error'
                ? 'rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive'
                : 'rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-700'
            }
          >
            {feedback.text}
          </div>
        )}

        {(categoriesQuery.isError || unitsQuery.isError) && (
          <ErrorState
            title="Catalogue references could not be loaded"
            description={apiErrorMessage(categoriesQuery.error ?? unitsQuery.error)}
            retry={() => {
              void categoriesQuery.refetch()
              void unitsQuery.refetch()
            }}
          />
        )}

        {tab === 'categories' && (
          <CategoryManager
            branchId={branch.id}
            categories={categories}
            loading={categoriesQuery.isLoading}
            canManage={canManage}
            onChanged={refreshAll}
          />
        )}

        {tab === 'units' && (
          <UnitManager
            branchId={branch.id}
            units={units}
            loading={unitsQuery.isLoading}
            canManage={canManage}
            onChanged={refreshAll}
          />
        )}

        {tab === 'products' && (
          <div className="space-y-4">
            {showForm && !editingId && (
              <ProductForm
                key="new-product"
                branchId={branch.id}
                product={null}
                categories={categories}
                units={units}
                canManage={canManage}
                onSaved={async (saved) => {
                  setFeedback({ type: 'success', text: `${saved.name} created.` })
                  closeForm()
                  await refreshAll()
                }}
                onCancel={closeForm}
              />
            )}

            {showForm && editingId && productDetailQuery.isLoading && (
              <Card>
                <CardContent>
                  <LoadingState text="Loading product..." />
                </CardContent>
              </Card>
            )}

            {showForm && editingId && productDetailQuery.isError && (
              <ErrorState
                title="Product could not be loaded"
                description={apiErrorMessage(productDetailQuery.error)}
                retry={() => void productDetailQuery.refetch()}
              />
            )}

            {showForm && editingId && productDetailQuery.data && (
              <>
                <ProductForm
                  key={`product-form-${productDetailQuery.data.id}`}
                  branchId={branch.id}
                  product={productDetailQuery.data}
                  categories={categories}
                  units={units}
                  canManage={canManage}
                  onSaved={async (saved) => {
                    setFeedback({ type: 'success', text: `${saved.name} updated.` })
                    await refreshAll()
                  }}
                  onCancel={closeForm}
                />
                <ProductUnitsPanel
                  key={`product-units-${productDetailQuery.data.id}`}
                  branchId={branch.id}
                  product={productDetailQuery.data}
                  units={units}
                  canManage={canManage}
                  onChanged={refreshAll}
                />
              </>
            )}

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Catalogue</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="mb-4 grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                  <div className="space-y-1 lg:col-span-2">
                    <Label htmlFor="product-search">Search</Label>
                    <Input
                      id="product-search"
                      value={search}
                      onChange={(event) => {
                        setSearch(event.target.value)
                        setPage(1)
                      }}
                      placeholder="SKU, name, or exact barcode"
                    />
                  </div>
                  <div className="space-y-1">
                    <Label htmlFor="product-status">Status</Label>
                    <select
                      id="product-status"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={status}
                      onChange={(event) => {
                        setStatus(event.target.value)
                        setPage(1)
                      }}
                    >
                      <option value="active">Active</option>
                      <option value="inactive">Archived</option>
                      <option value="all">All</option>
                    </select>
                  </div>
                  <div className="space-y-1">
                    <Label htmlFor="product-category-filter">Category</Label>
                    <select
                      id="product-category-filter"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={categoryId}
                      onChange={(event) => {
                        setCategoryId(event.target.value)
                        setPage(1)
                      }}
                    >
                      <option value="">All categories</option>
                      {categories.map((category) => (
                        <option key={category.id} value={category.id}>{category.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-1">
                    <Label htmlFor="product-sort">Sort</Label>
                    <select
                      id="product-sort"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={`${sort}:${direction}`}
                      onChange={(event) => {
                        const [nextSort, nextDirection] = event.target.value.split(':')
                        setSort(nextSort)
                        setDirection(nextDirection)
                        setPage(1)
                      }}
                    >
                      <option value="name:asc">Name A–Z</option>
                      <option value="name:desc">Name Z–A</option>
                      <option value="sku:asc">SKU A–Z</option>
                      <option value="price:asc">Price low–high</option>
                      <option value="price:desc">Price high–low</option>
                      <option value="updated_at:desc">Recently updated</option>
                    </select>
                  </div>
                </div>

                {productsQuery.isLoading && <LoadingState text="Loading products..." />}

                {productsQuery.isError && (
                  <ErrorState
                    title="Products could not be loaded"
                    description={apiErrorMessage(productsQuery.error)}
                    retry={() => void productsQuery.refetch()}
                  />
                )}

                {!productsQuery.isLoading && !productsQuery.isError && products.length === 0 && (
                  <EmptyState
                    title="No products found"
                    description="Adjust the filters or create the first product in this branch."
                    action={canManage ? <Button onClick={openCreate}>Add product</Button> : undefined}
                  />
                )}

                {!productsQuery.isLoading && !productsQuery.isError && products.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                      <thead className="border-b text-muted-foreground">
                        <tr>
                          <th className="py-3 pr-4 font-medium" scope="col">Product</th>
                          <th className="py-3 pr-4 font-medium" scope="col">Category</th>
                          <th className="py-3 pr-4 font-medium" scope="col">Unit</th>
                          <th className="py-3 pr-4 font-medium" scope="col">Price</th>
                          <th className="py-3 pr-4 font-medium" scope="col">Stock</th>
                          <th className="py-3 pr-4 font-medium" scope="col">Status</th>
                          <th className="py-3 text-right font-medium" scope="col">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {products.map((product) => (
                          <tr key={product.id} className="border-b last:border-0">
                            <td className="py-3 pr-4">
                              <div className="font-medium">{product.name}</div>
                              <div className="text-xs text-muted-foreground">
                                {product.sku}{product.barcode ? ` · ${product.barcode}` : ''}
                              </div>
                            </td>
                            <td className="py-3 pr-4 text-muted-foreground">{product.category?.name ?? '—'}</td>
                            <td className="py-3 pr-4">
                              {product.base_unit?.code ?? '—'}
                              {product.alternate_unit_count > 0 && (
                                <span className="ml-1 text-xs text-muted-foreground">
                                  +{product.alternate_unit_count}
                                </span>
                              )}
                            </td>
                            <td className="py-3 pr-4">{product.selling_price} {product.currency_code}</td>
                            <td className="py-3 pr-4">
                              <span className={product.stock.is_low ? 'font-medium text-destructive' : ''}>
                                {product.stock.quantity_on_hand}
                              </span>
                              {product.stock.is_low && <span className="ml-1 text-xs text-destructive">Low</span>}
                            </td>
                            <td className="py-3 pr-4">{product.is_active ? 'Active' : 'Archived'}</td>
                            <td className="space-x-2 whitespace-nowrap py-3 text-right">
                              <Button size="sm" variant="outline" onClick={() => openEdit(product)}>
                                {canManage ? 'Edit' : 'View'}
                              </Button>
                              {canManage && (
                                <Button
                                  size="sm"
                                  variant={product.is_active ? 'destructive' : 'secondary'}
                                  disabled={archiveMutation.isPending}
                                  onClick={() => {
                                    if (product.is_active && !window.confirm(`Archive ${product.name}?`)) return
                                    archiveMutation.mutate(product)
                                  }}
                                >
                                  {product.is_active ? 'Archive' : 'Activate'}
                                </Button>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                {meta && meta.total > 0 && (
                  <div className="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <p className="text-sm text-muted-foreground">
                      Page {meta.current_page} of {meta.last_page} · {meta.total} products
                    </p>
                    <div className="flex items-center gap-2">
                      <select
                        aria-label="Products per page"
                        className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                        value={perPage}
                        onChange={(event) => {
                          setPerPage(Number(event.target.value))
                          setPage(1)
                        }}
                      >
                        <option value={10}>10 per page</option>
                        <option value={25}>25 per page</option>
                        <option value={50}>50 per page</option>
                        <option value={100}>100 per page</option>
                      </select>
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={meta.current_page <= 1}
                        onClick={() => setPage((current) => Math.max(1, current - 1))}
                      >
                        Previous
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={meta.current_page >= meta.last_page}
                        onClick={() => setPage((current) => current + 1)}
                      >
                        Next
                      </Button>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </AppShell>
  )
}
