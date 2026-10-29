<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MeasurementLine;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_measurements_use_current_prices_and_enforce_project_rights(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = Project::factory()->create(['client_id' => Client::factory()->create()->id]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project->members()->attach($architect->id, ['project_role' => 'collaborator']);
        $data = ['category' => 'Maçonnerie', 'name' => 'Mur', 'unit' => 'm²', 'unit_price' => '12.500'];
        $this->get(route('prices.index'))->assertRedirect();
        $this->actingAs($architect)->post(route('prices.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $item = PriceItem::query()->firstOrFail();
        $this->post(route('measurement.store', $project), ['price_item_id' => $item->id, 'quantity' => '2'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('measurement.index', $project))->assertOk()->assertSee('25.000');
        $data['unit_price'] = '15.000';
        $this->put(route('prices.update', $item), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('measurement.index', $project))->assertOk()->assertSee('30.000');
        $this->assertSame(2, $item->prices()->count());

        foreach (['collaborator', 'client', 'contractor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('prices.index'))->assertForbidden();
            $this->actingAs($user)->get(route('measurement.index', $project))->assertForbidden();
            $this->actingAs($user)->post(route('prices.store'), $data)->assertForbidden();
        }
        $outsider = User::factory()->create();
        $outsider->assignRole('architect');
        $this->actingAs($outsider)->get(route('measurement.index', $project))->assertForbidden();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('prices.index'))->assertOk();
        $this->assertSame(1, MeasurementLine::query()->count());
    }
}
