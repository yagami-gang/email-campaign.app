@extends('layouts.layout')

@section('title','Serveurs SMTP')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Serveurs SMTP</h2>
    <a class="btn" href="{{ route('admin.smtp_servers.create') }}"><i class="fa-solid fa-plus"></i> Ajouter un serveur</a>
  </div>

  <div class="card">
    <table id="smtp-table" class="display" style="width:100%">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nom</th>
          <th>Host</th>
          <th>Port</th>
          <th>Utilisateur</th>
          <th>Chiffrement</th>
          <th>Actif</th>
          <th class="no-sort">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($smtpServers as $s)
          <tr>
            <td>#{{ $s->id }}</td>
            <td>{{ $s->name }}</td>
            <td>{{ $s->host }}</td>
            <td>{{ $s->port ?? '—' }}</td>
            <td>{{ $s->username ?? '—' }}</td>
            <td>{{ $s->encryption ? strtoupper($s->encryption) : '—' }}</td>
            <td>
              @if($s->is_active)
                <span class="badge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Oui</span>
              @else
                <span class="badge"><i class="fa-regular fa-circle" aria-hidden="true"></i> Non</span>
              @endif
            </td>
            <td class="actions" style="white-space:nowrap">
              <a class="btn" title="Voir" href="{{ route('admin.smtp_servers.show', $s->id) }}">
                <i class="fa-solid fa-eye"></i>
              </a>
              <form method="POST" action="{{ route('admin.smtp_servers.destroy', $s->id) }}" style="display:inline"
                    onsubmit="return confirm('Supprimer ce serveur SMTP ?');">
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
    $('#smtp-table').DataTable({
      pageLength: 10,
      order: [[0, 'desc']],
      columnDefs: [{ targets: 'no-sort', orderable: false }],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
    });
  });
</script>
@endsection
