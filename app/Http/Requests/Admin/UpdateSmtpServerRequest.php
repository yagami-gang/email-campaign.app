<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Importe Rule pour la validation unique ignorée

class UpdateSmtpServerRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés devraient pouvoir modifier des serveurs SMTP
        // Implémentez ici la logique d'autorisation (ex: $this->user()->can('update-smtp-server', $this->smtpServer))
        return true; // Pour l'instant, on autorise, mais à sécuriser plus tard
    }

    /**
     * Obtient les règles de validation qui s'appliquent à la requête.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Récupère l'ID du serveur SMTP depuis les paramètres de la route pour ignorer son propre nom lors de la vérification d'unicité
        $smtpServerId = $this->route('smtp_server')->id; // 'smtp_server' est le nom du paramètre de route par défaut pour les ressources

        return [
            // Le nom doit être unique SAUF pour le serveur que nous sommes en train de modifier
            'name' => ['required', 'string', 'max:255', Rule::unique('smtp_servers')->ignore($smtpServerId)],
            'url' => 'required|string|max:255',
            'api_key' => 'nullable|string', // Rendre la clé optionnelle
            'is_active' => 'boolean',
        ];
    }

    /**
     * Personnalise les messages d'erreur de validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du serveur SMTP est obligatoire.',
            'name.unique' => 'Un autre serveur SMTP avec ce nom existe déjà.',
            'api_key' => 'nullable|string', // Rendre la clé optionnelle
            'is_active.boolean' => 'Le statut "actif" doit être vrai ou faux.',
        ];
    }
}
