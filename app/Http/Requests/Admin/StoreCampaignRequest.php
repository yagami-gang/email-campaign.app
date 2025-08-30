<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés et autorisés peuvent créer des campagnes
        // Exemple: return $this->user()->can('create', Campaign::class);
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
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'template_id' => 'required|exists:templates,id',
            'nbre_contacts' => 'nullable|integer|min:0', // Doit exister dans la table 'templates'
            // NOTE: Si vous liez une mailing list directement à la campagne, ajoutez une règle ici
            // 'mailing_list_id' => 'required|exists:mailing_lists,id',
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
            'name.required' => 'Le nom de la campagne est obligatoire.',
            'subject.required' => 'L\'objet de l\'email est obligatoire.',
            'sender_name.required' => 'Le nom de l\'expéditeur est obligatoire.',
            'sender_email.required' => 'L\'adresse email de l\'expéditeur est obligatoire.',
            'sender_email.email' => 'L\'adresse email de l\'expéditeur doit être une adresse email valide.',
            'scheduled_at.required' => 'La date et l\'heure de planification sont obligatoires.',
            'scheduled_at.date' => 'La date et l\'heure de planification doivent être une date valide.',
            'scheduled_at.after_or_equal' => 'La date et l\'heure de planification doivent être dans le futur ou maintenant.',
            'template_id.required' => 'Le template est obligatoire.',
            'template_id.exists' => 'Le template sélectionné est invalide.',
            'smtp_server_ids.required' => 'Au moins un serveur SMTP doit être sélectionné.',
            'smtp_server_ids.array' => 'Les serveurs SMTP doivent être fournis sous forme de liste.',
            'smtp_server_ids.min' => 'Vous devez sélectionner au moins un serveur SMTP.',
            'smtp_server_ids.*.exists' => 'Un des serveurs SMTP sélectionnés est invalide.',
        ];
    }
}
