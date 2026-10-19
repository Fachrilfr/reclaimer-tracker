import { CircleCheck, Info, OctagonAlert, TriangleAlert } from 'lucide-react';

// Warna dan ikon status design.md §2.3: warna tidak pernah menjadi satu-satunya penanda
const TONES = {
  info: { icon: Info, className: 'border-info-border bg-info-bg text-info-fg' },
  success: { icon: CircleCheck, className: 'border-success-border bg-success-bg text-success-fg' },
  warning: { icon: TriangleAlert, className: 'border-warning-border bg-warning-bg text-warning-fg' },
  danger: { icon: OctagonAlert, className: 'border-danger-border bg-danger-bg text-danger-fg' },
};

export default function Alert({ tone = 'info', title, children, className = '' }) {
  const { icon: Icon, className: toneClassName } = TONES[tone];

  return (
    <div
      role={tone === 'danger' ? 'alert' : 'status'}
      className={`flex items-start gap-2.5 rounded-lg border p-3 text-body ${toneClassName} ${className}`}
    >
      <Icon className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
      <div className="min-w-0">
        {title && <p className="font-semibold">{title}</p>}
        {children}
      </div>
    </div>
  );
}
