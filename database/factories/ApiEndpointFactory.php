<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiEndpointFactory extends Factory
{
    public function definition(): array
    {
        // On simule des noms de services d'emailing connus
        $serviceName = fake()->randomElement(['Brevo', 'SendGrid', 'Mailgun', 'Postmark']);

        return [
            'name' => $serviceName . ' - Serveur ' . fake()->city(),
            'url' => 'https://api.' . strtolower($serviceName) . '.com/v3/mail/send',
            'api_key' => Str::random(40),
            'is_active' => fake()->boolean(90), // 90% de chance d'être actif
        ];
    }
}
