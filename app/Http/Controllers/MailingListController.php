<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MailingList;
use App\Models\Contact;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessMailingListImport;
use Illuminate\Support\Facades\Response; // Importation pour les réponses JSON

class MailingListController extends Controller
{
    /**
     * Affiche la liste des mailing lists.
     * Cette méthode sera créée plus tard pour afficher l'interface.
     */
    public function index()
    {
        $mailingLists = MailingList::all();
        return view('admin.mailing_lists.index', compact('mailingLists'));
    }

    /**
     * Affiche le formulaire pour importer une mailing list.
     */
    public function create()
    {
        return view('admin.mailing_lists.create');
    }

    /**
     * Gère l'importation d'une mailing liste au format JSON.
     * Crée l'entrée MailingList et délègue le traitement des contacts à un Job.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function import(Request $request)
    {
        // 1. Validation de la requête HTTP
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:mailing_lists,name',
                'mailing_list_file' => 'required|file|mimes:json|max:10240', // Fichier JSON de max 10MB
            ]);
        } catch (ValidationException $e) {
            // Si c'est une requête AJAX, renvoyer une erreur JSON
            if ($request->expectsJson()) {
                return Response::json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        // 2. Récupération du fichier et de son contenu
        $file = $request->file('mailing_list_file');
        $jsonContent = file_get_contents($file->getRealPath());

        // 3. Crée l'entrée MailingList AVANT de dispatcher le Job
        // Ceci permet de renvoyer l'ID au frontend pour le suivi de progression.
        $mailingList = MailingList::create([
            'name' => $request->name,
            'imported_at' => now(),
            'status' => 'pending', // Statut initial
            'progress' => 0,      // Progression initiale
        ]);

        // 4. Dispatch du Job pour traiter l'importation en arrière-plan
        // On passe l'ID de la mailing list au Job.
        ProcessMailingListImport::dispatch($mailingList->id, $jsonContent);

        // 5. Retourne une réponse JSON immédiate à l'utilisateur
        // Le frontend utilisera mailing_list_id pour interroger le statut.
        if ($request->expectsJson()) {
            return Response::json([
                'message' => 'Mailing liste en cours d\'importation.',
                'mailing_list_id' => $mailingList->id,
                'status' => $mailingList->status,
                'progress' => $mailingList->progress
            ], 202); // 202 Accepted indique que la requête a été acceptée pour traitement.
        }

        return redirect()->back()->with('success', 'Mailing liste en cours d\'importation. Vous serez notifié en cas d\'erreur.');
    }

    /**
     * Récupère le statut et la progression d'une importation de mailing list.
     * Cette méthode sera appelée par le frontend pour mettre à jour la barre de progression.
     *
     * @param int $id L'ID de la mailing liste.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImportProgress(int $id): \Illuminate\Http\JsonResponse
    {
        $mailingList = MailingList::find($id);

        if (!$mailingList) {
            return Response::json(['error' => 'Mailing liste introuvable.'], 404);
        }

        return Response::json([
            'mailing_list_id' => $mailingList->id,
            'name' => $mailingList->name,
            'status' => $mailingList->status,
            'progress' => $mailingList->progress
        ]);
    }
}
