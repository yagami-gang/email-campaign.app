@extends('layouts.layout')

@section('title','Templates HTML')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Templates HTML</h2>
    <a class="btn" href="{{ route('admin.templates.create') }}"><i class="fa-solid fa-plus"></i> Ajouter un template</a>
  </div>

  <div class="card">
    <table id="templates-table" class="display" style="width:100%">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nom</th>
          <th>Aperçu (texte)</th>
          <th class="no-sort">Voir</th>
          <th>Actif</th>
          <th class="no-sort">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($templates as $t)
          <tr>
            <td>#{{ $t->id }}</td>
            <td>{{ $t->name }}</td>
            <td title="Longueur: {{ mb_strlen((string)$t->html_content) }} chars">
              {{ \Illuminate\Support\Str::limit(strip_tags($t->html_content), 100) }}
            </td>

            {{-- Colonne "Voir" : bouton œil ouvrant la modale --}}
            <td class="actions" style="white-space:nowrap">
              <button type="button"
                      class="btn view-template"
                      data-id="{{ $t->id }}"
                      data-name="{{ $t->name }}"
                      title="Voir le rendu">
                <i class="fa-solid fa-eye"></i>
              </button>

              {{-- Stockage sûr du HTML du template en JSON pour la modale --}}
              <script type="application/json" id="tpl-{{ $t->id }}">@json($t->html_content)</script>
            </td>

            <td>
              @if($t->is_active)
                <span class="badge"><i class="fa-solid fa-circle-check"></i> Oui</span>
              @else
                <span class="badge"><i class="fa-regular fa-circle"></i> Non</span>
              @endif
            </td>

            <td class="actions" style="white-space:nowrap">
              <a class="btn" title="Voir (page)"
                 href="{{ route('admin.templates.show', $t->id) }}">
                <i class="fa-solid fa-eye"></i>
              </a>
              <form method="POST" action="{{ route('admin.templates.destroy', $t->id) }}" style="display:inline"
                    onsubmit="return confirm('Supprimer ce template ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn danger" title="Supprimer">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Modale de prévisualisation --}}
  <div id="template-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:50; padding:24px;">
    <div class="card" style="max-width:1000px; margin:0 auto; max-height:90vh; display:flex; flex-direction:column;">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px;">
        <h3 style="margin:0; font-size:16px;">Prévisualisation : <span id="tpl-name"></span></h3>
        <button type="button" class="btn" id="tpl-close"><i class="fa-solid fa-xmark"></i> Fermer</button>
      </div>
      <iframe id="tpl-iframe"
              style="width:100%; height:75vh; border:1px solid var(--border); border-radius:10px; background:#fff;"
              sandbox="allow-forms allow-scripts allow-same-origin">
      </iframe>
    </div>
  </div>
@endsection

@section('scripts')
<script>
  $(function(){
    // DataTables
    $('#templates-table').DataTable({
      pageLength: 10,
      order: [[0,'desc']],
      columnDefs: [{ targets: 'no-sort', orderable: false }],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
    });

    // Ouverture modale
    $(document).on('click', '.view-template', function(){
      const id   = $(this).data('id');
      const name = $(this).data('name');
      const raw  = document.getElementById('tpl-' + id)?.textContent || '""';
      let html   = "";
      try { html = JSON.parse(raw); } catch(e) { html = ""; }

      // Renseigner le titre
      $('#tpl-name').text(name);

      // Injecter le HTML dans l'iframe (srcdoc = rendu fidèle et isolé)
      const iframe = document.getElementById('tpl-iframe');
      iframe.setAttribute('srcdoc', html || '<!doctype html><html><body><p style="font-family:sans-serif">Aucun contenu.</p></body></html>');

      // Afficher la modale
      $('#template-modal').fadeIn(120);
    });

    // Fermeture modale (bouton)
    $('#tpl-close').on('click', function(){
      $('#template-modal').fadeOut(120);
    });

    // Fermeture modale (clic hors carte)
    $('#template-modal').on('click', function(e){
      if (e.target === this) $(this).fadeOut(120);
    });
  });
</script>
@endsection
