import { useEffect, useState } from 'react';
import { LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom, useResource } from '../lib/useResource.js';
import { formatDateTime, formatNumber, parseDecimal } from '../lib/format.js';
import PageHeader from '../components/PageHeader.jsx';
import Field from '../components/Field.jsx';
import Alert from '../components/Alert.jsx';
import { ErrorCard, LoadingCard } from '../components/LoadState.jsx';

// Nilai di form ditulis dengan koma desimal, seperti input lain di aplikasi
const toText = (item) => (item.integer ? String(item.value) : formatNumber(item.value, 1));

function draftFrom(groups) {
  return Object.fromEntries(groups.flatMap((g) => g.items.map((item) => [item.key, toText(item)])));
}

function ParameterForm({ groups, canEdit, onSaved }) {
  const { request } = useAuth();
  const [draft, setDraft] = useState(() => draftFrom(groups));
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const items = groups.flatMap((g) => g.items);
  const dirty = items.some((item) => draft[item.key] !== toText(item));

  useEffect(() => setDraft(draftFrom(groups)), [groups]);

  const submit = async (e) => {
    e.preventDefault();
    setFormError('');
    const errs = {};
    const values = {};
    for (const item of items) {
      const value = parseDecimal(draft[item.key]);
      if (value === null) errs[item.key] = `Isi ${item.label.toLowerCase()}.`;
      else if (Number.isNaN(value)) errs[item.key] = 'Isi dengan angka.';
      else if (item.integer && !Number.isInteger(value)) errs[item.key] = 'Isi dengan bilangan bulat.';
      else if (value < item.min || value > item.max) errs[item.key] = `Antara ${item.min} dan ${item.max}.`;
      values[item.key] = value;
    }
    setErrors(errs);
    if (Object.keys(errs).length) return;

    setSaving(true);
    try {
      const response = await request('/parameters', { method: 'PUT', body: { values } });
      onSaved(response.message, response.data);
    } catch (err) {
      const server = Object.fromEntries(Object.entries(fieldErrorsFrom(err)).map(([k, v]) => [k.replace(/^values\./, ''), v]));
      setErrors(server);
      if (!Object.keys(server).length) setFormError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={submit} noValidate className="mt-6 space-y-6">
      {formError && <Alert tone="danger">{formError}</Alert>}
      {groups.map((group) => (
        <section key={group.title} aria-labelledby={`param-${group.title}`} className="card p-4 sm:p-5">
          <h2 id={`param-${group.title}`} className="text-h2 font-semibold">
            {group.title}
          </h2>
          <div className="mt-4 grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
            {group.items.map((item) => (
              <Field key={item.key} id={item.key} label={item.label} required={canEdit} hint={item.hint} error={errors[item.key]}>
                {(props) => (
                  <div className="flex items-center gap-2">
                    <input
                      {...props}
                      type="text"
                      inputMode={item.integer ? 'numeric' : 'decimal'}
                      value={draft[item.key] ?? ''}
                      readOnly={!canEdit}
                      onChange={(e) => setDraft((d) => ({ ...d, [item.key]: e.target.value }))}
                      className="field w-32 tabular-nums read-only:bg-gray-25"
                    />
                    <span className="text-body text-gray-600">{item.unit}</span>
                  </div>
                )}
              </Field>
            ))}
          </div>
          {group.items.some((item) => item.updated_by) && (
            <p className="mt-4 border-t border-gray-100 pt-3 text-caption text-gray-600">
              {(() => {
                const last = [...group.items].filter((i) => i.updated_by).sort((a, b) => (a.updated_at < b.updated_at ? 1 : -1))[0];
                return `Terakhir diubah ${last.updated_by}, ${formatDateTime(last.updated_at)}.`;
              })()}
            </p>
          )}
        </section>
      ))}

      {canEdit && (
        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button
            type="button"
            disabled={!dirty || saving}
            onClick={() => {
              setDraft(draftFrom(groups));
              setErrors({});
            }}
            className="btn-secondary h-12 px-5 text-body-lg"
          >
            Batalkan perubahan
          </button>
          <button type="submit" disabled={!dirty || saving} className="btn-primary h-12 px-5 text-body-lg">
            {saving ? (
              <>
                <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                Menyimpan…
              </>
            ) : (
              'Simpan parameter'
            )}
          </button>
        </div>
      )}
    </form>
  );
}

export default function ParameterPage() {
  const { can } = useAuth();
  const canEdit = can('parameter', 'kelola');
  const { data, error, loading, reload } = useResource('/parameters');
  const [saved, setSaved] = useState(null);
  const [message, setMessage] = useState('');
  const groups = saved ?? data;

  return (
    <>
      <PageHeader
        title="Parameter"
        description={
          canEdit
            ? 'Angka yang dipakai perhitungan dan pengingat. Perubahan dicatat di audit dan berlaku untuk data baru.'
            : 'Angka yang dipakai perhitungan dan pengingat.'
        }
      />
      {message && (
        <Alert tone="success" className="mt-6">
          {message}
        </Alert>
      )}
      {!groups ? (
        <div className="mt-6">
          {loading ? <LoadingCard label="Memuat parameter…" /> : <ErrorCard title="Parameter belum bisa dimuat." message={error} onRetry={reload} />}
        </div>
      ) : (
        <ParameterForm
          groups={groups}
          canEdit={canEdit}
          onSaved={(msg, next) => {
            setMessage(msg);
            setSaved(next);
          }}
        />
      )}
    </>
  );
}
