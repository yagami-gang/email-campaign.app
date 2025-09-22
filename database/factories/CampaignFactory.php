<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Campagne ' . fake()->words(2, true),
            'subject' => fake()->sentence(4),
            'status' => fake()->randomElement(['pending', 'running', 'completed', 'paused', 'failed']),
            'progress' => fake()->numberBetween(0, 100),
            'nbre_contacts' => fake()->numberBetween(1000, 50000),
            'sent_count' => function (array $attributes) {
                // Le nombre d'envoyés est logiquement inférieur ou égal au nombre de contacts
                return fake()->numberBetween(0, $attributes['nbre_contacts']);
            },
            // On s'assure qu'un template valide est toujours associé
            'template_id' => Template::factory(),
            // La table des contacts est spécifique à chaque campagne, donc on la laisse null ici
            'nom_table_contact' => null,
        ];
    }
}
