# Gudang Scan

Aplikasi inventaris gudang berbasis barcode untuk web dan Android. Gudang Scan dibangun dengan Laravel, Livewire, dan NativePHP Mobile agar operator dapat mencatat mutasi stok, mengelola dokumen inventaris, melakukan stock opname, dan memantau stok menipis dari satu aplikasi.

## Fitur Utama

- Dashboard ringkas untuk stok menipis, mutasi hari ini, dan lokasi penyimpanan.
- Pemindaian barcode untuk mutasi stok satuan.
- Pengelolaan produk, lokasi, pemasok, dan riwayat mutasi.
- Dokumen penerimaan dan pengeluaran multi-item dengan proses posting atomik.
- Stock opname dengan validasi seluruh item sebelum finalisasi.
- Peringatan stok menipis dan laporan mutasi yang dapat diekspor ke CSV.
- Backup dan restore database dengan pemeriksaan checksum.
- Build Android melalui NativePHP Mobile.

## Teknologi

- PHP 8.3 dan Laravel 13
- Livewire 4
- NativePHP Mobile 3
- NativePHP Camera dan mobile barcode scanner
- SQLite
- Tailwind CSS 4 dan Vite 8
- PHPUnit 12

## Menjalankan Secara Lokal

```bash
git clone https://github.com/shuriza/gudang-scan.git
cd gudang-scan
composer run setup
composer run dev
```

Perintah `composer run setup` memasang dependensi, membuat `.env`, menghasilkan application key, menjalankan migrasi, memasang dependensi frontend, dan membangun aset.

## Menjalankan Pengujian

```bash
composer test
vendor/bin/pint --test
npm run build
```

Test suite mencakup alur dashboard, pemindaian produk, mutasi stok, dokumen inventaris, lokasi, stock opname, peringatan stok, filter riwayat, backup, dan restore.

## Build Android

Build debug:

```bash
php artisan native:run android --build=debug --no-interaction --no-tty
```

Build release:

```bash
php artisan native:run android --build=release --no-interaction --no-tty
```

Signing key tidak boleh disimpan di repository. Naikkan `NATIVEPHP_APP_VERSION` dan `NATIVEPHP_APP_VERSION_CODE` sebelum membuat rilis.

## Operasional

Panduan singkat untuk operator dan developer tersedia di [`docs/OPERATIONS.md`](docs/OPERATIONS.md).
