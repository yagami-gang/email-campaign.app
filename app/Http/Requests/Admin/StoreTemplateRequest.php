<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés devraient pouvoir créer des templates
        // Implémentez ici la logique d'autorisation (ex: $this->user()->can('create-template'))
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
            'name' => 'required|string|max:255|unique:templates,name', // Nom obligatoire, unique, max 255 caractères
            'html_content' => 'required|string', // Contenu HTML obligatoire
            'is_active' => 'boolean', // Doit être un booléen (true/false)
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
            'name.required' => 'Le nom du template est obligatoire.',
            'name.unique' => 'Un template avec ce nom existe déjà.',
            'name.max' => 'Le nom du template ne doit pas dépasser 255 caractères.',
            'html_content.required' => 'Le contenu HTML du template est obligatoire.',
            'is_active.boolean' => 'Le statut "actif" doit être vrai ou faux.',
        ];
    }
}
