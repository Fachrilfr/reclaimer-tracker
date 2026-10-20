import { useState } from 'react';
import { LoaderCircle } from 'lucide-react';
import { useAuth } from '../auth/AuthContext.jsx';
import { fieldErrorsFrom } from '../lib/useResource.js';
import { formatDate, formatPercent } from '../lib/format.js';
import Modal from './Modal.jsx';
import Field from './Field.jsx';
import Alert from './Alert.jsx';

// Supervisor meninjau rangkaian di bawah target dan mencatat tindak lanjutnya
export default function ReviewDialog({ chain, label, onClose, onReviewed }) {
  const { request } = useAuth();
  const [notes, setNotes] = useState('');
  const [error, setError] = useState('');
  const [formError, setFormError] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    if (!notes.trim()) {
      setError('Tulis catatan tindak lanjut, misalnya "Suhu uap terlalu rendah, operator sudah diarahkan".');
      return;
    }
    setIsSaving(true);
    try {
      const response = await request(`/batch-chains/${chain.root_id}/review`, {
        method: 'POST',
        body: { notes: notes.trim() },
      });
      onReviewed(response.message);
    } catch (err) {
      const fieldErrors = fieldErrorsFrom(err);
      if (fieldErrors.notes) setError(fieldErrors.notes);
      else setFormError(err.message);
      setIsSaving(false);
    }
  };

  return (
    <Modal title="Tinjau rangkaian di bawah target" onClose={onClose}>
      <dl className="grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg bg-gray-25 p-3 text-body">
        <div className="col-span-2">
          <dt className="text-caption text-gray-600">Rangkaian</dt>
          <dd className="font-mono text-gray-900">{label}</dd>
        </div>
        <div>
          <dt className="text-caption text-gray-600">Mulai</dt>
          <dd className="text-gray-900">
            {formatDate(chain.date)} · {chain.shift}
          </dd>
        </div>
        <div>
          <dt className="text-caption text-gray-600">Recovery / target</dt>
          <dd className="tabular-nums">
            <span className="font-semibold text-danger-fg">{formatPercent(chain.recovery_rate)}</span>
            <span className="text-gray-600"> / {formatPercent(chain.target_recovery_rate)}</span>
          </dd>
        </div>
      </dl>

      <form onSubmit={handleSubmit} noValidate className="mt-4 space-y-4">
        {formError && <Alert tone="danger">{formError}</Alert>}
        <Field
          id="review-notes"
          label="Catatan tindak lanjut"
          required
          hint="Penyebab dan tindakan yang diambil. Setelah disimpan, rangkaian ini hilang dari Perlu ditindak."
          error={error}
        >
          {(props) => (
            <textarea
              {...props}
              rows={3}
              maxLength={500}
              value={notes}
              onChange={(e) => {
                setNotes(e.target.value);
                setError('');
              }}
              className="field h-auto py-2"
            />
          )}
        </Field>
        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" onClick={onClose} className="btn-secondary h-11 px-4 text-body">
            Batal
          </button>
          <button type="submit" disabled={isSaving} className="btn-primary h-11 px-4 text-body">
            {isSaving ? (
              <>
                <LoaderCircle className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                Menyimpan…
              </>
            ) : (
              'Simpan tinjauan'
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}
