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
        // TODO: Implémenter la logique d'autorisation ici, par exemple :
        // return $this->user()->can('create-smtp-server');
        return true;
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
            'url' => 'required|string|max:255', // Hôte obligatoire
            'port' => ['nullable','integer','min:1'],
            'username' => ['nullable','string','max:255'],
            'password' => ['nullable','string','max:255'],
            'encryption' => ['nullable','in:tls,ssl'],
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
            'url.required' => 'L\'hôte SMTP est obligatoire.',
            'url.string' => 'L\'hôte doit être une chaîne de caractères.',
            'url.max' => 'L\'hôte ne doit pas dépasser 255 caractères.',
            'port.integer' => 'Le port doit être un nombre entier.',
            'port.min' => 'Le port doit être au minimum 1.',
            'username.string' => 'Le nom d\'utilisateur doit être une chaîne de caractères.',
            'username.max' => 'Le nom d\'utilisateur ne doit pas dépasser 255 caractères.',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères.',
            'password.max' => 'Le mot de passe ne doit pas dépasser 255 caractères.',
            'encryption.in' => 'Le type de chiffrement doit être "tls" ou "ssl".',
            'is_active.boolean' => 'Le statut "actif" doit être vrai ou faux.',
        ];
    }
}
