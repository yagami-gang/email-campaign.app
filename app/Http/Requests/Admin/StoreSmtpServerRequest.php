<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSmtpServerRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés devraient pouvoir créer des serveurs SMTP
        // Implémentez ici la logique d'autorisation (ex: $this->user()->can('create-smtp-server'))
        return true; // Pour l'instant, on autorise, mais à sécuriser plus tard
    }

    /**
     * Obtient les règles de validation qui s'appliquent à la requête.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:smtp_servers,name', // Nom obligatoire, unique
            'host' => 'required|string|max:255', // Hôte obligatoire
            'port' => 'required|integer|min:1|max:65535', // Port obligatoire, entier, valide
            'username' => 'required|string|max:255', // Nom d'utilisateur obligatoire
            'password' => 'required|string|max:255', // Mot de passe obligatoire
            'encryption' => 'nullable|string|in:tls,ssl', // Chiffrement optionnel, doit être 'tls' ou 'ssl'
            'is_active' => 'boolean', // Doit être un booléen
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
            'name.unique' => 'Un serveur SMTP avec ce nom existe déjà.',
            'host.required' => 'L\'hôte SMTP est obligatoire.',
            'port.required' => 'Le port SMTP est obligatoire.',
            'port.integer' => 'Le port doit être un nombre entier.',
            'port.min' => 'Le port doit être au minimum 1.',
            'port.max' => 'Le port ne doit pas dépasser 65535.',
            'username.required' => 'Le nom d\'utilisateur SMTP est obligatoire.',
            'password.required' => 'Le mot de passe SMTP est obligatoire.',
            'encryption.in' => 'Le type de chiffrement doit être "tls" ou "ssl".',
            'is_active.boolean' => 'Le statut "actif" doit être vrai ou faux.',
        ];
    }
}
