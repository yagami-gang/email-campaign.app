<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlacklistRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés et autorisés peuvent ajouter à la blacklist
        // Exemple: return $this->user()->can('create', Blacklist::class);
        return true; // À remplacer par une logique d'autorisation réelle
    }

    /**
     * Obtient les règles de validation qui s'appliquent à la requête.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255|unique:blacklist,email', // Email obligatoire, valide, unique
            'template_id' => 'nullable|exists:templates,id', // Optionnel, doit exister si fourni
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
            'email.unique' => 'Cet email est déjà sur la blacklist.',
            'template_id.exists' => 'Le template sélectionné est invalide.',
        ];
    }
}
