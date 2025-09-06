<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // --- PARTIE 1 : DÉFINITION DES RÈGLES DE VALIDATION ---
        $rules = [
            // Règles pour le profil (toujours obligatoires)
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            // Règles pour le mot de passe (optionnelles mais interdépendantes)
            'current_password' => [
                'nullable', // Le champ peut être vide
                'required_with:password', // Mais devient obligatoire si un nouveau mdp est fourni
                'current_password', // Vérifie le mot de passe actuel de l'utilisateur
            ],
            'password' => [
                'nullable', // Le champ peut être vide
                'confirmed', // Doit correspondre au champ 'password_confirmation'
                Password::defaults(), // Applique les règles de complexité par défaut
            ],
        ];

        // --- PARTIE 2 : DÉFINITION DES MESSAGES D'ERREUR PERSONNALISÉS ---
        $messages = [
            'name.required' => 'Le champ nom est obligatoire.',
            'email.required' => 'Le champ email est obligatoire.',
            'email.email' => 'Veuillez fournir une adresse email valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte.',
            'current_password.required_with' => 'Le mot de passe actuel est requis pour pouvoir le changer.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.confirmed' => 'La confirmation du nouveau mot de passe ne correspond pas.',
        ];

        // --- PARTIE 3 : EXÉCUTION DE LA VALIDATION ---
        // La méthode validate() s'arrête et redirige automatiquement en cas d'échec.
        $validatedData = $request->validate($rules, $messages);

        // --- PARTIE 4 : MISE À JOUR DU MODÈLE UTILISATEUR ---
        // Mise à jour des informations du profil
        if (!empty($validatedData['name'])) {
            $user->name = $validatedData['name'];
        }
        if (!empty($validatedData['email'])) {
            $user->email = $validatedData['email'];
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Mise à jour conditionnelle du mot de passe
        if (!empty($validatedData['password'])) {
            $user->password = Hash::make($validatedData['password']);
        }

        $user->save();

        // --- PARTIE 5 : REDIRECTION AVEC MESSAGE DE SUCCÈS ---
        return Redirect::route('admin.profile.edit')->with('status', 'profile-updated');
    }


    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
