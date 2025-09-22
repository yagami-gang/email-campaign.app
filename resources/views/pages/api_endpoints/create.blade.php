@extends('layouts.layout')

@php
    $isEditing = isset($apiEndpoint);
    $title = $isEditing ? 'Modifier un serveur SMTP' : 'Ajouter un serveur SMTP';
@endphp

@section('title', $title)

@section('content')
    <div class="toolbar">
        <h2 style="margin:0">{{ $title }}</h2>
        <a class="btn" href="{{ route('admin.api_endpoints.index') }}">
            <i class="fa-solid fa-rectangle-list"></i> Retour à la liste
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Une ou plusieurs erreurs ont été détectées :</strong>
                <ul style="margin:8px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ $isEditing ? route('admin.api_endpoints.update', $apiEndpoint->id) : route('admin.api_endpoints.store') }}" autocomplete="off">
            @csrf
            @if($isEditing)
                @method('PUT')
            @endif

            {{-- Nom --}}
            <div class="field">
                <label for="name">
                    <i class="fa-solid fa-tag" style="opacity: 0.7;"></i> Nom
                </label>
                <input id="name" name="name" type="text" value="{{ old('name', $isEditing ? $apiEndpoint->name : '') }}" placeholder="Ex: Mon Serveur Principal" required>
                <span class="hint">Un nom facile à reconnaître pour vous.</span>
            </div>

            {{-- URL --}}
            <div class="field">
                <label for="url">
                    <i class="fa-solid fa-link" style="opacity: 0.7;"></i> URL
                </label>
                <input id="url" name="url" type="text" placeholder="https://api.smtp.com" value="{{ old('url', $isEditing ? $apiEndpoint->url : '') }}" required>
            </div>

            <div class="field" style="grid-column: 1 / -1;"> {{-- On le met sur toute la largeur --}}
                <label for="api_key">
                    <i class="fa-solid fa-key" ></i> Clé API (Header Bearer)
                </label>
                {{-- Le type "password" masque la clé pour la sécurité --}}
                <input id="api_key" name="api_key" value="{{ old('api_key', $isEditing ? $apiEndpoint->api_key : '') }}" type="text" placeholder="•••••••••••••••••••••••••••••">
                <span class="hint">
                    Laissez vide si non requis.
                    @if(isset($apiEndpoint) && $apiEndpoint->api_key)
                        <span style="color:var(--warn)">Une clé est déjà enregistrée. Remplir ce champ l'écrasera.</span>
                    @endif
                </span>
            </div>

            {{-- Statut 'Actif' --}}
            <div class="field" style="grid-column: 1 / -1;">
                <label for="is_active">
                    <i class="fa-solid fa-power-off" style="opacity: 0.7;"></i> Statut
                </label>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 5px;">
                    <label class="toggle-switch">
                        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $isEditing ? $apiEndpoint->is_active : true))>
                        <span class="slider"></span>
                    </label>
                    <span class="hint" style="margin:0">Coché = utilisable pour les envois</span>
                </div>
            </div>

            {{-- Actions --}}
            <div class="form-actions">
                <button type="submit" class="btn ok"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                <a href="{{ route('admin.api_endpoints.index') }}" class="btn">Annuler</a>
            </div>
        </form>
    </div>
@endsection
