<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppListingCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_seeded_app_has_an_active_location_open_review_listing(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $apps = json_decode(
            file_get_contents(database_path('data/app-listings.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        )['apps'];
        $user = User::query()->where('email', 'demo.user01@tlstore.test')->firstOrFail();
        $token = $user->createToken('app-listing-catalog-test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/listings/apps?per_page=50');

        $response->assertOk()->assertJsonPath('meta.total', count($apps));
        $listedNames = collect($response->json('data'))->pluck('app.name')->sort()->values()->all();
        $expectedNames = collect($apps)->pluck('name')->sort()->values()->all();
        $this->assertSame($expectedNames, $listedNames);
        $this->assertSame(count($apps), count(array_unique($listedNames)));
    }
}
