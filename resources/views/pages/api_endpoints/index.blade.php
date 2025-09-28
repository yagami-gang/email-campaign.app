@extends('layouts.layout')

@section('title', 'Serveurs SMTP')

@section('content')
    <div class="toolbar">
        <h2>Serveurs API</h2>
        <a class="btn" href="{{ route('admin.api_endpoints.create') }}"><i class="fa-solid fa-plus"></i> Ajouter un serveur</a>
    </div>

    <div class="card" style="padding:0;">
        <div class="table-responsive-wrapper">
            <table id="smtp-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Hôte</th>
                        <th style="width:100px;">Statut</th>
                        <th class="no-sort table-actions-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($apiEndpoints as $s)
                        <tr>
                            <td>#{{ $s->id }}</td>
                            <td>{{ $s->name }}</td>
                            <td>{{ $s->url ?? '—' }}</td>
                            <td>
                                @if($s->is_active)
                                    <span class="badge status-active"><i class="fa-solid fa-check"></i> Actif</span>
                                @else
                                    <span class="badge status-inactive"><i class="fa-solid fa-times"></i> Inactif</span>
                                @endif
                            </td>
                            <td class="table-actions">
                                {{-- Le formulaire de suppression est caché et sera soumis via JS --}}
                                <form id="delete-form-{{ $s->id }}" method="POST" action="{{ route('admin.api_endpoints.destroy', $s->id) }}" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                </form>

                                <div class="dropdown-actions">
                                    <button class="dropdown-toggle" aria-expanded="false" title="Actions">
                                        <i class="fa-solid fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="{{ route('admin.api_endpoints.show', $s->id) }}">
                                            <i class="fa-solid fa-eye"></i> Voir
                                        </a>
                                        <a class="dropdown-item" href="{{ route('admin.api_endpoints.edit', $s->id) }}">
                                            <i class="fa-solid fa-pencil"></i> Modifier
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item danger delete-btn"
                                                data-form-id="delete-form-{{ $s->id }}"
                                                data-server-name="{{ $s->name }}">
                                            <i class="fa-solid fa-trash"></i> Supprimer
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
{{-- SweetAlert2 est nécessaire pour les modales de confirmation --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Initialisation de DataTables ---
    $('#smtp-table').DataTable({
        pageLength: 10,
        order: [[0, 'desc']],
        columnDefs: [{ targets: 'no-sort', orderable: false }],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
    });

    // --- Logique du dropdown ---
    // (Utilise la technique du menu flottant pour éviter d'être coupé par le tableau)
    const closeAllDropdowns = () => {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
        document.querySelectorAll('.dropdown-toggle').forEach(t => t.setAttribute('aria-expanded', 'false'));
    };

    document.getElementById('smtp-table').addEventListener('click', function(e) {
        const toggle = e.target.closest('.dropdown-toggle');
        if (toggle) {
            e.preventDefault();
            const menu = toggle.nextElementSibling;
            const isShowing = menu.classList.contains('show');
            closeAllDropdowns();
            if (!isShowing) {
                menu.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-actions')) {
            closeAllDropdowns();
        }
    });

    // --- Logique de suppression avec SweetAlert2 ---
    document.getElementById('smtp-table').addEventListener('click', async function(e) {
        const deleteButton = e.target.closest('.delete-btn');
        if (deleteButton) {
            e.preventDefault();
            const formId = deleteButton.dataset.formId;
            const serverName = deleteButton.dataset.serverName;

            const result = await Swal.fire({
                title: 'Êtes-vous sûr ?',
                html: `Voulez-vous vraiment supprimer le serveur "<strong>${serverName}</strong>" ?<br><br><span style='color:var(--warn)'>Cette action est irréversible.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer !',
                cancelButtonText: 'Annuler',
                confirmButtonColor: 'var(--danger)',
                background: 'var(--card)',
                color: 'var(--text)'
            });

            if (result.isConfirmed) {
                const form = document.getElementById(formId);
                if (form) {
                    form.submit();
                }
            }
        }
    });
});
</script>
@endsection
