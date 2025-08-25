<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmtpServer;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\StoreSmtpServerRequest; // Importe la requête de validation pour la création
use App\Http\Requests\Admin\UpdateSmtpServerRequest; // Importe la requête de validation pour la mise à jour
use Illuminate\Support\Facades\Hash; // Pour hasher le mot de passe

class SmtpServerController extends Controller
{
    /**
     * Affiche une liste de tous les serveurs SMTP.
     * C'est la page principale de gestion des serveurs.
     */
    public function index()
    {
        // Récupère tous les serveurs SMTP de la base de données
        $smtpServers = SmtpServer::all();
        // Passe les serveurs SMTP à la vue 'admin.smtp_servers.index' pour affichage
        return view('admin.smtp_servers.index', compact('smtpServers'));
    }

    /**
     * Affiche le formulaire pour créer un nouveau serveur SMTP.
     */
    public function create()
    {
        // Renvoie simplement la vue du formulaire de création
        return view('admin.smtp_servers.create');
    }

    /**
     * Stocke un nouveau serveur SMTP dans la base de données.
     * Utilise StoreSmtpServerRequest pour la validation des données.
     * Le mot de passe est hashé avant d'être stocké.
     *
     * @param  \App\Http\Requests\Admin\StoreSmtpServerRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreSmtpServerRequest $request)
    {
        // Récupère les données validées
        $validatedData = $request->validated();
        
        // Hashe le mot de passe avant de le stocker
        $validatedData['password'] = Hash::make($validatedData['password']);
        $validatedData['is_active'] = true;
        // Crée un nouveau serveur SMTP avec les données validées et hashées
        SmtpServer::create($validatedData);
        
        // Ajoute un message flash de succès et redirige vers la liste des serveurs SMTP
        return redirect()->route('admin.smtp_servers.index')->with('success', 'Le serveur SMTP a été créé avec succès !');
    }

    /**
     * Affiche les détails d'un serveur SMTP spécifique.
     * Pour une interface d'administration, on redirige souvent vers l'édition.
     *
     * @param  \App\Models\SmtpServer  $smtpServer
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function show(SmtpServer $smtpServer)
    {
        // On redirige vers la page d'édition, plus pratique pour l'admin
        return redirect()->route('admin.smtp_servers.edit', $smtpServer);
    }

    /**
     * Affiche le formulaire pour éditer un serveur SMTP existant.
     *
     * @param  \App\Models\SmtpServer  $smtpServer
     * @return \Illuminate\View\View
     */
    public function edit(SmtpServer $smtpServer)
    {
        // Passe le serveur SMTP à la vue 'admin.smtp_servers.edit' pour pré-remplir le formulaire
        return view('admin.smtp_servers.edit', compact('smtpServer'));
    }

    /**
     * Met à jour un serveur SMTP existant dans la base de données.
     * Utilise UpdateSmtpServerRequest pour la validation des données.
     * Le mot de passe est mis à jour seulement s'il est fourni.
     *
     * @param  \App\Http\Requests\Admin\UpdateSmtpServerRequest  $request
     * @param  \App\Models\SmtpServer  $smtpServer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateSmtpServerRequest $request, SmtpServer $smtpServer)
    {
        // Récupère les données validées
        $validatedData = $request->validated();

        // Met à jour le mot de passe seulement s'il est fourni dans la requête
        // Cela permet de ne pas le modifier si le champ est laissé vide lors de l'édition
        if (isset($validatedData['password']) && !empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            // Si le mot de passe n'est pas fourni, on le retire des données validées pour ne pas écraser l'ancien
            unset($validatedData['password']);
        }

        // Met à jour le serveur SMTP avec les données validées
        $smtpServer->update($validatedData);

        // Ajoute un message flash de succès et redirige vers la liste des serveurs SMTP
        return redirect()->route('admin.smtp_servers.index')->with('success', 'Le serveur SMTP a été mis à jour avec succès !');
    }

    /**
     * Supprime un serveur SMTP de la base de données.
     *
     * @param  \App\Models\SmtpServer  $smtpServer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(SmtpServer $smtpServer)
    {
        // Supprime le serveur SMTP
        $smtpServer->delete();

        // Ajoute un message flash de succès et redirige vers la liste des serveurs SMTP
        return redirect()->route('admin.smtp_servers.index')->with('success', 'Le serveur SMTP a été supprimé avec succès !');
    }
}
