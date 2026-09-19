# Gudang Scan

## Operator

1. Buka tab **Ringkas** untuk melihat stok menipis, mutasi hari ini, dan lokasi.
2. Gunakan **Scan** untuk mutasi satuan. Produk arsip harus diaktifkan dulu.
3. Gunakan **Dokumen** untuk penerimaan atau pengeluaran multi-item. Posting bersifat atomik.
4. Gunakan **Opname** untuk hitung fisik. Item yang belum dihitung tidak boleh difinalisasi.
5. Gunakan **Laporan** untuk melihat stok menipis dan mengunduh CSV mutasi.
6. Gunakan **Setelan** untuk backup dan restore. Restore menolak file dengan checksum rusak.

## Developer

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan test --compact
php artisan native:run android --build=debug --no-interaction --no-tty
```

Release Android:

```bash
php artisan native:run android --build=release --no-interaction --no-tty
```

Signing key tidak boleh masuk repository. Naikkan `NATIVEPHP_APP_VERSION` dan `NATIVEPHP_APP_VERSION_CODE` sebelum rilis.
