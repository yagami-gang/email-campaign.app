@extends('layouts.layout')

@section('title','Liste des campagnes')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Campagnes</h2>
    <a class="btn" href="{{ route('admin.campaigns.create') }}"><i class="fa-solid fa-plus"></i> Nouvelle campagne</a>
  </div>

  <div class="card">
    <table id="campaigns-table" class="display" style="width:100%">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nom</th>
          <th>Objet</th>
          
          <th>Template</th>
          
          <th>Statut</th>
          <th>Progress</th>
          <th class="no-sort">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($campaigns as $c)
          <tr>
            <td>#{{ $c->id }}</td>
            <td>{{ $c->name }}</td>
            <td>{{ $c->subject }}</td>
            <td><span class="badge"><i class="fa-solid fa-file-code"></i> {{ $c->template->name }}</span></td>
            
           
            <td>
              @php
                $labels = ['draft'=>'Brouillon','scheduled'=>'Planifiée','running'=>'En cours','paused'=>'En pause','completed'=>'Terminée','failed'=>'Échec'];
              @endphp
              <span class="badge">
                <i class="fa-solid fa-traffic-light"></i> {{ $labels[$c->status] ?? $c->status }}
              </span>
            </td>
            <td>
              <div title="{{ (int)$c->progress }}%">
                <progress value="{{ (int)$c->progress }}" max="100"></progress>
                {{ (int)$c->progress }}%
              </div>
            </td>
            <td class="actions" style="white-space:nowrap">
              <a class="btn" title="Voir" href="{{ route('admin.campaigns.show',$c->id) }}">
                <i class="fa-solid fa-eye"></i>
              </a>
              <a class="btn" title="Editer" href="{{ route('admin.campaigns.edit',$c->id) }}">
                <i class="fa-solid fa-pencil"></i>
              </a>
              <form method="POST" action="{{ route('admin.campaigns.destroy',$c->id) }}" style="display:inline"
                    onsubmit="return confirm('Supprimer cette campagne ?');">
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
@endsection

@section('scripts')
<script>
  $(function(){
    $('#campaigns-table').DataTable({
      pageLength: 10,
      order: [[0, 'desc']],
      columnDefs: [{ targets: 'no-sort', orderable: false }],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
    });
  });
</script>
@endsection
