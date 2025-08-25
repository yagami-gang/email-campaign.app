<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blacklist extends Model
{
    use HasFactory;

    /**
     * Le nom de la table associée au modèle.
     * Par défaut, Laravel infère le pluriel du nom du modèle (ex: Blacklist -> blacklists).
     * Ici, nous spécifions explicitement 'blacklist' si vous souhaitez un nom au singulier.
     */
    protected $table = 'blacklist';

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'blacklisted_at',
        'template_id', // Optionnel, pour le suivi du template
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * 'blacklisted_at' sera automatiquement converti en objet DateTime de PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'blacklisted_at' => 'datetime',
    ];

    /**
     * Définit la relation entre une entrée de blacklist et le modèle Template.
     * Utile pour savoir quel template a été utilisé lors du blacklistage (si applicable).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
