<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlacklistRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés et autorisés peuvent modifier la blacklist
        // Exemple: return $this->user()->can('update', $this->blacklist);
        return true; // À remplacer par une logique d'autorisation réelle
    }

    /**
     * Obtient les règles de validation qui s'appliquent à la requête.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $blacklistId = $this->route('blacklist')->id; // Récupère l'ID de l'entrée en cours de modification

        return [
            // L'email doit être unique SAUF pour l'entrée que nous sommes en train de modifier
            'email' => ['required', 'email', 'max:255', Rule::unique('blacklist')->ignore($blacklistId)],
            'template_id' => 'nullable|exists:templates,id',
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
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être une adresse email valide.',
            'email.unique' => 'Un autre email similaire existe déjà sur la blacklist.',
            'template_id.exists' => 'Le template sélectionné est invalide.',
        ];
    }
}
