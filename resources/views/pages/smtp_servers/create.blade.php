@extends('layouts.layout')

@section('title','Ajouter un serveur SMTP')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Ajouter un serveur SMTP</h2>
    <a class="btn" href="{{ route('admin.smtp_servers.index') }}"><i class="fa-solid fa-rectangle-list"></i> Retour à la liste</a>
  </div>

  @if ($errors->any())
    <div class="card" style="border-color:#ef4444">
      <ul style="margin:0;padding-left:18px">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
    <br>
  @endif

  <div class="card">
    <form method="POST" action="{{ route('admin.smtp_servers.store') }}" class="grid cols-2" autocomplete="off">
      @csrf

      {{-- Obligatoires --}}
      <div class="field">
        <label for="name"><i class="fa-solid fa-tag"></i> Nom <span class="hint">(obligatoire)</span></label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required>
      </div>

      <div class="field">
        <label for="host"><i class="fa-solid fa-server"></i> Host <span class="hint">(obligatoire)</span></label>
        <input id="host" name="host" type="text" placeholder="mail.mon-domaine.com" value="{{ old('host') }}" required>
      </div>

      {{-- Optionnels --}}
      <div class="field">
        <label for="port"><i class="fa-solid fa-plug"></i> Port</label>
        <input id="port" name="port" type="number" min="1" step="1" placeholder="587" value="{{ old('port') }}">
        <small class="hint">587 (STARTTLS) ou 465 (SSL). Laisse vide pour valeur par défaut côté code.</small>
      </div>

      <div class="field">
        <label for="username"><i class="fa-solid fa-user"></i> Nom d’utilisateur</label>
        <input id="username" name="username" type="text" placeholder="expediteur@mon-domaine.com" value="{{ old('username') }}">
      </div>

      <div class="field">
        <label for="password"><i class="fa-solid fa-key"></i> Mot de passe</label>
        <input id="password" name="password" type="password" value="">
      </div>

      <div class="field">
        <label for="encryption"><i class="fa-solid fa-lock"></i> Chiffrement</label>
        <select id="encryption" name="encryption">
          <option value="" @selected(old('encryption')==='')>Aucun</option>
          <option value="tls" @selected(old('encryption')==='tls')>TLS (587)</option>
          <option value="ssl" @selected(old('encryption')==='ssl')>SSL (465)</option>
        </select>
      </div>

      <div class="field">
        <label for="is_active"><i class="fa-solid fa-toggle-on"></i> Activer</label>
        <label style="display:flex;align-items:center;gap:8px">
          <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
          <span class="hint">Coché = utilisable pour les envois</span>
        </label>
      </div>

      <div style="grid-column:1/-1;display:flex;gap:10px">
        <button type="submit" class="btn ok"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <a href="{{ route('admin.smtp_servers.index') }}" class="btn"><i class="fa-solid fa-arrow-left"></i> Annuler</a>
      </div>
    </form>
  </div>
@endsection
