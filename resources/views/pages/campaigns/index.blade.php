@extends('layouts.layout')

@section('title', 'Liste des campagnes')

@section('content')
    <div class="toolbar">
        <h2 style="margin:0">Campagnes</h2>
        <a class="btn" href="{{ route('admin.campaigns.create') }}"><i class="fa-solid fa-plus"></i> Nouvelle campagne</a>
    </div>

    <div class="card" style="padding:0;">
        <table id="campaigns-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Objet</th>
                    <th>Modèle</th>
                    <th class="no-sort">Listes de diffusion</th>
                    <th>Statut</th>
                    <th>Progression</th>
                    <th class="no-sort" style="width:50px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($campaigns as $c)
                    <tr data-campaign-id="{{ $c->id }}" data-campaign-status="{{ $c->status }}">
                        <td>#{{ $c->id }}</td>
                        <td>{{ $c->name }}</td>
                        <td>{{ $c->subject }}</td>
                        <td>
                            <span class="badge">
                                <i class="fa-solid fa-file-code" style="opacity:.7"></i> {{ $c->template->name }}
                            </span>
                        </td>
                        <td>
                            @forelse($c->mailingLists as $mailingList)
                                <span class="badge">{{ $mailingList->name }}</span>
                            @empty
                                <span style="color: var(--muted);">Aucune</span>
                            @endforelse
                        </td>
                        <td>
                            @php
                                $statusMap = [
                                    'draft'     => ['label' => 'Brouillon', 'class' => 'status-draft'],
                                    'scheduled' => ['label' => 'Planifiée', 'class' => 'status-scheduled'],
                                    'running'   => ['label' => 'En cours', 'class' => 'status-running'],
                                    'paused'    => ['label' => 'En pause', 'class' => 'status-paused'],
                                    'completed' => ['label' => 'Terminée', 'class' => 'status-completed'],
                                    'failed'    => ['label' => 'Échec', 'class' => 'status-failed'],
                                ];
                                $current = $statusMap[$c->status] ?? ['label' => $c->status, 'class' => ''];
                            @endphp
                            <span class="badge {{ $current['class'] }}">
                                <i class="fa-solid fa-circle"></i> {{ $current['label'] }}
                            </span>
                        </td>
                        <td>
                            <div class="progress-container" title="{{ (int)$c->progress }}%">
                                <div class="progress-bar">
                                    <div class="progress-value" style="width: {{ (int)$c->progress }}%;"></div>
                                </div>
                                <span class="progress-text">{{ (int)$c->progress }}%</span>
                            </div>
                        </td>
                        <td class="actions" style="text-align:center;">
                            <div class="dropdown-actions">
                                <button class="dropdown-toggle" aria-expanded="false" title="Actions">
                                    <i class="fa-solid fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="{{ route('admin.campaigns.show', $c->id) }}">
                                        <i class="fa-solid fa-eye"></i> Voir
                                    </a>
                                    <a class="dropdown-item" href="{{ route('admin.campaigns.edit', $c->id) }}">
                                        <i class="fa-solid fa-pencil"></i> Editer
                                    </a>

                                    <div class="dropdown-divider"></div>

                                    <button class="dropdown-item campaign-action-btn" data-action="launch" data-id="{{ $c->id }}" data-name="{{ $c->name }}" data-allowed-status="draft,paused,pending">
                                        <i class="fa-solid fa-play"></i> Lancer
                                    </button>
                                    <button class="dropdown-item campaign-action-btn" data-action="pause" data-id="{{ $c->id }}" data-name="{{ $c->name }}" data-allowed-status="running">
                                        <i class="fa-solid fa-pause"></i> Mettre en pause
                                    </button>
                                    <button class="dropdown-item campaign-action-btn" data-action="resume" data-id="{{ $c->id }}" data-name="{{ $c->name }}" data-allowed-status="paused">
                                        <i class="fa-solid fa-sync"></i> Reprendre
                                    </button>
                                    <button class="dropdown-item danger campaign-action-btn" data-action="delete" data-id="{{ $c->id }}" data-name="{{ $c->name }}" data-allowed-status="draft,scheduled,completed,failed,paused">
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
@endsection

@section('scripts')
{{-- SweetAlert2 est recommandé pour les modales de confirmation --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Initialisation de DataTables ---
    const dataTable = $('#campaigns-table').DataTable({
        pageLength: 10,
        order: [[0, 'desc']],
        columnDefs: [{ targets: 'no-sort', orderable: false }],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
    });

    // --- Logique du dropdown ---
    function updateCampaignActionButtons(rowElement) {
        const status = rowElement.getAttribute('data-campaign-status');
        const buttons = rowElement.querySelectorAll('.campaign-action-btn');
        buttons.forEach(button => {
            const allowed = button.getAttribute('data-allowed-status').split(',');
            if (allowed.includes(status)) {
                button.classList.remove('is-disabled');
                button.removeAttribute('disabled');
            } else {
                button.classList.add('is-disabled');
                button.setAttribute('disabled', 'true');
            }
        });
    }

    // Appliquer la logique au chargement de la page pour chaque ligne
    document.querySelectorAll('#campaigns-table tbody tr').forEach(updateCampaignActionButtons);

    // Gérer l'ouverture/fermeture des menus déroulants
    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.dropdown-toggle');
        const openMenus = document.querySelectorAll('.dropdown-menu.show');

        if (toggle) {
            e.preventDefault();
            const menu = toggle.nextElementSibling;
            const isShowing = menu.classList.contains('show');
            openMenus.forEach(m => {
                m.classList.remove('show');
                m.previousElementSibling.setAttribute('aria-expanded', 'false');
            });
            if (!isShowing) {
                menu.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            }
        } else if (!e.target.closest('.dropdown-actions')) {
            openMenus.forEach(m => {
                m.classList.remove('show');
                m.previousElementSibling.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // --- Logique des actions de campagne ---
    const actionConfigs = {
        'delete': { title: 'Supprimer la campagne', verb: 'supprimer', past: 'supprimée', removeRow: true },
        'launch': { title: 'Lancer la campagne', verb: 'lancer', past: 'lancée', newStatus: 'running' },
        'pause': { title: 'Mettre en pause', verb: 'mettre en pause', past: 'mise en pause', newStatus: 'paused' },
        'resume': { title: 'Reprendre la campagne', verb: 'reprendre', past: 'reprise', newStatus: 'running' }
    };

    const statusMap = {
        'draft': { label: 'Brouillon', class: 'status-draft' }, 'scheduled': { label: 'Planifiée', class: 'status-scheduled' },
        'running': { label: 'En cours', class: 'status-running' }, 'paused': { label: 'En pause', class: 'status-paused' },
        'completed': { label: 'Terminée', class: 'status-completed' }, 'failed': { label: 'Échec', class: 'status-failed' }
    };

    async function handleCampaignAction(button) {
        const action = button.dataset.action;
        const config = actionConfigs[action];
        if (!config) return;

        const id = button.dataset.id;
        const name = button.dataset.name;
        const row = button.closest('tr');

        const result = await Swal.fire({
            title: config.title,
            html: `Êtes-vous sûr de vouloir ${config.verb} la campagne "<strong>${name}</strong>" ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: `Oui, ${config.verb} !`,
            cancelButtonText: 'Annuler'
        });

        if (result.isConfirmed) {
            // Remplacer par un véritable appel API (fetch/axios)
            console.log(`Action: ${action} sur campagne ID: ${id}`);
            // Simuler le succès
            const isSuccess = true;

            if (isSuccess) {
                await Swal.fire('Succès !', `La campagne a été ${config.past} avec succès.`, 'success');

                if (config.removeRow) {
                    dataTable.row(row).remove().draw();
                } else if (config.newStatus) {
                    row.dataset.campaignStatus = config.newStatus;
                    const newStatusInfo = statusMap[config.newStatus];

                    const statusCell = Array.from(row.cells).find(cell => cell.querySelector('.badge[class*="status-"]'));
                    if (statusCell) {
                        const newBadge = statusCell.querySelector('.badge');
                        Object.values(statusMap).forEach(s => newBadge.classList.remove(s.class));
                        newBadge.classList.add(newStatusInfo.class);
                        newBadge.innerHTML = `<i class="fa-solid fa-circle"></i> ${newStatusInfo.label}`;
                    }

                    updateCampaignActionButtons(row);
                }
            } else {
                await Swal.fire('Erreur', 'Une erreur est survenue.', 'error');
            }
        }
    }

    // Gestion des clics sur les boutons d'action du menu
    document.getElementById('campaigns-table').addEventListener('click', function(e) {
        const button = e.target.closest('.campaign-action-btn:not(.is-disabled)');
        if (button) {
            handleCampaignAction(button);
        }
    });
});
</script>
@endsection
