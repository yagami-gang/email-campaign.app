<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingClick extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_contact',
        'id_campaign',
        'short_url_id',
        'clicked_at',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * 'clicked_at' sera automatiquement converti en objet DateTime de PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    /**
     * Définit la relation entre un clic et le modèle EmailLog.
     * Un clic appartient à un seul log d'email.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }

    /**
     * Définit la relation entre un clic et le modèle ShortUrl.
     * Un clic est associé à une seule URL courte.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shortUrl(): BelongsTo
    {
        return $this->belongsTo(ShortUrl::class);
    }
}
