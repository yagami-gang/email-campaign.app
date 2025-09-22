<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Template ' . fake()->words(2, true);

        return [
            'name' => ucfirst($name),
            'html_content' => "<h1>Bonjour {{firstname}} !</h1><p>Ceci est le contenu de votre email pour le template '{$name}'. Voici votre ville : {{city}}.</p><p><a href='https://google.com'>Cliquez ici</a></p>",
            'is_active' => true,
        ];
    }
}
