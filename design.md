# DESIGN.md — Material Balance & B3 Tracker

Panduan desain antarmuka untuk aplikasi **Material Balance & B3 Tracker** (Indofood CBP).
Aplikasi web untuk mencatat proses reclaimer tinta & solvent, menghitung material balance otomatis, dan mengawasi masa simpan limbah B3 sampai diserahkan ke vendor.

- **Stack:** Laravel 11 · Blade Components · Tailwind CSS · Alpine.js · (opsional) Livewire
- **Pengguna utama:** Operator mesin IRAC, Supervisor Produksi, HSE, Admin
- **Bahasa UI:** Bahasa Indonesia
- **Prinsip:** cepat diisi di lantai produksi, angka mudah dibaca, status B3 tidak bisa terlewat

---

## 1. Identitas Visual

Arah visual diambil dari logo Indofood CBP: **biru korporat** sebagai warna utama dan **merah** sebagai aksen. Nuansanya bersih, terpercaya, dan industrial — bukan aplikasi konsumen yang ramai.

### Aturan logo
- Gunakan **file logo resmi** dari tim Corporate Communication (SVG/PNG). Jangan menggambar ulang, mengubah warna, memiringkan, atau memberi efek.
- Tempatkan di kiri atas topbar/sidebar, tinggi **28–32px** (desktop) dan **24px** (mobile).
- Ruang kosong minimal di sekeliling logo = tinggi huruf "I".
- Latar logo selalu putih atau sangat terang. Di dark mode, pakai versi logo putih/monokrom resmi bila tersedia.

> Nilai warna di bawah adalah perkiraan dari logo. Cocokkan dengan brand guideline resmi Indofood sebelum produksi.

---

## 2. Warna

### 2.1 Warna brand

| Token | Hex | Pemakaian |
|---|---|---|
| `brand-blue-50` | `#EAF3FB` | Latar baris terpilih, latar badge info |
| `brand-blue-100` | `#CFE3F5` | Hover baris tabel, border fokus lembut |
| `brand-blue-500` | `#2A84C9` | Ikon, link sekunder |
| **`brand-blue-600`** | **`#1B75BB`** | **Warna primer: tombol utama, link, menu aktif** |
| `brand-blue-700` | `#155F98` | Hover tombol primer |
| `brand-blue-800` | `#0F4A78` | Pressed, header tabel gelap, sidebar (opsional) |
| `brand-blue-900` | `#0A3557` | Teks judul di atas latar biru muda |
| **`brand-red-600`** | **`#E41E26`** | **Aksen brand: garis indikator menu aktif, highlight KPI, label "CBP"** |
| `brand-red-700` | `#B8171E` | Hover aksen merah |

### 2.2 Netral

| Token | Hex | Pemakaian |
|---|---|---|
| `gray-0` | `#FFFFFF` | Kartu, form |
| `gray-25` | `#F7F9FC` | Latar halaman |
| `gray-100` | `#EEF1F5` | Latar input disabled, divider tebal |
| `gray-200` | `#DDE3EA` | Border kartu & input |
| `gray-400` | `#9AA5B1` | Placeholder |
| `gray-600` | `#5B6673` | Teks sekunder, label |
| `gray-800` | `#2B333D` | Teks isi |
| `gray-900` | `#141A21` | Judul |

### 2.3 Warna status (semantik)

**Penting:** merah brand (`#E41E26`) **tidak dipakai untuk error/bahaya**, supaya aksen brand tidak terbaca sebagai peringatan. Status bahaya memakai merah yang lebih gelap dan **selalu disertai ikon + teks**.

| Status | Teks/ikon | Latar | Border |
|---|---|---|---|
| Sukses / Aman | `#157F3D` | `#E8F6EE` | `#A7DDBC` |
| Info | `#1B75BB` | `#EAF3FB` | `#B7D6EF` |
| Perhatian | `#9A6700` | `#FFF6DB` | `#F2D27A` |
| Mendesak | `#C2410C` | `#FFEDE3` | `#F7B894` |
| Bahaya / Kritis | `#B42318` | `#FDECEA` | `#F4B4AE` |

### 2.4 Status masa simpan limbah B3

Status dihitung dari **tanggal mulai simpan** dan **batas masa simpan** per jenis/kategori limbah (dikonfigurasi Admin sesuai izin TPS LB3 dan peraturan yang berlaku). Ambang hari di bawah adalah default dan dapat diubah.

| Status | Kondisi (default) | Warna | Ikon | Badge |
|---|---|---|---|---|
| **Aman** | Sisa > 30 hari | Hijau | `shield-check` | `Aman · 45 hari lagi` |
| **Perhatian** | Sisa 15–30 hari | Amber | `clock` | `Perhatian · 21 hari lagi` |
| **Mendesak** | Sisa 1–14 hari | Oranye | `alert-triangle` | `Mendesak · 6 hari lagi` |
| **Kritis** | Hari ini / lewat batas | Merah gelap | `alert-octagon` | `Lewat 3 hari` |
| **Diserahkan** | Sudah ada surat jalan | Biru | `truck` | `Diserahkan · 12 Okt 2026` |

Selalu tulis **sisa hari dalam angka**. Warna hanya penguat, bukan satu-satunya penanda (aksesibel untuk buta warna).

### 2.5 Warna grafik

Urutan seri: `#1B75BB` (biru brand) → `#0F4A78` (biru tua) → `#4FA3DE` (biru muda) → `#E41E26` (merah brand, hanya untuk seri yang ingin disorot) → `#9AA5B1` (abu, pembanding).
Untuk material balance: **Input = biru**, **Output reclaim = hijau**, **Losses/limbah = oranye**, **Selisih di luar toleransi = merah gelap**.

---

## 3. Tipografi

| Peran | Font | Catatan |
|---|---|---|
| UI & teks | **Plus Jakarta Sans** (Google Fonts) | Modern, mudah dibaca, karya desainer Indonesia |
| Angka & kode dokumen | **JetBrains Mono** | Nomor surat jalan, nomor batch, manifest |
| Fallback | `system-ui, -apple-system, "Segoe UI", sans-serif` | |

Logo memakai huruf serif miring yang khas — **jangan** meniru gaya itu untuk teks UI.

### Skala

| Token | Ukuran / line-height | Berat | Pemakaian |
|---|---|---|---|
| `display` | 30 / 38 | 700 | Angka KPI besar di dashboard |
| `h1` | 24 / 32 | 700 | Judul halaman |
| `h2` | 20 / 28 | 600 | Judul kartu/seksi |
| `h3` | 16 / 24 | 600 | Sub-seksi form |
| `body` | 14 / 22 | 400 | Teks isi, sel tabel |
| `body-lg` | 16 / 24 | 400 | Input form di tablet lantai produksi |
| `label` | 13 / 18 | 500 | Label form, header tabel |
| `caption` | 12 / 16 | 400 | Hint, timestamp, satuan |

### Aturan angka
- Semua angka pakai `font-variant-numeric: tabular-nums` (class Tailwind `tabular-nums`) dan **rata kanan** di tabel.
- Format Indonesia: `1.250,50 kg` (titik ribuan, koma desimal). Gunakan helper `number_format($v, 2, ',', '.')`.
- Satuan selalu ditampilkan, dengan warna `gray-600`: **1.250,50** <span>kg</span>.
- Tanggal: `19 Okt 2026`; tanggal + jam: `19 Okt 2026, 15.28 WIB`.

---

## 4. Spasi, Grid, Radius, Bayangan

- **Unit dasar:** 4px. Skala yang dipakai: 4, 8, 12, 16, 20, 24, 32, 40, 48.
- **Padding kartu:** 20px (desktop), 16px (mobile).
- **Jarak antar field form:** 16px vertikal; antar grup form: 32px.
- **Grid konten:** 12 kolom, gutter 24px, lebar maks konten 1440px.
- **Radius:** input & tombol `8px`, kartu `12px`, badge `999px` (pill), modal `16px`.
- **Bayangan:**
  - `shadow-card`: `0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.08)`
  - `shadow-pop`: `0 8px 24px rgba(16,24,40,.12)` (dropdown, modal)
- **Border default:** 1px `gray-200`.

---

## 5. Layout Aplikasi

```
┌──────────────────────────────────────────────────────────────┐
│ Topbar 64px: [Logo] · Judul halaman · 🔔 Notifikasi · Profil │
├────────────┬─────────────────────────────────────────────────┤
│ Sidebar    │ Breadcrumb                                      │
│ 256px      │ H1 + aksi utama (kanan)                         │
│            │ ─────────────────────────────────────────────── │
│ Dashboard  │ Konten (kartu, tabel, form)                     │
│ Reclaimer  │                                                 │
│ Mat.Balance│                                                 │
│ Limbah B3  │                                                 │
│ Surat Jalan│                                                 │
│ Laporan    │                                                 │
│ Master Data│                                                 │
│ Pengaturan │                                                 │
└────────────┴─────────────────────────────────────────────────┘
```

- **Sidebar:** latar putih, border kanan `gray-200`. Item aktif: latar `brand-blue-50`, teks `brand-blue-700`, **garis kiri 3px `brand-red-600`** (satu-satunya tempat merah brand muncul terus-menerus).
- **Topbar:** putih, garis bawah 2px `brand-blue-600` sebagai penanda brand.
- **Lonceng notifikasi:** badge jumlah berwarna merah gelap bila ada limbah status Kritis.
- **Breakpoint:** `sm 640`, `md 768`, `lg 1024`, `xl 1280`. Di bawah `lg`, sidebar menjadi drawer.
- **Tablet lantai produksi (768–1024px):** target utama form Reclaimer — ukuran input `body-lg`, target sentuh minimal 44px.

---

## 6. Komponen (Blade)

Simpan di `resources/views/components/`. Penamaan `x-` mengikuti konvensi Laravel.

### 6.1 Tombol — `<x-button>`

| Varian | Gaya | Pemakaian |
|---|---|---|
| `primary` | Latar `brand-blue-600`, teks putih, hover `blue-700` | Simpan, Kirim, Ajukan |
| `secondary` | Putih, border `gray-200`, teks `gray-800` | Batal, Kembali |
| `ghost` | Transparan, teks `brand-blue-600` | Aksi tabel |
| `danger` | Latar `#B42318`, teks putih | Hapus, Batalkan surat jalan |

- Tinggi: `sm 32px`, `md 40px` (default), `lg 48px` (form tablet).
- Satu tombol `primary` per area. State loading: spinner + teks "Menyimpan…", tombol disabled.

### 6.2 Input form — `<x-input>`, `<x-select>`, `<x-textarea>`

- Label di atas field, `label` 13px/500. Tanda wajib: `*` berwarna `#B42318`.
- Border `gray-200`; fokus: border `brand-blue-600` + ring 3px `brand-blue-100`.
- Error: border `#B42318`, pesan di bawah field dengan ikon. Pesan validasi berbahasa Indonesia (`lang/id/validation.php`).
- **Input angka:** rata kanan, `tabular-nums`, satuan sebagai suffix di dalam field (`kg`, `L`).
- **Field hasil hitungan otomatis** (mis. total input, losses): latar `gray-100`, ikon kalkulator, read-only, dengan tooltip rumus.

### 6.3 Kartu KPI — `<x-stat-card>`

```
┌───────────────────────────┐
│ Solvent direclaim (bulan)  │  ← label 13px gray-600
│ 4.820,5 L                  │  ← display 30px, tabular-nums
│ ▲ 6,2% vs bulan lalu       │  ← caption, hijau/merah gelap
└───────────────────────────┘
```

### 6.4 Badge status — `<x-badge :status="...">`
Pill, 12px/500, padding 2×8px, ikon 14px di kiri. Warna mengikuti §2.3 dan §2.4.

### 6.5 Tabel — `<x-table>`
- Header: latar `gray-25`, teks `label` uppercase tidak dipakai (cukup 500 weight).
- Tinggi baris 48px; hover `brand-blue-50`.
- Angka rata kanan; kolom status di dekat kiri agar cepat dipindai.
- Baris limbah status **Kritis**: garis kiri 3px `#B42318`.
- Toolbar di atas tabel: pencarian, filter (tanggal, jenis limbah, status, vendor), tombol ekspor (Excel/PDF).
- Paginasi di bawah: "Menampilkan 1–20 dari 134".

### 6.6 Alert/banner — `<x-alert>`
Varian `info`, `success`, `warning`, `danger`. Dipakai di atas halaman, misalnya:
> ⚠️ **3 limbah B3 melewati batas masa simpan.** Segera jadwalkan penyerahan ke vendor. [Lihat daftar]

### 6.7 Modal konfirmasi — `<x-modal>`
Untuk aksi yang tidak bisa dibatalkan (hapus, finalisasi laporan, serahkan limbah). Judul berupa pertanyaan, tombol aksi menyebut tindakannya ("Ya, finalisasi laporan"), bukan "OK".

### 6.8 Timeline — `<x-timeline>`
Riwayat satu item limbah: Dicatat → Disimpan di TPS → Pengingat H-30 → H-14 → H-7 → Diserahkan (surat jalan #…). Titik berwarna sesuai status.

### 6.9 Empty state
Ikon garis 48px `gray-400`, satu kalimat penjelasan, satu tombol aksi. Contoh: "Belum ada proses reclaimer hari ini. **[+ Catat proses]**"

---

## 7. Pola Halaman

### 7.1 Dashboard
1. Banner alert bila ada limbah Kritis/Mendesak.
2. Baris 4 kartu KPI: Tinta direclaim, Solvent direclaim, Efisiensi recovery (%), Limbah B3 tersimpan (kg).
3. Grafik material balance per bulan (bar bertumpuk: output reclaim + losses vs garis input).
4. Tabel "Limbah B3 mendekati batas" (urut sisa hari terkecil), maks 10 baris + link "Lihat semua".
5. Aktivitas terbaru (proses reclaimer, surat jalan baru).

### 7.2 Form Proses Reclaimer (pengganti template Laporan Proses Reclaimer Mesin IRAC)
- Dibagi menjadi **langkah/seksi** dengan judul jelas:
  1. Informasi proses — tanggal, shift, mesin, operator
  2. Input — jenis material (tinta/solvent), kode/batch, berat/volume masuk
  3. Output — hasil reclaim, residu/sludge (menjadi limbah B3), losses
  4. Ringkasan material balance (otomatis)
  5. Paraf/approval — operator, supervisor
- Panel **ringkasan material balance** menempel di kanan (desktop) atau bawah (tablet), ter-update langsung saat angka diisi:

```
Input            1.000,00 kg
Output reclaim     842,30 kg   84,2%
Residu (B3)        141,50 kg   14,2%
Losses              16,20 kg    1,6%
─────────────────────────────────────
Selisih              0,00 kg   ✓ Dalam toleransi (±2%)
```

- Bila selisih di luar toleransi: badge Bahaya + kolom **"Alasan selisih"** menjadi wajib.
- Residu yang dicatat otomatis membuat entri limbah B3 (ditandai dengan info banner "Residu akan dicatat sebagai limbah B3").
- Simpan sebagai draf diizinkan; finalisasi butuh semua field wajib.

### 7.3 Limbah B3
- Tab: **Tersimpan** · **Diserahkan** · **Semua**.
- Kolom: Kode limbah, Jenis, Berat, Tgl mulai simpan, Batas simpan, Sisa hari (badge), Lokasi TPS, Aksi.
- Default urut sisa hari terkecil.
- Detail item: info, timeline (§6.8), dokumen terkait.

### 7.4 Surat Jalan / Penyerahan ke Vendor
- Form: vendor, nomor surat jalan & nomor manifest (font mono), tanggal, kendaraan, daftar limbah yang diserahkan (multi-pilih dari stok tersimpan), unggah scan dokumen (PDF/JPG).
- Arsip: pencarian teks penuh berdasarkan nomor, vendor, jenis limbah, rentang tanggal. Pratinjau dokumen di panel samping.

### 7.5 Laporan
Filter periode → pratinjau tabel → ekspor Excel/PDF. Kop laporan PDF memakai logo resmi + garis biru brand 2px.

---

## 8. Pengingat Bertingkat ke HSE

Visual dan isi notifikasi konsisten di aplikasi dan email.

| Tingkat | Waktu (default) | Penerima | Tampilan |
|---|---|---|---|
| 1 | H-30 | HSE | Notifikasi biasa, badge Perhatian |
| 2 | H-14 | HSE + Supervisor | Badge Mendesak |
| 3 | H-7 | HSE + Supervisor + Manajer | Badge Mendesak + banner dashboard |
| 4 | H-0 & tiap hari setelahnya | Semua di atas | Badge Kritis, banner tidak bisa ditutup sampai ada tindakan |

- **Email (Laravel Mailable / Markdown mail):** header putih + logo, garis biru 2px; subjek diawali tingkat, mis. `[MENDESAK] 2 limbah B3 batas simpan 7 hari lagi`; isi tabel ringkas + tombol primer "Lihat di aplikasi".
- Di aplikasi: dropdown lonceng, item belum dibaca berlatar `brand-blue-50`.

---

## 9. Ikon

- Set: **Lucide** (via `blade-ui-kit/blade-icons` + `mallardduck/blade-lucide-icons`) — garis 1,5–2px, konsisten.
- Ukuran: 16px (inline), 20px (tombol/menu), 24px (header kartu).
- Ikon kunci: `layout-dashboard`, `recycle` (reclaimer), `scale` (material balance), `biohazard` / `flask-conical` (limbah B3), `truck` (surat jalan), `file-bar-chart` (laporan), `database` (master data), `settings`.

---

## 10. Aksesibilitas

- Kontras teks minimal 4,5:1. `#1B75BB` di atas putih ≈ 4,9:1 (lolos untuk teks normal). Merah brand `#E41E26` di atas putih ≈ 4,5:1 — hanya untuk aksen/teks tebal ≥16px, bukan teks kecil.
- Status tidak pernah hanya dari warna: selalu ada ikon + teks.
- Fokus keyboard terlihat jelas (ring 3px `brand-blue-100` + border biru).
- Semua input punya `<label for>`; error dihubungkan lewat `aria-describedby`.
- Target sentuh minimal 44×44px di tampilan tablet.

---

## 11. Gaya Bahasa (Microcopy)

- Kalimat pendek, kata kerja aktif: "Catat proses", "Serahkan ke vendor", "Unduh laporan".
- Konsisten dengan istilah internal: *reclaimer*, *material balance*, *limbah B3*, *TPS*, *surat jalan*, *manifest*.
- Pesan error menjelaskan cara memperbaiki: "Berat output melebihi input. Periksa kembali angka residu."
- Hindari singkatan yang tidak umum di lantai produksi.

---

## 12. Implementasi Tailwind

### `tailwind.config.js`

```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
    './app/View/Components/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          blue: {
            50: '#EAF3FB', 100: '#CFE3F5', 500: '#2A84C9',
            600: '#1B75BB', 700: '#155F98', 800: '#0F4A78', 900: '#0A3557',
          },
          red: { 600: '#E41E26', 700: '#B8171E' },
        },
        gray: {
          0: '#FFFFFF', 25: '#F7F9FC', 100: '#EEF1F5', 200: '#DDE3EA',
          400: '#9AA5B1', 600: '#5B6673', 800: '#2B333D', 900: '#141A21',
        },
        success: { fg: '#157F3D', bg: '#E8F6EE', border: '#A7DDBC' },
        info:    { fg: '#1B75BB', bg: '#EAF3FB', border: '#B7D6EF' },
        warning: { fg: '#9A6700', bg: '#FFF6DB', border: '#F2D27A' },
        urgent:  { fg: '#C2410C', bg: '#FFEDE3', border: '#F7B894' },
        danger:  { fg: '#B42318', bg: '#FDECEA', border: '#F4B4AE' },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
        mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
      },
      fontSize: {
        caption: ['12px', '16px'],
        label:   ['13px', '18px'],
        body:    ['14px', '22px'],
        'body-lg': ['16px', '24px'],
        h3: ['16px', '24px'],
        h2: ['20px', '28px'],
        h1: ['24px', '32px'],
        display: ['30px', '38px'],
      },
      borderRadius: { card: '12px', modal: '16px' },
      boxShadow: {
        card: '0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.08)',
        pop:  '0 8px 24px rgba(16,24,40,.12)',
      },
    },
  },
  plugins: [forms],
};
```

### Font (di `resources/views/layouts/app.blade.php`)

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
```

### Pemetaan status B3 (contoh enum PHP)

```php
enum B3StorageStatus: string
{
    case Aman = 'aman';
    case Perhatian = 'perhatian';
    case Mendesak = 'mendesak';
    case Kritis = 'kritis';
    case Diserahkan = 'diserahkan';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Aman       => 'bg-success-bg text-success-fg border-success-border',
            self::Perhatian  => 'bg-warning-bg text-warning-fg border-warning-border',
            self::Mendesak   => 'bg-urgent-bg text-urgent-fg border-urgent-border',
            self::Kritis     => 'bg-danger-bg text-danger-fg border-danger-border',
            self::Diserahkan => 'bg-info-bg text-info-fg border-info-border',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Aman       => 'lucide-shield-check',
            self::Perhatian  => 'lucide-clock',
            self::Mendesak   => 'lucide-alert-triangle',
            self::Kritis     => 'lucide-octagon-alert',
            self::Diserahkan => 'lucide-truck',
        };
    }
}
```

### Contoh tombol primer

```blade
{{-- resources/views/components/button.blade.php --}}
@props(['variant' => 'primary', 'size' => 'md'])
@php
$base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition
         focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-blue-100 disabled:opacity-50';
$variants = [
  'primary'   => 'bg-brand-blue-600 text-white hover:bg-brand-blue-700 active:bg-brand-blue-800',
  'secondary' => 'bg-white text-gray-800 border border-gray-200 hover:bg-gray-25',
  'ghost'     => 'text-brand-blue-600 hover:bg-brand-blue-50',
  'danger'    => 'bg-danger-fg text-white hover:bg-[#912018]',
];
$sizes = ['sm' => 'h-8 px-3 text-label', 'md' => 'h-10 px-4 text-body', 'lg' => 'h-12 px-5 text-body-lg'];
@endphp
<button {{ $attributes->merge(['class' => "$base {$variants[$variant]} {$sizes[$size]}"]) }}>
  {{ $slot }}
</button>
```

---

## 13. Checklist Sebelum Rilis

- [ ] Warna brand dicocokkan dengan brand guideline resmi Indofood
- [ ] Logo resmi (SVG) dipakai, termasuk versi untuk kop PDF dan email
- [ ] Ambang masa simpan & jadwal pengingat dikonfigurasi HSE sesuai izin TPS LB3
- [ ] Format angka & tanggal Indonesia di semua tabel, ekspor, dan email
- [ ] Form reclaimer diuji di tablet yang dipakai di lantai produksi
- [ ] Uji kontras dan navigasi keyboard
