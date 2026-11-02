import { createBrowserRouter, Navigate } from "react-router-dom"
import HomePage from "./pages/public/HomePage"
import LoginPage from "./pages/auth/LoginPage"
import DashboardPage from "./pages/app/DashboardPage"
import ProductsPage from "./pages/app/ProductsPage"
import CustomersPage from "./pages/app/CustomersPage"
import StockPage from "./pages/app/StockPage"
import PosPage from "./pages/app/PosPage"
import ReceiptPage from "./pages/app/ReceiptPage"
import SalesHistoryPage from "./pages/app/SalesHistoryPage"
import SyncCenterPage from "./pages/app/SyncCenterPage"
import UsersPage from "./pages/app/UsersPage"
import PlatformLandingPage from "./pages/platform/LandingPage"
import { ProtectedRoute } from "./components/layout/ProtectedRoute"

export const router = createBrowserRouter([
  {
    path: "/",
    element: <HomePage />,
  },
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    path: "/app",
    element: <ProtectedRoute><DashboardPage /></ProtectedRoute>,
  },
  {
    path: "/app/products",
    element: <ProtectedRoute><ProductsPage /></ProtectedRoute>,
  },
  {
    path: "/app/customers",
    element: <ProtectedRoute><CustomersPage /></ProtectedRoute>,
  },
  {
    path: "/app/stock",
    element: <ProtectedRoute><StockPage /></ProtectedRoute>,
  },
  {
    path: "/app/pos",
    element: <ProtectedRoute><PosPage /></ProtectedRoute>,
  },
  {
    path: "/app/sales/:saleId/receipt",
    element: <ProtectedRoute><ReceiptPage /></ProtectedRoute>,
  },
  {
    path: "/app/sales",
    element: <ProtectedRoute><SalesHistoryPage /></ProtectedRoute>,
  },
  {
    path: "/app/sync",
    element: <ProtectedRoute><SyncCenterPage /></ProtectedRoute>,
  },
  {
    path: "/app/users",
    element: <ProtectedRoute><UsersPage /></ProtectedRoute>,
  },
  {
    path: "/app/*",
    element: <Navigate to="/app" replace />,
  },
  {
    path: "/platform",
    element: <PlatformLandingPage />,
  },
  {
    path: "*",
    element: <Navigate to="/" replace />,
  },
])
