import { NavLink } from 'react-router-dom';
import { UserRound } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { MODULE_GROUPS } from '../modules.js';

// Item aktif design.md §5: latar brand-blue-50, teks brand-blue-700, garis kiri 3px merah brand.
// Garis merah itu satu-satunya tempat merah brand muncul terus-menerus, dan menandai halaman aktif.
function navClassName({ isActive }) {
  const base =
    'focus-ring relative flex min-h-11 items-center gap-3 rounded-lg px-3 text-body font-medium transition-colors';
  return isActive
    ? `${base} bg-brand-blue-50 text-brand-blue-700 before:absolute before:inset-y-2 before:left-0 before:w-[3px] before:rounded-r before:bg-brand-red-600`
    : `${base} text-gray-800 hover:bg-gray-25`;
}

export default function Sidebar() {
  const { can } = useAuth();

  const groups = MODULE_GROUPS.map((group) => ({
    ...group,
    modules: group.modules.filter((module) => can(module.key)),
  })).filter((group) => group.modules.length > 0);

  return (
    <nav aria-label="Menu utama" className="flex flex-1 flex-col overflow-y-auto px-3 py-4 [scrollbar-width:thin]">
      <div className="flex-1 space-y-4">
        {groups.map((group) => {
          const headingId = `nav-${group.label.replace(/\s+/g, '-').toLowerCase()}`;
          return (
            <div key={group.label}>
              <h2 id={headingId} className="px-3 pb-2 text-caption font-semibold text-gray-600">
                {group.label}
              </h2>
              <ul aria-labelledby={headingId} className="space-y-0.5">
                {group.modules.map(({ key, label, path, icon: Icon, page }) => (
                  <li key={key}>
                    <NavLink to={path} className={navClassName}>
                      <Icon className="h-5 w-5 shrink-0" aria-hidden="true" />
                      <span className="min-w-0 flex-1 truncate">{label}</span>
                      {!page && <span className="text-caption font-normal text-gray-600">Segera</span>}
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          );
        })}
      </div>

      <div className="mt-4 border-t border-gray-200 pt-4">
        <NavLink to="/akun" className={navClassName}>
          <UserRound className="h-5 w-5 shrink-0" aria-hidden="true" />
          <span>Akun saya</span>
        </NavLink>
      </div>
    </nav>
  );
}
