<?php

use App\Livewire\Dashboard\Cloud\CloudHome;
use App\Models\CloudStoragePlan;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'enable_cloud' => true,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
        'cloud_sms_limit' => 20,
    ]);

    CloudStoragePlan::create([
        'name' => 'Starter Storage',
        'extra_contacts' => 5,
        'extra_sms' => 5,
        'is_active' => true,
        'duration_days' => 0,
    ]);
});

function cloudPageUser(): User
{
    return User::factory()->create([
        'package_id' => Package::first()->id,
    ]);
}

test('cloud page opens the storage picker from the inbox "Add more Cloud storage" deep link', function () {
    $user = cloudPageUser();

    $this->actingAs($user);

    Livewire::withQueryParams(['storage' => 1])
        ->test(CloudHome::class)
        ->assertSet('pickerTab', 'activate');
});

test('cloud page deep link opens the switch tab for users with an active storage pack', function () {
    $user = cloudPageUser();
    $plan = CloudStoragePlan::first();

    $user->cloudStorageTopUps()->create([
        'cloud_storage_plan_id' => $plan->id,
        'extra_contacts' => (int) $plan->extra_contacts,
        'extra_sms' => (int) $plan->extra_sms,
    ]);

    $this->actingAs($user);

    Livewire::withQueryParams(['storage' => 1])
        ->test(CloudHome::class)
        ->assertSet('pickerTab', 'switch');
});

test('cloud page without the deep link keeps the storage picker closed', function () {
    $user = cloudPageUser();

    $this->actingAs($user);

    Livewire::test(CloudHome::class)
        ->assertSet('pickerTab', null);
});
