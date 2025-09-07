<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShortUrl extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'original_url',
        'short_code',
        'id_campaign',
        'id_contact',
        'tracking_data',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * 'tracking_data' sera automatiquement converti d'une chaîne JSON en un tableau PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tracking_data' => 'array',
    ];

    /**
     * Définit la relation entre une URL courte et le modèle Campagne.
     * Une URL courte peut être liée à une campagne spécifique (si applicable).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Définit la relation entre une URL courte et le modèle EmailLog.
     * Une URL courte peut être liée à un log d'email spécifique (si applicable).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }

    /**
     * Définit la relation entre une URL courte et le modèle TrackingClick.
     * Une URL courte peut avoir plusieurs clics enregistrés.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function trackingClicks(): HasMany
    {
        return $this->hasMany(TrackingClick::class);
    }
}
