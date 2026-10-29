<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PriceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PriceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_records_price_changes_without_duplicating_unchanged_prices(): void
    {
        $user = User::factory()->create();
        $catalog = app(PriceCatalog::class);
        $data = ['category' => 'Maçonnerie', 'name' => 'Mur', 'unit' => 'm²', 'unit_price' => '12.500'];
        $item = $catalog->create($user, $data);
        $catalog->update($user, $item, $data);
        $data['unit_price'] = '13.250';
        $catalog->update($user, $item, $data);

        $this->assertSame('13.250', $item->fresh()->unit_price);
        $this->assertSame(['12.500', '13.250'], $item->prices()->orderBy('id')->pluck('unit_price')->all());
    }

    public function test_catalog_rejects_invalid_prices(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        app(PriceCatalog::class)->create($user, ['category' => 'Test', 'name' => 'Test', 'unit' => 'm', 'unit_price' => '-1']);
    }
}
