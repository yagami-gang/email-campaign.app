<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\StoreTemplateRequest; // Importe la requête de validation pour la création
use App\Http\Requests\Admin\UpdateTemplateRequest; // Importe la requête de validation pour la mise à jour

class TemplateController extends Controller
{
    /**
     * Affiche une liste de tous les templates HTML.
     * C'est la page principale de gestion des templates.
     */
    public function index()
    {
        // Récupère tous les templates de la base de données
        $templates = Template::all();
        // Passe les templates à la vue 'admin.templates.index' pour affichage
        return view('pages.templates_html.index', compact('templates'));
    }

    /**
     * Affiche le formulaire pour créer un nouveau template HTML.
     */
    public function create()
    {
        // Renvoie simplement la vue du formulaire de création
        return view('pages.templates_html.create');
    }

    /**
     * Stocke un nouveau template HTML dans la base de données.
     * Utilise StoreTemplateRequest pour la validation des données.
     *
     * @param  \App\Http\Requests\Admin\StoreTemplateRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreTemplateRequest $request)
    {
        // Crée un nouveau template avec les données validées
        Template::create($request->validated());
        
        // Ajoute un message flash de succès et redirige vers la liste des templates
        return redirect()->route('admin.templates.index')->with('success', 'Le template a été créé avec succès !');
    }

    /**
     * Affiche les détails d'un template HTML spécifique.
     * Pour une interface d'administration, on peut souvent rediriger vers l'édition.
     *
     * @param  \App\Models\Template  $template
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function show(Template $template)
    {
        // On redirige vers la page d'édition, plus pratique pour l'admin
        return redirect()->route('admin.templates.edit', $template);
    }

    /**
     * Affiche le formulaire pour éditer un template HTML existant.
     *
     * @param  \App\Models\Template  $template
     * @return \Illuminate\View\View
     */
    public function edit(Template $template)
    {
        // Passe le template à la vue 'admin.templates.edit' pour pré-remplir le formulaire
        return view('admin.templates.edit', compact('template'));
    }

    /**
     * Met à jour un template HTML existant dans la base de données.
     * Utilise UpdateTemplateRequest pour la validation des données.
     *
     * @param  \App\Http\Requests\Admin\UpdateTemplateRequest  $request
     * @param  \App\Models\Template  $template
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateTemplateRequest $request, Template $template)
    {
        // Met à jour le template avec les données validées
        $template->update($request->validated());

        // Ajoute un message flash de succès et redirige vers la liste des templates
        return redirect()->route('admin.templates.index')->with('success', 'Le template a été mis à jour avec succès !');
    }

    /**
     * Supprime un template HTML de la base de données.
     *
     * @param  \App\Models\Template  $template
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Template $template)
    {
        // Supprime le template
        $template->delete();

        // Ajoute un message flash de succès et redirige vers la liste des templates
        return redirect()->route('admin.templates.index')->with('success', 'Le template a été supprimé avec succès !');
    }
}
