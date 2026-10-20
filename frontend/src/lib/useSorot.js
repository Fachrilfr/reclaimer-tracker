import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';

// Kelas untuk baris yang dituju dari peringatan dashboard (?sorot=...)
export const SOROT_CLASS = 'bg-brand-blue-50 outline outline-2 -outline-offset-2 outline-brand-blue-600';

/**
 * Peringatan di dashboard membawa ?sorot=<id> ke halaman tujuan. Setelah datanya siap,
 * elemen dengan data-sorot yang sama digulir ke tengah layar dan diberi fokus.
 * Mengembalikan id yang sedang disorot untuk memberi gaya pada barisnya.
 */
export function useSorot(ready) {
  const [params] = useSearchParams();
  const target = params.get('sorot');

  useEffect(() => {
    if (!ready || !target) return;
    const el = document.querySelector(`[data-sorot="${CSS.escape(target)}"]`);
    if (!el) return;
    el.scrollIntoView({ block: 'center' });
    el.focus({ preventScroll: true });
  }, [ready, target]);

  return target;
}
