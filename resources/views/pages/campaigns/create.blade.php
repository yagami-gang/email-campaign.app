@extends('layouts.layout')

@section('title','Créer une campagne')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Créer une campagne</h2>
    <a class="btn" href="{{ route('admin.campaigns.index') }}"><i class="fa-solid fa-rectangle-list"></i> Retour à la liste</a>
  </div>

  {{-- Erreurs globales (liste) --}}
  @if ($errors->any())
    <div class="card" style="border-color:#ef4444" id="first-error">
      <ul style="margin:0;padding-left:18px">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
    <br>
  @endif

  <div class="card">
    <form method="POST" action="{{ route('admin.campaigns.store') }}" class="grid cols-2" novalidate>
      @csrf

      {{-- Nom de la campagne --}}
      <div class="field">
        <label for="name"><i class="fa-solid fa-tag"></i> Nom de la campagne</label>
        <input id="name" name="name" type="text"
               value="{{ old('name') }}" required
               aria-invalid="@error('name') true @else false @enderror"
               @error('name') style="border-color:#ef4444" @enderror>
        @error('name')
          <small class="hint" style="color:#fca5a5">{{ $message }}</small>
        @enderror
      </div>

      {{-- Objet --}}
      <div class="field">
        <label for="subject"><i class="fa-solid fa-envelope-open-text"></i> Objet du mail</label>
        <input id="subject" name="subject" type="text"
               value="{{ old('subject') }}" required
               aria-invalid="@error('subject') true @else false @enderror"
               @error('subject') style="border-color:#ef4444" @enderror>
        @error('subject')
          <small class="hint" style="color:#fca5a5">{{ $message }}</small>
        @enderror
      </div>

      {{-- Template --}}
      <div class="field">
        <label for="template_id"><i class="fa-solid fa-layer-group"></i> Template HTML</label>
        <select id="template_id" name="template_id" required
                aria-invalid="@error('template_id') true @else false @enderror"
                @error('template_id') style="border-color:#ef4444" @enderror>
          @isset($templates)
            @foreach($templates as $tpl)
              <option value="{{ $tpl->id }}" @selected(old('template_id')==$tpl->id)>{{ $tpl->name }}</option>
            @endforeach
          @else
            <option value="welcome" @selected(old('template_id')==='welcome')>Welcome</option>
            <option value="promo" @selected(old('template_id')==='promo')>Promotion</option>
            <option value="newsletter" @selected(old('template_id')==='newsletter')>Newsletter</option>
          @endisset
        </select>
        @error('template_id')
          <small class="hint" style="color:#fca5a5">{{ $message }}</small>
        @enderror
        <div class="hint">Liste gérée côté back-office.</div>
      </div>

      {{-- shoot_limit (optionnel) --}}
      <div class="field">
        <label for="shoot_limit">Nombre limite de shoot</label>
        <input id="shoot_limit" name="shoot_limit" type="number" required
               value="{{ old('shoot_limit') ?? 0 }}"
               aria-invalid="@error('shoot_limit') true @else false @enderror"
               @error('shoot_limit') style="border-color:#ef4444" @enderror>
        @error('shoot_limit')
          <small class="hint" style="color:#fca5a5">{{ $message }}</small>
        @enderror
        <div class="hint">0 (zéro) pour aucune limite</div>
      </div>

      <div style="grid-column:1/-1;display:flex;gap:10px">
        <button type="submit" class="btn ok"><i class="fa-solid fa-floppy-disk"></i> Suivant</button>
        <a href="{{ route('admin.campaigns.index') }}" class="btn"><i class="fa-solid fa-arrow-left"></i> Annuler</a>
      </div>
    </form>
  </div>
@endsection

@section('scripts')
<script>
  // Scroll automatique vers le premier bloc d’erreur
  const firstError = document.getElementById('first-error');
  if(firstError){ firstError.scrollIntoView({behavior:'smooth', block:'start'}); }
</script>
@endsection
