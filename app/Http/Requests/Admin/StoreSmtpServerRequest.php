<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiEndpointRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:api_endpoints,name', // Nom obligatoire, unique
            'url' => 'required|string|max:255', // Hôte obligatoire
            'api_key' => 'nullable|string', // Rendre la clé optionnelle
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
            'is_active.boolean' => 'Le statut "actif" doit être vrai ou faux.',
        ];
    }
}
