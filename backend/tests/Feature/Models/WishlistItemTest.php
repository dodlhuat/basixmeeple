<?php

namespace Tests\Feature\Models;

use App\Models\Collection;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_estimate_is_cast_to_a_decimal_string(): void
    {
        $item = WishlistItem::factory()->create(['price_estimate' => 39.9]);

        $this->assertSame('39.90', $item->price_estimate);
    }

    public function test_collection_relation_resolves(): void
    {
        $collection = Collection::factory()->create();
        $item = WishlistItem::factory()->create(['collection_id' => $collection->id]);

        $this->assertTrue($item->collection->is($collection));
        $this->assertTrue($collection->wishlistItems()->first()->is($item));
    }
}
