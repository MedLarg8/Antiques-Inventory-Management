<?php

namespace Database\Seeders;

use App\Actions\ChangeStorageAreaAssignment;
use App\Enums\Role;
use App\Models\City;
use App\Models\StorageArea;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class OrganizationDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Organization fixtures may only be created in the local environment.');
        }

        foreach (range(1, 3) as $number) {
            $city = City::firstOrCreate(['code' => 'DEMO-CITY-'.$number], [
                'name' => 'Example City '.$number,
                'description' => 'Local development fixture, not an operational city.',
                'is_active' => true,
            ]);
            foreach (range(1, 2) as $areaNumber) {
                $area = StorageArea::firstOrCreate(['code' => "DEMO-AREA-{$number}-{$areaNumber}"], [
                    'city_id' => $city->id,
                    'name' => "Example Storage {$number}.{$areaNumber}",
                    'location_details' => 'Local development fixture.',
                    'is_active' => true,
                ]);
                if ($areaNumber === 1) {
                    $user = User::firstOrCreate(['email' => "example-employee-{$number}@example.test"], [
                        'name' => 'Example Employee '.$number,
                        'password' => Str::password(64),
                    ]);
                    $user->refresh();
                    if ($user->role === Role::StockEmployee && ! $user->currentStorageArea()->exists()
                        && ! $area->assignments()->exists() && $area->is_active) {
                        app(ChangeStorageAreaAssignment::class)->handle($area, $user->id, null);
                    }
                }
            }
        }
        $this->command->info('Local organization fixtures created. Existing data and assignment history were preserved. Use password reset for fixture accounts; passwords are never displayed.');
    }
}
