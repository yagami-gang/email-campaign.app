<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campaign extends Model
{
    use HasFactory; use SoftDeletes;

    /**
     * Les attributs qui peuvent être assignés en masse.
     * Cela permet de créer ou mettre à jour une campagne avec un tableau de données.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'subject',
        'status',
        'progress',
        'template_id',
        'nbre_contacts',
        'sent_count',
        'nom_table_contact',
        'shoot_limit'
    ];

    /**
     * Définit la relation entre une campagne et le template HTML qu'elle utilise.
     * Une campagne appartient à un seul template.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Définit la relation entre une campagne et les serveurs SMTP qu'elle utilise pour l'envoi.
     * Une campagne peut utiliser plusieurs serveurs SMTP.
     * 'campaign_smtp_server' est le nom de la table pivot qui fera le lien.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function apiEndpoints(): BelongsToMany
    {
        return $this->belongsToMany(ApiEndpoint::class, 'campaign_api_endpoint')
            ->withPivot([
                'sender_name', 'sender_email',
                'send_frequency_minutes', 'max_daily_sends',
                'scheduled_at', 'progress', 'status','error_message'
            ])
            ->withTimestamps();
    }

    /**
     * Définit la relation entre une campagne et les logs d'emails qu'elle génère.
     * Une campagne peut générer de nombreux logs d'emails (un par envoi à un contact).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Définit la relation entre une campagne et les URLs courtes générées pour son suivi.
     * Une campagne peut avoir plusieurs URLs courtes pour traquer les clics.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function shortUrls(): HasMany
    {
        return $this->hasMany(ShortUrl::class);
    }

}
