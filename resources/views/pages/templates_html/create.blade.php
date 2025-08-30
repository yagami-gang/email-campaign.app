@extends('layouts.layout')

@section('title','Ajouter un template')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Ajouter un template</h2>
    <a class="btn" href="{{ route('admin.templates.index') }}"><i class="fa-solid fa-rectangle-list"></i> Retour à la liste</a>
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
    <form method="POST" action="{{ route('admin.templates.store') }}" class="grid cols-2" autocomplete="off">
      @csrf

      <div class="field" style="grid-column:1/-1">
        <label for="name"><i class="fa-solid fa-tag"></i> Nom <span class="hint">(obligatoire)</span></label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required>
      </div>

      <div class="field" style="grid-column:1/-1">
        <label for="html_content"><i class="fa-solid fa-file-code"></i> Contenu HTML <span class="hint">(obligatoire)</span></label>
        <textarea id="html_content" name="html_content" required placeholder="<!DOCTYPE html>...">{{ old('html_content') }}</textarea>
        <div class="hint">Tu peux coller ici ton HTML complet (styles inline recommandés pour l’emailing).</div>
      </div>

      <div class="field">
        <label for="is_active"><i class="fa-solid fa-toggle-on"></i> Activer</label>
        <label style="display:flex;align-items:center;gap:8px">
          <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
          <span class="hint">Coché = utilisable dans la création de campagnes</span>
        </label>
      </div>

      <div style="grid-column:1/-1;display:flex;gap:10px">
        <button type="submit" class="btn ok"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <a href="{{ route('admin.templates.index') }}" class="btn"><i class="fa-solid fa-arrow-left"></i> Annuler</a>
      </div>
    </form>
  </div>
@endsection
