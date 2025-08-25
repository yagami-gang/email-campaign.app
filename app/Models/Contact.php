<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     * Cela permet de créer ou mettre à jour un contact avec un tableau de données.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'name',
        'firstname',
        'cp',
        'department',
        'phone_number',
        'city',
        'profession',
        'habitation',
        'anciennete',
        'statut',
    ];

    /**
     * Définit la relation entre un contact et les mailing lists auxquelles il appartient.
     * Un contact peut appartenir à plusieurs mailing lists.
     * 'mailing_list_contacts' est la table pivot qui gère cette association.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function mailingLists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'mailing_list_contacts')
                    ->withTimestamps(); // Gère les created_at/updated_at sur la table pivot.
    }

    /**
     * Définit la relation entre un contact et les logs d'emails qui lui sont associés.
     * Chaque fois qu'un email est envoyé à ce contact, un EmailLog est créé.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }
}
