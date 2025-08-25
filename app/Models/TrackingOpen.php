<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingOpen extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email_log_id',
        'opened_at',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * 'opened_at' sera automatiquement converti en objet DateTime de PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'opened_at' => 'datetime',
    ];

    /**
     * Définit la relation entre un enregistrement d'ouverture et le modèle EmailLog.
     * Un enregistrement d'ouverture appartient à un seul log d'email.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
