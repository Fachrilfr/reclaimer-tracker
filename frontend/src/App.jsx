import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { WifiOff } from 'lucide-react';
import { AuthProvider, useAuth } from './auth/AuthContext.jsx';
import { MODULES, moduleByKey, moduleForPath } from './modules.js';
import AppLayout from './layouts/AppLayout.jsx';
import FullPageStatus from './components/FullPageStatus.jsx';
import LoginPage from './pages/LoginPage.jsx';
import AccountPage from './pages/AccountPage.jsx';
import ModulePlaceholderPage from './pages/ModulePlaceholderPage.jsx';
import { ForbiddenPage, NotFoundPage } from './pages/StatusPages.jsx';

function homePath(home) {
  return moduleByKey(home)?.path ?? '/akun';
}

// Tampilan bersama saat sesi belum siap: memeriksa token, atau server tidak bisa dihubungi
function SessionPending() {
  const { status, error, retry } = useAuth();

  if (status === 'checking') return <FullPageStatus loading title="Memuat sesi…" />;

  return (
    <FullPageStatus
      icon={WifiOff}
      title="Sesi belum bisa dimuat."
      action={
        <button type="button" onClick={retry} className="btn-primary h-11 px-4 text-body">
          Coba lagi
        </button>
      }
    >
      {error}
    </FullPageStatus>
  );
}

function RequireAuth({ children }) {
  const { status, signedOutManually } = useAuth();
  const location = useLocation();

  if (status === 'checking' || status === 'error') return <SessionPending />;
  if (status === 'signed-out') {
    return <Navigate to="/masuk" replace state={signedOutManually ? undefined : { from: location }} />;
  }
  return children;
}

function LoginRoute() {
  const { status, home, can } = useAuth();
  const location = useLocation();

  if (status === 'checking' || status === 'error') return <SessionPending />;
  if (status === 'signed-out') return <LoginPage />;

  // Kembali ke halaman yang tadi dibuka, kecuali role yang baru masuk tidak boleh membukanya
  const from = location.state?.from;
  const fromModule = from && moduleForPath(from.pathname);
  const canReturn = from && from.pathname !== '/masuk' && (!fromModule || can(fromModule.key));
  return <Navigate to={canReturn ? from : homePath(home)} replace />;
}

function HomeRedirect() {
  const { home } = useAuth();
  return <Navigate to={homePath(home)} replace />;
}

function ModuleRoute({ module }) {
  const { can } = useAuth();

  if (!can(module.key)) return <ForbiddenPage module={module} />;
  const Page = module.page;
  return Page ? <Page /> : <ModulePlaceholderPage module={module} />;
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/masuk" element={<LoginRoute />} />
          <Route
            element={
              <RequireAuth>
                <AppLayout />
              </RequireAuth>
            }
          >
            <Route index element={<HomeRedirect />} />
            {MODULES.map((module) => (
              <Route key={module.key} path={module.path} element={<ModuleRoute module={module} />} />
            ))}
            <Route path="/akun" element={<AccountPage />} />
            <Route path="*" element={<NotFoundPage />} />
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
