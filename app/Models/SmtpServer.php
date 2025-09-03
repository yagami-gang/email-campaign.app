<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmtpServer extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     * Cela permet de créer ou mettre à jour un serveur SMTP avec un tableau de données.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'url',
        'is_active',
        'api_key',
    ];

    /**
     * Les attributs qui doivent être cachés lors de la conversion du modèle en tableau ou JSON.
     * C'est crucial pour la sécurité, car le mot de passe ne doit jamais être exposé.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * 'port' sera un entier, et 'is_active' un booléen.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

}
