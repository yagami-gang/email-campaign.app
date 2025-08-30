@extends('layouts.layout')

@section('title','Blacklist e-mails')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Blacklist</h2>
    {{-- Bouton optionnel si tu ajoutes un create plus tard
    <a class="btn" href="{{ route('admin.blacklist.create') }}"><i class="fa-solid fa-plus"></i> Ajouter</a>
    --}}
  </div>

  <div class="card">
    <table id="blacklist-table" class="display" style="width:100%">
      <thead>
        <tr>
          <th>ID</th>
          <th>E-mail</th>
          <th>Black-listé le</th>
          <th>Template (ID)</th>
          <th class="no-sort">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($blacklistEntries as $b)
          <tr>
            <td>#{{ $b->id }}</td>
            <td>{{ $b->email }}</td>
            <td>
              @if($b->blacklisted_at)
                {{ \Illuminate\Support\Carbon::parse($b->blacklisted_at)->format('d/m/Y H:i') }}
              @else
                —
              @endif
            </td>
            <td>{{ $b->template_id ?? '—' }}</td>
            <td class="actions" style="white-space:nowrap">
              {{-- Page détail (si tu crées la route show) --}}
              <a class="btn" title="Voir" href="{{ route('admin.blacklist.show', $b->id) }}">
                <i class="fa-solid fa-eye"></i>
              </a>

              {{-- Retirer de la blacklist / supprimer --}}
              <form method="POST" action="{{ route('admin.blacklist.destroy', $b->id) }}" style="display:inline"
                    onsubmit="return confirm('Retirer cet e-mail de la blacklist ?');">
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
    $('#blacklist-table').DataTable({
      pageLength: 10,
      order: [[0,'desc']],
      columnDefs: [{ targets: 'no-sort', orderable: false }],
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
    });
  });
</script>
@endsection
