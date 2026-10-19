import { OctagonAlert } from 'lucide-react';

/**
 * Label, hint, dan pesan error untuk satu input (design.md §6.2).
 * `children` adalah fungsi yang menerima atribut id dan aria untuk dipasang ke input:
 *   <Field id="nik" label="NIK" required error={errors.nik}>
 *     {(props) => <input {...props} className="field" />}
 *   </Field>
 */
export default function Field({ id, label, required = false, hint, error, children }) {
  const hintId = `${id}-hint`;
  const errorId = `${id}-error`;

  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-label font-medium text-gray-800">
        {label}
        {required && (
          <span className="text-danger-fg" aria-hidden="true">
            {' '}
            *
          </span>
        )}
      </label>

      {children({
        id,
        'aria-required': required || undefined,
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? errorId : hint ? hintId : undefined,
      })}

      {error ? (
        <p id={errorId} className="mt-1.5 flex items-center gap-1.5 text-caption text-danger-fg">
          <OctagonAlert className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          {error}
        </p>
      ) : (
        hint && (
          <p id={hintId} className="mt-1.5 text-caption text-gray-600">
            {hint}
          </p>
        )
      )}
    </div>
  );
}
