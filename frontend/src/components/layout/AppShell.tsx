import { ReactNode } from "react"

export function AppShell({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen bg-background">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:bg-background focus:border">
        Skip to main content
      </a>
      <header className="border-b">
        <nav className="container mx-auto px-4 py-4 flex items-center justify-between" aria-label="App navigation">
          <div className="text-lg font-semibold">TorqueERP</div>
          <div className="text-sm text-muted-foreground">Branch Workspace</div>
        </nav>
      </header>
      <div className="container mx-auto px-4 py-6 flex flex-col md:flex-row gap-6">
        <aside className="w-full md:w-64 border rounded-lg p-4" aria-label="Sidebar">
          <nav className="space-y-2">
            <a href="/app" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Dashboard</a>
            <a href="/app/products" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Products</a>
            <a href="/app/customers" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Customers</a>
            <a href="/app/stock" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Stock</a>
            <a href="/app/pos" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">POS</a>
            <a href="/app/sales" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Sales history</a>
            <a href="/app/sync" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Sync center</a>
            <a href="/app/users" className="block px-2 py-1 rounded hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Users and roles</a>
          </nav>
        </aside>
        <main id="main-content" className="flex-1" tabIndex={-1}>
          {children}
        </main>
      </div>
    </div>
  )
}
