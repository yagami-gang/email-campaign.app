<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'smtp_servers' pour stocker les configurations des serveurs SMTP.
     */
    public function up(): void
    {
        Schema::create('smtp_servers', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            $table->string('name')->unique(); // Nom unique du serveur (ex: "SMTP OVH", "SMTP Mailgun").
            $table->string('host'); // Hôte SMTP (ex: smtp.ovh.net, smtp.mailgun.org).
            $table->integer('port'); // Port SMTP (ex: 587 pour TLS, 465 pour SSL).
            $table->string('username'); // Nom d'utilisateur pour l'authentification SMTP.
            $table->string('password'); // Mot de passe pour l'authentification SMTP.
            $table->string('encryption')->nullable(); // Type de chiffrement (ex: "tls", "ssl", peut être null si non chiffré).
            $table->boolean('is_active')->default(true); // Indique si ce serveur peut être utilisé pour l'envoi (par défaut, oui).
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'smtp_servers' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_servers');
    }
};
