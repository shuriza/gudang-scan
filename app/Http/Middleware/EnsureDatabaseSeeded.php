<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDatabaseSeeded
{
    /**
     * Seed produk awal jika DB masih kosong.
     *
     * NativePHP menggunakan SAPI `embed` untuk artisan dan HTTP, sehingga
     * AppServiceProvider::boot() hanya jalan sekali saat startup (sebelum
     * request masuk). Middleware ini berjalan pada tiap HTTP request; static
     * $checked membuatnya hanya seed satu kali per process lifetime.
     */
    public function handle(Request $request, Closure $next): Response
    {
        static $checked = false;

        if (! $checked) {
            $checked = true;

            try {
                if (Product::count() === 0) {
                    (new DatabaseSeeder)->run();
                }
            } catch (\Throwable) {
                // Tabel belum ada atau error lain — jangan crash request.
            }
        }

        return $next($request);
    }
}
