import { Suspense, lazy, type ReactNode } from "react"
import { createBrowserRouter, Navigate } from "react-router-dom"
import HomePage from "./pages/public/HomePage"
import ShopPage from "./pages/public/ShopPage"
import ProductPage from "./pages/public/ProductPage"
import AboutPage from "./pages/public/AboutPage"
import ServicesPage from "./pages/public/ServicesPage"
import ContactPage from "./pages/public/ContactPage"
import ReservationPage from "./pages/public/ReservationPage"
import ReservationStatusPage from "./pages/public/ReservationStatusPage"
import CmsPage from "./pages/public/CmsPage"
import NotFoundPage from "./pages/public/NotFoundPage"
import CartPage from "./pages/public/CartPage"
import AccountPage from "./pages/public/AccountPage"
import LoginPage from "./pages/auth/LoginPage"
import SignUpPage from "./pages/auth/SignUpPage"
import PlatformLandingPage from "./pages/platform/LandingPage"
import { ProtectedRoute } from "./components/layout/ProtectedRoute"
import { LoadingState } from "./components/feedback/StateComponents"

const DashboardPage = lazy(() => import("./pages/app/DashboardPage"))
const InventoryIntelligencePage = lazy(() => import("./pages/app/InventoryIntelligencePage"))
const ProductsPage = lazy(() => import("./pages/app/ProductsPage"))
const CustomersPage = lazy(() => import("./pages/app/CustomersPage"))
const StockPage = lazy(() => import("./pages/app/StockPage"))
const PosPage = lazy(() => import("./pages/app/PosPage"))
const ReceiptPage = lazy(() => import("./pages/app/ReceiptPage"))
const SalesHistoryPage = lazy(() => import("./pages/app/SalesHistoryPage"))
const SyncCenterPage = lazy(() => import("./pages/app/SyncCenterPage"))
const ReservationsPage = lazy(() => import("./pages/app/ReservationsPage"))
const UsersPage = lazy(() => import("./pages/app/UsersPage"))
const EmployeesPage = lazy(() => import("./pages/app/EmployeesPage"))
const HrRecordsPage = lazy(() => import("./pages/app/HrRecordsPage"))
const PayrollPage = lazy(() => import("./pages/app/PayrollPage"))
const TasksPage = lazy(() => import("./pages/app/TasksPage"))
const JobsPage = lazy(() => import("./pages/app/JobsPage"))
const TransfersPage = lazy(() => import("./pages/app/TransfersPage"))
const CatalogueSearchPage = lazy(() => import("./pages/app/CatalogueSearchPage"))
const LoyaltyPage = lazy(() => import("./pages/app/LoyaltyPage"))
const AssetsPage = lazy(() => import("./pages/app/AssetsPage"))
const AuditLogPage = lazy(() => import("./pages/app/AuditLogPage"))
const PlatformAuditLogPage = lazy(() => import("./pages/platform/AuditLogPage"))
const ContentPage = lazy(() => import("./pages/platform/ContentPage"))

function routeLoading() {
  return <LoadingState text="Loading page..." />
}

function withSuspense(node: ReactNode) {
  return <Suspense fallback={routeLoading()}>{node}</Suspense>
}

function protectedPage(node: ReactNode, options?: { platformOnly?: boolean; platformPermission?: string }) {
  return <ProtectedRoute {...options}>{withSuspense(node)}</ProtectedRoute>
}

export const router = createBrowserRouter([
  {
    path: "/",
    element: <HomePage />,
  },
  {
    path: "/about",
    element: <AboutPage />,
  },
  {
    path: "/services",
    element: <ServicesPage />,
  },
  {
    path: "/contact",
    element: <ContactPage />,
  },
  {
    path: "/products",
    element: <ShopPage />,
  },
  {
    path: "/products/category/:categorySlug",
    element: <ShopPage />,
  },
  {
    path: "/shop",
    element: <ShopPage />,
  },
  {
    path: "/shop/category/:categorySlug",
    element: <ShopPage />,
  },
  {
    path: "/shop/:slug",
    element: <ProductPage />,
  },
  {
    path: "/reserve/:slug",
    element: <ReservationPage />,
  },
  {
    path: "/reservation/:token",
    element: <ReservationStatusPage />,
  },
  {
    path: "/pages/:slug",
    element: <CmsPage />,
  },
  {
    path: "/cart",
    element: <CartPage />,
  },
  {
    path: "/account",
    element: <AccountPage />,
  },
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    path: "/signup",
    element: <SignUpPage />,
  },
  {
    path: "/app",
    element: protectedPage(<DashboardPage />),
  },
  {
    path: "/app/inventory-intelligence",
    element: protectedPage(<InventoryIntelligencePage />),
  },
  {
    path: "/app/products",
    element: protectedPage(<ProductsPage />),
  },
  {
    path: "/app/catalogue-search",
    element: protectedPage(<CatalogueSearchPage />),
  },
  {
    path: "/app/customers",
    element: protectedPage(<CustomersPage />),
  },
  {
    path: "/app/stock",
    element: protectedPage(<StockPage />),
  },
  {
    path: "/app/pos",
    element: protectedPage(<PosPage />),
  },
  {
    path: "/app/sales/:saleId/receipt",
    element: protectedPage(<ReceiptPage />),
  },
  {
    path: "/app/sales",
    element: protectedPage(<SalesHistoryPage />),
  },
  {
    path: "/app/reservations",
    element: protectedPage(<ReservationsPage />),
  },
  {
    path: "/app/sync",
    element: protectedPage(<SyncCenterPage />),
  },
  {
    path: "/app/users",
    element: protectedPage(<UsersPage />),
  },
  {
    path: "/app/employees",
    element: protectedPage(<EmployeesPage />),
  },
  {
    path: "/app/hr",
    element: protectedPage(<HrRecordsPage />),
  },
  {
    path: "/app/payroll",
    element: protectedPage(<PayrollPage />),
  },
  {
    path: "/app/tasks",
    element: protectedPage(<TasksPage />),
  },
  {
    path: "/app/jobs",
    element: protectedPage(<JobsPage />),
  },
  {
    path: "/app/transfers",
    element: protectedPage(<TransfersPage />),
  },
  {
    path: "/app/loyalty",
    element: protectedPage(<LoyaltyPage />),
  },
  {
    path: "/app/assets",
    element: protectedPage(<AssetsPage />),
  },
  {
    path: "/app/audit",
    element: protectedPage(<AuditLogPage />),
  },
  {
    path: "/app/*",
    element: <Navigate to="/app" replace />,
  },
  {
    path: "/platform",
    element: protectedPage(<PlatformLandingPage />, { platformOnly: true }),
  },
  {
    path: "/platform/content",
    element: protectedPage(<ContentPage />, { platformPermission: "platform.content.manage" }),
  },
  {
    path: "/platform/audit",
    element: protectedPage(<PlatformAuditLogPage />, { platformPermission: "platform.audit.view" }),
  },
  {
    path: "*",
    element: <NotFoundPage />,
  },
])
