<?php

namespace Database\Factories\Organization;

use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'slug'           => $this->faker->slug,
            'abbreviation'   => $this->faker->lexify('???'),
            'notes'          => $this->faker->paragraph,
            'primaryColor'   => $this->faker->hexcolor,
            'secondaryColor' => $this->faker->hexcolor,
            'inactive'       => $this->faker->boolean,
            'url_facebook'   => $this->faker->url,
            'url_website'    => $this->faker->url,
            'url_donate'     => $this->faker->url,
            'url_twitter'    => $this->faker->url,
            'address'        => $this->faker->streetName,
            'address2'       => '',
            'city'           => $this->faker->city,
            'state'          => $this->faker->stateAbbr,
            'zip'            => $this->faker->postcode,
            'phone'          => $this->faker->phoneNumber,
            'email'          => $this->faker->email,
            'email_director' => $this->faker->email,
            'latitude'       => $this->faker->latitude,
            'longitude'      => $this->faker->longitude,
        ];
    }
}
