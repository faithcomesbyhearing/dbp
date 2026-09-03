<?php

namespace Database\Factories\Organization;

use App\Models\Organization\Asset;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'id'              => $this->faker->bankAccountNumber,
            'organization_id' => Organization::factory(),
            'asset_type'      => $this->faker->randomElement(['s3', 'cloudfront', 'other']),
            'base_name'       => $this->faker->url,
        ];
    }
}
