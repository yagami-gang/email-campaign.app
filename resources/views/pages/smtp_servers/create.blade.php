@extends('layouts.layout')

@php
    $isEditing = isset($smtpServer);
    $title = $isEditing ? 'Modifier un serveur SMTP' : 'Ajouter un serveur SMTP';
@endphp

@section('title', $title)

@section('content')
    <div class="toolbar">
        <h2 style="margin:0">{{ $title }}</h2>
        <a class="btn" href="{{ route('admin.smtp_servers.index') }}">
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
        <form method="POST" action="{{ $isEditing ? route('admin.smtp_servers.update', $smtpServer->id) : route('admin.smtp_servers.store') }}" autocomplete="off">
            @csrf
            @if($isEditing)
                @method('PUT')
            @endif

            {{-- Nom --}}
            <div class="field">
                <label for="name">
                    <i class="fa-solid fa-tag" style="opacity: 0.7;"></i> Nom
                </label>
                <input id="name" name="name" type="text" value="{{ old('name', $isEditing ? $smtpServer->name : '') }}" placeholder="Ex: Mon Serveur Principal" required>
                <span class="hint">Un nom facile à reconnaître pour vous.</span>
            </div>

            {{-- URL --}}
            <div class="field">
                <label for="url">
                    <i class="fa-solid fa-link" style="opacity: 0.7;"></i> URL
                </label>
                <input id="url" name="url" type="text" placeholder="https://api.smtp.com" value="{{ old('url', $isEditing ? $smtpServer->url : '') }}" required>
            </div>

            {{-- Statut 'Actif' --}}
            <div class="field" style="grid-column: 1 / -1;">
                <label for="is_active">
                    <i class="fa-solid fa-power-off" style="opacity: 0.7;"></i> Statut
                </label>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 5px;">
                    <label class="toggle-switch">
                        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $isEditing ? $smtpServer->is_active : true))>
                        <span class="slider"></span>
                    </label>
                    <span class="hint" style="margin:0">Coché = utilisable pour les envois</span>
                </div>
            </div>

            {{-- Actions --}}
            <div class="form-actions">
                <button type="submit" class="btn ok"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                <a href="{{ route('admin.smtp_servers.index') }}" class="btn">Annuler</a>
            </div>
        </form>
    </div>
@endsection
