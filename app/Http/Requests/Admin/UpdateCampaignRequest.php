<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Seuls les administrateurs authentifiés et autorisés peuvent modifier cette campagne
        // Exemple: return $this->user()->can('update', $this->campaign);
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
            'sender_name' => 'required|string|max:255',
            'sender_email' => 'required|email|max:255',
            'send_frequency_minutes' => 'nullable|integer|min:0',
            'max_daily_sends' => 'nullable|integer|min:0',
            // La date planifiée peut être dans le passé si la campagne est déjà en cours ou passée
            'scheduled_at' => 'required|date', 
            'template_id' => 'required|exists:templates,id',
            'smtp_server_ids' => 'required|array|min:1',
            'smtp_server_ids.*' => 'exists:smtp_servers,id',
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
            'template_id.required' => 'Le template est obligatoire.',
            'template_id.exists' => 'Le template sélectionné est invalide.',
            'smtp_server_ids.required' => 'Au moins un serveur SMTP doit être sélectionné.',
            'smtp_server_ids.array' => 'Les serveurs SMTP doivent être fournis sous forme de liste.',
            'smtp_server_ids.min' => 'Vous devez sélectionner au moins un serveur SMTP.',
            'smtp_server_ids.*.exists' => 'Un des serveurs SMTP sélectionnés est invalide.',
            // 'mailing_list_id.required' => 'La mailing list est obligatoire.',
            // 'mailing_list_id.exists' => 'La mailing list sélectionnée est invalide.',
        ];
    }
}
