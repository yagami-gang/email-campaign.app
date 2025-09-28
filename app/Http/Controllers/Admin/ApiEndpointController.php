<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiEndpoint;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\StoreApiEndpointRequest;
use App\Http\Requests\Admin\UpdateApiEndpointRequest;

class ApiEndpointController extends Controller
{
    /**
     * Affiche une liste de tous les serveurs SMTP.
     */
    public function index()
    {
        $apiEndpoints = ApiEndpoint::all();
        return view('pages.api_endpoints.index', compact('apiEndpoints'));
    }

    /**
     * Affiche le formulaire pour créer un nouveau Serveurs API.
     */
    public function create()
    {
        return view('pages.api_endpoints.create');
    }

    /**
     * Stocke un nouveau Serveurs API dans la base de données.
     * Utilise StoreApiEndpointRequest pour la validation des données.
     */
    public function store(StoreApiEndpointRequest $request)
    {
        $validatedData = $request->validated();

        $validatedData['is_active'] = $request->boolean('is_active');

        ApiEndpoint::create($validatedData);

        return redirect()->route('admin.api_endpoints.index')->with('success', 'Le Serveurs API a été créé avec succès !');
    }

    /**
     * Affiche les détails d'un Serveurs API spécifique.
     */
    public function show(ApiEndpoint $apiEndpoint)
    {
        return redirect()->route('admin.api_endpoints.edit', $apiEndpoint);
    }

    /**
     * Affiche le formulaire pour éditer un Serveurs API existant.
     */
    public function edit(ApiEndpoint $apiEndpoint)
    {
        return view('pages.api_endpoints.create', compact('apiEndpoint'));
    }

    /**
     * Met à jour un Serveurs API existant dans la base de données.
     * Utilise UpdateApiEndpointRequest pour la validation des données.
     */
    public function update(UpdateApiEndpointRequest $request, ApiEndpoint $apiEndpoint)
    {
        $validatedData = $request->validated();

        $validatedData['is_active'] = $request->boolean('is_active');

        // On ne la met à jour que si une nouvelle valeur est fournie.
        if (empty($validatedData['api_key'])) {
            unset($validatedData['api_key']);
        }
        $apiEndpoint->update($validatedData);

        return redirect()->route('admin.api_endpoints.index')->with('success', 'Le Serveurs API a été mis à jour avec succès !');
    }

    /**
     * Supprime un Serveurs API de la base de données.
     */
    public function destroy(ApiEndpoint $apiEndpoint)
    {
        $apiEndpoint->delete();

        return redirect()->route('admin.api_endpoints.index')->with('success', 'Le Serveurs API a été supprimé avec succès !');
    }
}
