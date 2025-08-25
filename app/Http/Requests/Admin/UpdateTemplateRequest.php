<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Importe Rule pour la validation unique ignorée

class UpdateTemplateRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés devraient pouvoir modifier des templates
        // Implémentez ici la logique d'autorisation (ex: $this->user()->can('update-template', $this->template))
        return true; // Pour l'instant, on autorise, mais à sécuriser plus tard
    }

    /**
     * Obtient les règles de validation qui s'appliquent à la requête.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Récupère l'ID du template depuis les paramètres de la route pour ignorer son propre nom lors de la vérification d'unicité
        $templateId = $this->route('template')->id; // 'template' est le nom du paramètre de route par défaut pour les ressources

        return [
            // Le nom doit être unique SAUF pour le template que nous sommes en train de modifier
            'name' => ['required', 'string', 'max:255', Rule::unique('templates')->ignore($templateId)],
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
            'name.unique' => 'Un autre template avec ce nom existe déjà.',
            'name.max' => 'Le nom du template ne doit pas dépasser 255 caractères.',
            'html_content.required' => 'Le contenu HTML du template est obligatoire.',
            'is_active.boolean' => 'Le statut "actif" doit être vrai ou faux.',
        ];
    }
}
