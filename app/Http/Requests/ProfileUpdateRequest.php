<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à effectuer cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // En général, un utilisateur authentifié peut toujours mettre à jour son propre profil.
        return true;
    }

    /**
     * Récupère les règles de validation qui s'appliquent à la requête.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email', // La règle 'lowercase' de Laravel 10+ peut être enlevée si vous n'en avez pas l'utilité, mais elle est bonne à garder.
                'max:255',
                // Cette règle est cruciale : elle vérifie si l'email est unique,
                // SAUF pour l'utilisateur actuellement authentifié.
                // Cela permet à un utilisateur de soumettre le formulaire sans changer son propre email.
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * Récupère les messages d'erreur personnalisés pour les règles de validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le champ nom est obligatoire.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères.',

            'email.required' => 'Le champ email est obligatoire.',
            'email.email' => 'Veuillez fournir une adresse email valide.',
            'email.max' => 'L\'adresse email ne doit pas dépasser 255 caractères.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte.',
        ];
    }
}
