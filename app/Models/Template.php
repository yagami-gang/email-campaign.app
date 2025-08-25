<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     * Cela permet de créer ou mettre à jour un template avec un tableau de données.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'html_content',
        'is_active',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * Ici, 'is_active' sera automatiquement converti en booléen.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Définit la relation entre un template et les campagnes qui l'utilisent.
     * Un template peut être utilisé dans plusieurs campagnes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Définit la relation entre un template et les entrées de la blacklist
     * associées à ce template. Cette relation est optionnelle et sert au suivi.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function blacklistEntries(): HasMany
    {
        return $this->hasMany(Blacklist::class);
    }
}
