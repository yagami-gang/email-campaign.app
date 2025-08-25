<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Nouvelle importation
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     * Cela permet de créer ou mettre à jour une campagne avec un tableau de données.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'subject',
        'sender_name',
        'sender_email',
        'send_frequency_minutes',
        'max_daily_sends',
        'scheduled_at',
        'status',
        'progress',
        'template_id',
    ];

    /**
     * Les attributs qui doivent être "castés" vers des types de données spécifiques.
     * 'send_frequency_minutes' et 'max_daily_sends' seront des entiers.
     * 'scheduled_at' sera converti en objet DateTime.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'send_frequency_minutes' => 'integer',
        'max_daily_sends' => 'integer',
        'scheduled_at' => 'datetime',
        'status' => 'string',
        'progress' => 'integer',
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
    public function smtpServers(): BelongsToMany
    {
        return $this->belongsToMany(SmtpServer::class, 'campaign_smtp_server')
                    ->withTimestamps(); // Inclut created_at et updated_at sur la table pivot
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
