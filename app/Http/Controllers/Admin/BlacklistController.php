<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blacklist;
use App\Models\Template; // Pour lier le template lors de l'ajout manuel ou pour l'édition
use Illuminate\Http\Request;
use App\Http\Requests\Admin\StoreBlacklistRequest;
use App\Http\Requests\Admin\UpdateBlacklistRequest;
use Illuminate\Support\Facades\Crypt; // Pour décrypter l'email du lien de désinscription
use Illuminate\Contracts\Encryption\DecryptException; // Pour gérer les erreurs de décryptage
use Illuminate\Support\Facades\Log; // Pour les logs d'erreur

class BlacklistController extends Controller
{
    /**
     * Affiche une liste de tous les emails blacklistés.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Récupère toutes les entrées de la blacklist avec la relation vers le template si elle existe
        $blacklistEntries = Blacklist::with('template')->latest()->get();
        return view('pages.blacklists.index', compact('blacklistEntries'));
    }

    /**
     * Affiche le formulaire pour ajouter manuellement un email à la blacklist.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $templates = Template::all(); // On peut lier l'entrée à un template existant
        return view('pages.blacklists.create', compact('templates'));
    }

    /**
     * Stocke un nouvel email dans la blacklist (ajout manuel via l'admin).
     *
     * @param  \App\Http\Requests\Admin\StoreBlacklistRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreBlacklistRequest $request)
    {
        Blacklist::create($request->validated());
        return redirect()->route('admin.blacklist.index')->with('success', 'L\'email a été ajouté à la blacklist avec succès !');
    }

    /**
     * Affiche les détails d'une entrée de blacklist spécifique.
     * Redirige vers la page d'édition pour l'admin, car l'édition est plus pertinente.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show(Blacklist $blacklist)
    {
        return redirect()->route('admin.blacklist.edit', $blacklist);
    }

    /**
     * Affiche le formulaire pour éditer une entrée de blacklist existante.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\View\View
     */
    public function edit(Blacklist $blacklist)
    {
        $templates = Template::all();
        return view('admin.blacklist.edit', compact('blacklist', 'templates'));
    }

    /**
     * Met à jour une entrée de blacklist existante dans la base de données.
     *
     * @param  \App\Http\Requests\Admin\UpdateBlacklistRequest  $request
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateBlacklistRequest $request, Blacklist $blacklist)
    {
        $blacklist->update($request->validated());
        return redirect()->route('admin.blacklist.index')->with('success', 'L\'entrée de la blacklist a été mise à jour avec succès !');
    }

    /**
     * Supprime un email de la blacklist.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Blacklist $blacklist)
    {
        $blacklist->delete();
        return redirect()->route('admin.blacklist.index')->with('success', 'L\'email a été retiré de la blacklist avec succès !');
    }

    /**
     * Affiche la page publique de désinscription.
     * L'email est passé via un paramètre crypté dans l'URL.
     *
     * @param string $encryptedEmail L'email crypté de l'utilisateur.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function unsubscribeForm(string $encryptedEmail, int $campaign_id )
    {
        try {
            $email = Crypt::decryptString($encryptedEmail);
        } catch (DecryptException $e) {
            Log::warning('Tentative d\'accès à un lien de désinscription invalide: ' . $encryptedEmail);
            return redirect('/')->with('error', 'Lien de désinscription invalide ou expiré.');
        }

        // Vérifie si l'email est déjà en blacklist
        $isBlacklisted = Blacklist::where('email', $email)->exists();

        // On passe toutes les informations nécessaires à la vue
        return view('unsubscribe.index', compact('email', 'encryptedEmail', 'isBlacklisted', 'campaign_id'));
    }

    /**
     * Traite la demande de désinscription et ajoute l'email à la blacklist.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unsubscribe(Request $request)
    {
        // On valide les données reçues du formulaire
        $validated = $request->validate([
            'encrypted_email' => 'required|string',
            'campaign_id' => 'nullable|exists:campaigns,id', // Valide l'ID de la campagne
        ]);

        try {
            $email = Crypt::decryptString($validated['encrypted_email']);
        } catch (DecryptException $e) {
            Log::error('Erreur de décryptage lors de la désinscription: ' . $validated['encrypted_email']);
            return redirect('/')->with('error', 'Une erreur est survenue. Veuillez réessayer.');
        }

        // Utilise updateOrCreate pour une gestion robuste des doublons
        Blacklist::updateOrCreate(
            ['email' => $email], // Condition pour trouver l'enregistrement
            [
                // Données à insérer ou à mettre à jour
                'campaign_id' => $validated['campaign_id'], // On stocke l'ID de la campagne
                'blacklisted_at' => now(),
            ]
        );

        // On peut créer une route dédiée pour la page de succès
        return redirect()->route('unsubscribe.success')->with('status', 'Vous avez été désinscrit avec succès !');
    }

    /**
     * Affiche une simple page de confirmation.
     */
    public function unsubscribeSuccess()
    {
        return view('unsubscribe.success');
    }
}
