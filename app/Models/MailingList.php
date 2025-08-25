<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MailingList extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     * C'est-à-dire les colonnes que vous pouvez remplir directement lors de la création ou mise à jour.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'imported_at',
        'status',
        'progress',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * Ici, 'imported_at' sera automatiquement converti en objet DateTime de PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'imported_at' => 'datetime',
        'progress' => 'integer',
    ];

    /**
     * Définit la relation entre une mailing list et ses contacts.
     * Une mailing list peut avoir plusieurs contacts, et un contact peut être dans plusieurs listes.
     * La méthode 'belongsToMany' indique une relation plusieurs-à-plusieurs.
     * 'mailing_list_contacts' est le nom de la table pivot qui fera le lien.
     * 'withTimestamps()' permet de gérer automatiquement les colonnes created_at et updated_at sur la table pivot.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'mailing_list_contacts')
                    ->withTimestamps();
    }
}
