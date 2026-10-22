export class ApiError extends Error {
  constructor(message, status, data = null) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

/**
 * Pemanggil API Laravel. Pesan error dari backend sudah berbahasa Indonesia,
 * jadi dipakai apa adanya; pesan di sini hanya untuk kasus tanpa respons JSON.
 */
export async function api(path, { method = 'GET', token, body } = {}) {
  // FormData (unggah berkas) dikirim apa adanya; browser mengisi Content-Type multipart sendiri
  const isForm = body instanceof FormData;
  const headers = { Accept: 'application/json' };
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  let response;
  try {
    response = await fetch(`/api${path}`, {
      method,
      headers,
      body: body === undefined || isForm ? body : JSON.stringify(body),
    });
  } catch {
    throw new ApiError('Server tidak bisa dihubungi. Periksa koneksi jaringan, lalu coba lagi.', 0);
  }

  let data = null;
  try {
    data = await response.json();
  } catch {
    // Respons tanpa JSON (misalnya halaman error dari server web)
  }

  if (response.ok) return data;

  if (response.status === 429) {
    throw new ApiError('Terlalu banyak percobaan. Tunggu 1 menit, lalu coba lagi.', 429, data);
  }

  throw new ApiError(
    data?.message ?? 'Server sedang bermasalah. Coba lagi beberapa saat, atau hubungi Super Admin bila berulang.',
    response.status,
    data,
  );
}
