<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmtpServer;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\StoreSmtpServerRequest;
use App\Http\Requests\Admin\UpdateSmtpServerRequest;

class SmtpServerController extends Controller
{
    /**
     * Affiche une liste de tous les serveurs SMTP.
     */
    public function index()
    {
        $smtpServers = SmtpServer::all();
        return view('pages.smtp_servers.index', compact('smtpServers'));
    }

    /**
     * Affiche le formulaire pour créer un nouveau serveur SMTP.
     */
    public function create()
    {
        return view('pages.smtp_servers.create');
    }

    /**
     * Stocke un nouveau serveur SMTP dans la base de données.
     * Utilise StoreSmtpServerRequest pour la validation des données.
     */
    public function store(StoreSmtpServerRequest $request)
    {
        $validatedData = $request->validated();

        $validatedData['is_active'] = $request->boolean('is_active');

        SmtpServer::create($validatedData);

        return redirect()->route('admin.smtp_servers.index')->with('success', 'Le serveur SMTP a été créé avec succès !');
    }

    /**
     * Affiche les détails d'un serveur SMTP spécifique.
     */
    public function show(SmtpServer $smtpServer)
    {
        return redirect()->route('admin.smtp_servers.edit', $smtpServer);
    }

    /**
     * Affiche le formulaire pour éditer un serveur SMTP existant.
     */
    public function edit(SmtpServer $smtpServer)
    {
        return view('pages.smtp_servers.create', compact('smtpServer'));
    }

    /**
     * Met à jour un serveur SMTP existant dans la base de données.
     * Utilise UpdateSmtpServerRequest pour la validation des données.
     */
    public function update(UpdateSmtpServerRequest $request, SmtpServer $smtpServer)
    {
        $validatedData = $request->validated();

        $validatedData['is_active'] = $request->boolean('is_active');
        
        // On ne la met à jour que si une nouvelle valeur est fournie.
        if (empty($validatedData['api_key'])) {
            unset($validatedData['api_key']);
        }
        $smtpServer->update($validatedData);

        return redirect()->route('admin.smtp_servers.index')->with('success', 'Le serveur SMTP a été mis à jour avec succès !');
    }

    /**
     * Supprime un serveur SMTP de la base de données.
     */
    public function destroy(SmtpServer $smtpServer)
    {
        $smtpServer->delete();

        return redirect()->route('admin.smtp_servers.index')->with('success', 'Le serveur SMTP a été supprimé avec succès !');
    }
}
