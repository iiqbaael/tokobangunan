# TB Sumber Baru

Aplikasi web untuk membantu operasional toko bangunan dengan pengelolaan penjualan, persediaan, kasir, dan laporan. Aplikasi dibuat menggunakan Laravel 12, Inertia.js, Vue 3, dan Tailwind CSS.

## Fitur

- Login dengan peran Owner, Admin, dan Kasir.
- Pengelolaan barang, kategori, pelanggan, serta satuan alternatif.
- POS penjualan, pembayaran, dan pembatalan (void) transaksi.
- Stok per cabang, penyesuaian stok dengan persetujuan, dan transfer antar cabang.
- Shift kasir dan pencatatan kas masuk/keluar.
- Pengelolaan piutang dan catatan tindak lanjut pelanggan.
- Dashboard dan laporan stok, kartu stok, kas shift, penjualan kredit, laba kotor, barang terhapus, serta konsolidasi cabang.

## Kebutuhan

- PHP 8.2 atau lebih baru beserta ekstensi yang dibutuhkan Laravel.
- Composer.
- Node.js dan npm.
- SQLite untuk konfigurasi lokal bawaan, atau MySQL/MariaDB bila disiapkan sendiri.

## Menjalankan secara lokal

Jalankan perintah berikut dari folder proyek:

```bash
composer install
npm install
```

Salin `.env.example` menjadi `.env`, lalu buat application key:

```bash
cp .env.example .env
php artisan key:generate
```

Di Windows PowerShell, gunakan `Copy-Item .env.example .env` sebagai pengganti `cp`.

Konfigurasi `.env.example` menggunakan SQLite. Pastikan file `database/database.sqlite` tersedia, kemudian jalankan migrasi dan data demo:

```bash
php artisan migrate --seed
```

Build aset frontend dan jalankan server lokal:

```bash
npm run build
php artisan serve
```

Buka alamat yang ditampilkan oleh `php artisan serve`, biasanya `http://127.0.0.1:8000`.

Untuk pengembangan frontend dengan hot reload, jalankan `npm run dev` di terminal lain sebagai pengganti build produksi.

> **Perhatian:** `php artisan migrate:fresh --seed` menghapus semua tabel dan data pada database yang sedang dikonfigurasi sebelum membuat ulang database demo. Gunakan hanya pada database lokal yang datanya boleh dihapus.

## Akun demo

Seeder demo membuat dua cabang, data barang, stok, transaksi, shift, dan data contoh lainnya. Akun berikut menggunakan password awal `password` pada database demo baru:

| Peran | Email | Cabang |
|---|---|---|
| Owner | `demo.owner@tbsumberbaru.test` | Semua cabang |
| Admin | `admin@toko.test` | Cabang Utama |
| Admin | `admin.cb2@tbsumberbaru.test` | Cabang Cibubur |
| Kasir | `kasir@toko.test` | Cabang Utama |
| Kasir | `kasir.cb2@tbsumberbaru.test` | Cabang Cibubur |

Seeder dasar juga menyiapkan `owner@toko.test` sebagai Owner. Pada database baru, password awalnya juga `password`.

**Akun dan data di atas hanya untuk demo lokal.** Ganti password dan kredensial sebelum aplikasi digunakan dengan data atau pengguna sungguhan. Seeder memakai `firstOrCreate`, jadi menjalankan seeder kembali tidak mereset password akun yang sudah ada.

## Pengujian

Jalankan seluruh test dengan:

```bash
php artisan test
```

Konfigurasi test pada `phpunit.xml` menggunakan MySQL dan database `db_sumberbaru_test`, bukan SQLite lokal bawaan. Buat database test khusus sebelum menjalankan test dan pastikan konfigurasi koneksi MySQL pada `.env` dapat digunakan. Jangan arahkan test ke database produksi atau database yang berisi data penting.

Sebagian test autentikasi dan fitur menggunakan factory untuk menyiapkan data. Test yang terkait verifikasi email dapat gagal karena fitur/rute verifikasi email belum diaktifkan pada aplikasi.

## Struktur aplikasi

- `app/Http/Controllers` — controller dan endpoint aplikasi.
- `app/Models` — model dan relasi database.
- `app/Services` — aturan bisnis penjualan, stok, transfer, shift, dan piutang.
- `database/migrations` — skema database.
- `database/seeders` — cabang, akun, dan dataset demo.
- `resources/js/Pages` — halaman Vue/Inertia.
- `routes/web.php` dan `routes/auth.php` — rute aplikasi.
- `tests` — test unit dan feature.
