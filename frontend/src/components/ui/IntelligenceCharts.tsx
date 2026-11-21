import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { DashboardV2Data, DataQuality } from '@/lib/dashboard'

const classLabels: Record<string, string> = {
  active: 'Active',
  slow_moving: 'Slow moving',
  dead_stock: 'Dead stock',
  no_stock: 'No stock',
  unknown: 'No movement history',
  A: 'A - high contribution',
  B: 'B - medium contribution',
  C: 'C - low contribution',
}

function qualityNotice(quality: DataQuality, id: string) {
  return (
    <div
      id={id}
      role="status"
      className={
        quality.status === 'WARNING'
          ? 'mt-3 rounded border border-amber-400 bg-amber-50 p-3 text-sm font-medium text-amber-900'
          : 'mt-3 rounded border border-emerald-300 bg-emerald-50 p-3 text-sm font-medium text-emerald-900'
      }
    >
      <p className="font-bold">Data quality: {quality.status}</p>
      {quality.warnings.length > 0 && (
        <ul className="mt-1 list-disc pl-5">
          {quality.warnings.map((warning) => (
            <li key={`${warning.section}-${warning.code}`}>
              {warning.section}: {warning.message}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function bar(value: number, max: number, label: string) {
  const percent = max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0
  return (
    <div role="img" aria-label={label} className="h-2 w-full overflow-hidden rounded bg-slate-200">
      <div aria-hidden="true" className="h-2 rounded bg-slate-800" style={{ width: `${percent}%` }} />
    </div>
  )
}

export function IntelligenceCharts({ data }: { data: DashboardV2Data }) {
  const intelligence = data.inventory_intelligence
  const reorder = intelligence?.reorder
  const ageing = intelligence?.ageing
  const abc = intelligence?.abc
  const forecast = data.cash_forecast
  const maxSuggested = Math.max(0, ...(reorder?.suggestions.map((item) => item.suggested_order_units) ?? [0]))
  const maxBucketValue = Math.max(0, ...Object.values(ageing?.buckets ?? {}).map((bucket) => bucket.stock_value_minor))
  const maxAbcRevenue = Math.max(0, ...Object.values(abc?.summary ?? {}).map((group) => group.revenue_minor))

  return (
    <section aria-labelledby="intelligence-heading" className="space-y-6">
      <div>
        <h2 id="intelligence-heading" className="text-2xl font-bold text-foreground">
          Inventory intelligence and cash forecast
        </h2>
        <p className="mt-1 text-sm font-medium text-muted-foreground">
          Shared branch metrics as of {data.as_of}. All values are in {data.currency_code} minor units unless stated.
        </p>
        {qualityNotice(data.data_quality, 'overall-data-quality')}
      </div>

      {reorder && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg font-bold text-foreground">Reorder suggestions</CardTitle>
            <p className="text-sm font-medium text-muted-foreground">
              Average daily sales over {reorder.sales_window_days} days; reorder point = average daily sales x (lead time {reorder.lead_time_days} + cover {reorder.days_of_cover} days). Available = on hand minus reserved.
            </p>
          </CardHeader>
          <CardContent>
            {reorder.suggestions.length === 0 ? (
              <p className="text-sm font-medium text-muted-foreground">No products have sufficient sales history for a suggestion.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm text-foreground">
                  <caption className="mb-2 text-left text-sm font-semibold text-foreground">
                    Suggested purchase quantities, highest first
                  </caption>
                  <thead>
                    <tr className="border-b border-slate-400 text-left">
                      <th scope="col" className="p-2">Product</th>
                      <th scope="col" className="p-2">Available</th>
                      <th scope="col" className="p-2">Average daily sales</th>
                      <th scope="col" className="p-2">Reorder point</th>
                      <th scope="col" className="p-2">Suggested order</th>
                      <th scope="col" className="p-2">Urgency</th>
                    </tr>
                  </thead>
                  <tbody>
                    {reorder.suggestions.map((item) => (
                      <tr key={item.product_id} className="border-b border-slate-200">
                        <td className="p-2 font-semibold">{item.sku} - {item.name}</td>
                        <td className="p-2">{item.quantity_available} {item.unit_code}</td>
                        <td className="p-2">{item.average_daily_sales_units}</td>
                        <td className="p-2">{item.reorder_point_units}</td>
                        <td className="p-2">
                          <span className="font-bold">{item.suggested_order_units} {item.unit_code}</span>
                          {bar(item.suggested_order_units, maxSuggested, `Suggestion for ${item.sku}: ${item.suggested_order_units} units`)}
                        </td>
                        <td className="p-2">{item.urgency === 'reorder_now' ? 'Reorder now' : 'Plan reorder'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {qualityNotice(reorder.data_quality, 'reorder-data-quality')}
          </CardContent>
        </Card>
      )}

      {ageing && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg font-bold text-foreground">Stock ageing and dead stock</CardTitle>
            <p className="text-sm font-medium text-muted-foreground">
              Based on the last canonical stock movement date. Slow moving at {ageing.slow_moving_threshold_days}+ days; dead stock at {ageing.dead_stock_threshold_days}+ days.
            </p>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-sm text-foreground">
                <caption className="mb-2 text-left text-sm font-semibold text-foreground">
                  Ageing buckets by product count and stock value
                </caption>
                <thead>
                  <tr className="border-b border-slate-400 text-left">
                    <th scope="col" className="p-2">Classification</th>
                    <th scope="col" className="p-2">Products</th>
                    <th scope="col" className="p-2">Stock value</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(ageing.buckets).map(([key, bucket]) => (
                    <tr key={key} className="border-b border-slate-200">
                      <th scope="row" className="p-2 text-left">{classLabels[key] ?? key}</th>
                      <td className="p-2">{bucket.product_count}</td>
                      <td className="p-2">
                        <span className="font-bold">{bucket.stock_value_minor}</span>
                        {bar(bucket.stock_value_minor, maxBucketValue, `${classLabels[key] ?? key}: ${bucket.stock_value_minor} minor units`)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {ageing.items.length > 0 && (
              <details className="mt-4 text-sm text-foreground">
                <summary className="cursor-pointer font-semibold">Show product-level ageing</summary>
                <div className="overflow-x-auto">
                  <table className="mt-2 w-full border-collapse">
                    <caption className="mb-2 text-left font-semibold">Product-level ageing detail</caption>
                    <thead>
                      <tr className="border-b border-slate-400 text-left">
                        <th scope="col" className="p-2">Product</th>
                        <th scope="col" className="p-2">Age (days)</th>
                        <th scope="col" className="p-2">Classification</th>
                      </tr>
                    </thead>
                    <tbody>
                      {ageing.items.map((item) => (
                        <tr key={item.product_id} className="border-b border-slate-200">
                          <td className="p-2">{item.sku} - {item.name}</td>
                          <td className="p-2">{item.age_days ?? 'Not available'}</td>
                          <td className="p-2">{item.classification ? (classLabels[item.classification] ?? item.classification) : 'Unknown - no movement history'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </details>
            )}
            {qualityNotice(ageing.data_quality, 'ageing-data-quality')}
          </CardContent>
        </Card>
      )}

      {abc && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg font-bold text-foreground">ABC analysis</CardTitle>
            <p className="text-sm font-medium text-muted-foreground">
              Revenue contribution over {abc.sales_window_days} days. A: cumulative share up to {abc.a_cumulative_contribution_percent}%, B: up to {abc.b_cumulative_contribution_percent}%, C: the remainder.
            </p>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-sm text-foreground">
                <caption className="mb-2 text-left font-semibold text-foreground">
                  ABC classes from {abc.total_revenue_minor} minor units of posted sales
                </caption>
                <thead>
                  <tr className="border-b border-slate-400 text-left">
                    <th scope="col" className="p-2">Class</th>
                    <th scope="col" className="p-2">Products</th>
                    <th scope="col" className="p-2">Revenue</th>
                    <th scope="col" className="p-2">Share</th>
                  </tr>
                </thead>
                <tbody>
                  {['A', 'B', 'C'].map((key) => {
                    const group = abc.summary[key] ?? { product_count: 0, revenue_minor: 0, share_percent: 0 }
                    return (
                      <tr key={key} className="border-b border-slate-200">
                        <th scope="row" className="p-2 text-left">{classLabels[key]}</th>
                        <td className="p-2">{group.product_count}</td>
                        <td className="p-2">{group.revenue_minor}</td>
                        <td className="p-2">
                          <span className="font-bold">{group.share_percent}%</span>
                          {bar(group.revenue_minor, maxAbcRevenue, `Class ${key}: ${group.revenue_minor} minor units`)}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            {qualityNotice(abc.data_quality, 'abc-data-quality')}
          </CardContent>
        </Card>
      )}

      {forecast && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg font-bold text-foreground">
              Cash forecast <span className="rounded bg-slate-800 px-2 py-1 text-xs font-bold text-white">{forecast.label}</span>
            </CardTitle>
            <p className="text-sm font-medium text-muted-foreground">
              Illustrative only. {forecast.history_window_days}-day history extrapolated over {forecast.horizon_days} days. Not an authoritative finance statement.
            </p>
          </CardHeader>
          <CardContent>
            <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div><dt className="text-sm font-semibold text-muted-foreground">Opening cash</dt><dd className="text-lg font-bold text-foreground">{forecast.beginning_cash_minor}</dd></div>
              <div><dt className="text-sm font-semibold text-muted-foreground">Average daily inflow</dt><dd className="text-lg font-bold text-foreground">{forecast.average_daily_inflow_minor ?? 'Not available'}</dd></div>
              <div><dt className="text-sm font-semibold text-muted-foreground">Average daily outflow</dt><dd className="text-lg font-bold text-foreground">{forecast.average_daily_outflow_minor ?? 'Not available'}</dd></div>
              <div><dt className="text-sm font-semibold text-muted-foreground">Forecast closing cash</dt><dd className="text-lg font-bold text-foreground">{forecast.forecast_cash_position_minor ?? 'Not available'}</dd></div>
              <div><dt className="text-sm font-semibold text-muted-foreground">Known bills due in horizon</dt><dd className="text-lg font-bold text-foreground">{forecast.known_bills_due_minor}</dd></div>
              <div><dt className="text-sm font-semibold text-muted-foreground">Illustrative cash target</dt><dd className="text-lg font-bold text-foreground">{forecast.cash_target_minor}</dd></div>
            </dl>
            {forecast.series.length > 0 && (
              <details className="mt-4 text-sm text-foreground">
                <summary className="cursor-pointer font-semibold">Show daily projection</summary>
                <div className="overflow-x-auto">
                  <table className="mt-2 w-full border-collapse">
                    <caption className="mb-2 text-left font-semibold">Straight-line projected cash by day</caption>
                    <thead>
                      <tr className="border-b border-slate-400 text-left">
                        <th scope="col" className="p-2">Day</th>
                        <th scope="col" className="p-2">Projected cash</th>
                      </tr>
                    </thead>
                    <tbody>
                      {forecast.series.map((point) => (
                        <tr key={point.day} className="border-b border-slate-200">
                          <td className="p-2">{point.day}</td>
                          <td className="p-2">{point.projected_cash_minor}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </details>
            )}
            <div className="mt-4 text-sm text-foreground">
              <p className="font-bold">Documented assumptions</p>
              <ul className="mt-1 list-disc pl-5">
                {forecast.assumptions.map((assumption) => <li key={assumption}>{assumption}</li>)}
              </ul>
            </div>
            {qualityNotice(forecast.data_quality, 'forecast-data-quality')}
          </CardContent>
        </Card>
      )}

      {!intelligence && !forecast && (
        <p className="rounded border border-slate-400 bg-white p-4 text-sm font-medium text-slate-900">
          Your role does not include inventory or finance view permissions for these charts.
        </p>
      )}
    </section>
  )
}
