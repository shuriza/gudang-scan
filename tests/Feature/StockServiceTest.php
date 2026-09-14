<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 100): Product
    {
        return Product::create([
            'barcode' => '8992696404021',
            'name' => 'Kopi Kapal Api 165g',
            'unit' => 'pcs',
            'location' => 'B-01',
            'stock' => $stock,
            'min_stock' => 30,
        ]);
    }

    /**
     * Riwayat membaca arah mutasi dari tanda `quantity`. Jika mutasi keluar
     * tersimpan positif, riwayat menampilkan "+15" untuk barang yang keluar.
     */
    public function test_quantity_selalu_delta_bertanda(): void
    {
        $service = app(StockService::class);
        $product = $this->product(100);

        $in = $service->apply($product, StockMovement::TYPE_IN, 20);
        $out = $service->apply($product, StockMovement::TYPE_OUT, 15);
        $adjust = $service->apply($product, StockMovement::TYPE_ADJUST, 90);

        $this->assertSame(20, $in->quantity);
        $this->assertSame(-15, $out->quantity);
        $this->assertSame(-15, $adjust->quantity);

        foreach ([$in, $out, $adjust] as $movement) {
            $this->assertSame(
                $movement->stock_after - $movement->stock_before,
                $movement->quantity,
            );
        }
    }

    public function test_mutasi_memperbarui_stok_dan_mencatat_posisi_awal_akhir(): void
    {
        $service = app(StockService::class);
        $product = $this->product(100);

        $movement = $service->apply($product, StockMovement::TYPE_OUT, 40, 'kirim ke toko A');

        $this->assertSame(100, $movement->stock_before);
        $this->assertSame(60, $movement->stock_after);
        $this->assertSame('kirim ke toko A', $movement->note);
        $this->assertSame(60, $product->fresh()->stock);
    }

    public function test_stok_tidak_boleh_jatuh_di_bawah_nol(): void
    {
        $service = app(StockService::class);
        $product = $this->product(5);

        $this->expectException(StockException::class);

        try {
            $service->apply($product, StockMovement::TYPE_OUT, 6);
        } finally {
            // Transaksi yang gagal tidak boleh menyisakan perubahan apa pun.
            $this->assertSame(5, $product->fresh()->stock);
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_koreksi_ke_nilai_negatif_ditolak(): void
    {
        $this->expectException(StockException::class);

        app(StockService::class)->apply($this->product(10), StockMovement::TYPE_ADJUST, -1);
    }

    public function test_jumlah_masuk_keluar_minimal_satu(): void
    {
        $this->expectException(StockException::class);

        app(StockService::class)->apply($this->product(10), StockMovement::TYPE_IN, 0);
    }

    public function test_koreksi_ke_nol_diizinkan(): void
    {
        $product = $this->product(10);

        $movement = app(StockService::class)->apply($product, StockMovement::TYPE_ADJUST, 0);

        $this->assertSame(0, $movement->stock_after);
        $this->assertSame(-10, $movement->quantity);
        $this->assertSame(0, $product->fresh()->stock);
    }
}
