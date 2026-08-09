<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Services\InventoryDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_posts_all_items_and_links_movements(): void
    {
        $first = $this->product('A', 5);
        $second = $this->product('B', 10);
        $document = $this->document(InventoryDocument::TYPE_RECEIPT);
        $document->items()->create(['product_id' => $first->id, 'quantity' => 3]);
        $document->items()->create(['product_id' => $second->id, 'quantity' => 4]);

        app(InventoryDocumentService::class)->post($document);

        $this->assertSame(8, $first->fresh()->stock);
        $this->assertSame(14, $second->fresh()->stock);
        $this->assertSame(InventoryDocument::STATUS_POSTED, $document->fresh()->status);
        $this->assertSame(2, $document->movements()->count());
    }

    public function test_issue_is_atomic_when_any_item_lacks_stock(): void
    {
        $first = $this->product('A', 5);
        $second = $this->product('B', 1);
        $document = $this->document(InventoryDocument::TYPE_ISSUE);
        $document->items()->create(['product_id' => $first->id, 'quantity' => 2]);
        $document->items()->create(['product_id' => $second->id, 'quantity' => 2]);

        try {
            app(InventoryDocumentService::class)->post($document);
            $this->fail('Expected insufficient stock exception.');
        } catch (StockException) {
            $this->assertSame(5, $first->fresh()->stock);
            $this->assertSame(1, $second->fresh()->stock);
            $this->assertSame(InventoryDocument::STATUS_DRAFT, $document->fresh()->status);
            $this->assertSame(0, $document->movements()->count());
        }
    }

    public function test_issue_posts_all_items_when_stock_is_sufficient(): void
    {
        $first = $this->product('C', 8);
        $second = $this->product('D', 6);
        $document = $this->document(InventoryDocument::TYPE_ISSUE);
        $document->items()->create(['product_id' => $first->id, 'quantity' => 3]);
        $document->items()->create(['product_id' => $second->id, 'quantity' => 2]);

        app(InventoryDocumentService::class)->post($document);

        $this->assertSame(5, $first->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
        $this->assertSame(InventoryDocument::STATUS_POSTED, $document->fresh()->status);
        $this->assertSame(2, $document->movements()->count());
        $this->assertTrue($document->movements()->where('quantity', '<', 0)->exists());
    }

    public function test_posted_document_can_be_cancelled_with_reversal_movements(): void
    {
        $product = $this->product('E', 4);
        $document = $this->document(InventoryDocument::TYPE_RECEIPT);
        $document->items()->create(['product_id' => $product->id, 'quantity' => 3]);

        $service = app(InventoryDocumentService::class);
        $service->post($document);
        $service->cancel($document->fresh());

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(InventoryDocument::STATUS_CANCELLED, $document->fresh()->status);
        $this->assertSame(2, $document->movements()->count());
    }

    private function product(string $suffix, int $stock): Product
    {
        return Product::create(['barcode' => '899100000000'.$suffix, 'name' => 'Produk '.$suffix, 'unit' => 'pcs', 'stock' => $stock, 'min_stock' => 0]);
    }

    private function document(string $type): InventoryDocument
    {
        return InventoryDocument::create([
            'number' => 'DOC-'.strtoupper($type),
            'type' => $type,
            'status' => InventoryDocument::STATUS_DRAFT,
            'document_date' => today(),
        ]);
    }
}
