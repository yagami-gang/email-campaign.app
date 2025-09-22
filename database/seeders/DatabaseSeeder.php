<?php

namespace Database\Seeders;

use App\Models\ApiEndpoint;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Exécute les seeders de la base de données.
     */
    public function run(): void
    {
        // --- 1. Création de l'utilisateur Admin ---
        // On utilise firstOrCreate pour éviter de créer un doublon si l'utilisateur existe déjà.
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'), // Mot de passe : password
            ]
        );

        // --- 2. Création des ressources de base ---
        // On crée un pool de templates et de serveurs API qui seront utilisés par les campagnes.
        $templates = Template::factory(5)->create();
        $apiEndpoints = ApiEndpoint::factory(4)->create();

        // --- 3. Création d'une campagne "Brouillon" prête à être configurée ---
        Campaign::factory()->create([
            'name' => 'Campagne de Bienvenue - Hiver 2025',
            'subject' => '❄️ Bienvenue parmi nous !',
            'status' => 'pending',
            'template_id' => $templates->first()->id,
        ]);

        // --- 4. Création d'une campagne "Terminée" avec des statistiques simulées ---
        $completedCampaign = Campaign::factory()->create([
            'name' => 'Ventes Flash - Automne 2024',
            'subject' => '🍂 Ne manquez pas nos offres exclusives !',
            'status' => 'completed',
            'template_id' => $templates->last()->id,
            'nbre_contacts' => 150000,
            'sent_count' => 149850, // On simule un compteur d'envoi
            'progress' => 100,
        ]);

        // On attache deux serveurs API à cette campagne terminée, avec des données pivot
        $completedCampaign->apiEndpoints()->attach([
            $apiEndpoints[0]->id => [
                'sender_name' => 'L\'équipe Marketing',
                'sender_email' => 'marketing@example.com',
                'max_daily_sends' => 10000,
            ],
            $apiEndpoints[1]->id => [
                'sender_name' => 'Le Service Client',
                'sender_email' => 'support@example.com',
                'max_daily_sends' => 5000,
            ],
        ]);

        // Affichage des identifiants dans la console
        $this->command->info('Base de données remplie avec des données de test !');
        $this->command->warn('Compte administrateur :');
        $this->command->line('Email: admin@example.com');
        $this->command->line('Mot de passe: password');
    }
}
