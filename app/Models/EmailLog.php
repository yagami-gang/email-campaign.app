<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailLog extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'campaign_id',
        'contact_id',
        'status',
        'sent_at',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * 'sent_at' sera converti en objet DateTime.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'status' => 'string', // S'assurer que le statut est bien géré comme une chaîne
    ];

    /**
     * Définit la relation entre un log d'email et la campagne associée.
     * Un log d'email appartient à une seule campagne.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Définit la relation entre un log d'email et le contact destinataire.
     * Un log d'email est lié à un seul contact.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Définit la relation entre un log d'email et son enregistrement d'ouverture.
     * Un log d'email peut avoir au maximum un enregistrement d'ouverture (car ouvert une seule fois).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function trackingOpen(): HasOne
    {
        return $this->hasOne(TrackingOpen::class);
    }

    /**
     * Définit la relation entre un log d'email et les clics associés.
     * Un log d'email peut avoir plusieurs clics (si le destinataire clique sur plusieurs liens ou sur le même plusieurs fois).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function trackingClicks(): HasMany
    {
        return $this->hasMany(TrackingClick::class);
    }
}
