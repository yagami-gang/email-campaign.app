@extends('layouts.layout')

@section('title', 'Contacts de la campagne')

@section('content')
  <div class="toolbar">
    <div>
      <h2 style="margin:0">Contacts : {{ $campaign->name }}</h2>
      <p class="hint" style="margin-top:4px;">Liste des contacts et de leur statut d'envoi pour cette campagne.</p>
    </div>
    <a class="btn" href="{{ route('admin.campaigns.show', $campaign->id) }}"><i class="fa-solid fa-chart-pie"></i> Retour au rapport</a>
  </div>

  <div class="card" style="padding:0;">
    <div class="table-responsive-wrapper">
        <table class="sub-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Email</th>
                    <th>Nom</th>
                    <th style="width: 150px;">Statut</th>
                    <th style="width: 180px;">Ouvert le</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $contact)
                    <tr>
                        <td>#{{ $contact->id }}</td>
                        <td>{{ $contact->email }}</td>
                        <td>{{ $contact->name ?? 'N/A' }}</td>
                        <td>
                            @php
                                $status = $contact->status ?? 'pending';
                                $statusMap = [
                                    'sended'    => ['label' => 'Délivré', 'class' => 'status-ok'],
                                    'fail_http'    => ['label' => 'fail_http', 'class' => 'status-danger'],
                                    'fail_smtp'   => ['label' => 'fail_smtp', 'class' => 'status-muted'],
                                ];
                                $current = $statusMap[$status] ?? ['label' => ucfirst($status), 'class' => 'status-muted'];
                            @endphp
                            <span class="badge {{ $current['class'] }}">
                                <i class="fa-solid fa-circle"></i> {{ $current['label'] }}
                            </span>
                        </td>
                        <td>
                            @if($contact->opened_at)
                                {{ \Carbon\Carbon::parse($contact->opened_at)->format('d/m/Y H:i:s') }}
                            @else
                                <span class="hint">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 24px; color: var(--muted);">
                            Aucun contact trouvé dans cette table.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Affichage des liens de pagination, stylisés pour le thème --}}
    @if ($contacts->hasPages())
    <div class="pagination-footer">
        {{ $contacts->links() }}
    </div>
    @endif
  </div>
@endsection

@section('styles')
{{-- Styles nécessaires pour le tableau et les badges --}}
<style>
    .table-responsive-wrapper { overflow-x: auto; }
    .sub-table { width: 100%; border-collapse: collapse; }
    .sub-table th {
        text-align: left; padding: 12px 22px; color: var(--muted); font-size: 12px;
        text-transform: uppercase; border-bottom: 1px solid var(--border); background-color: rgba(0,0,0,0.1);
    }
    .sub-table td { padding: 14px 22px; vertical-align: middle; border-bottom: 1px solid var(--border); }
    .sub-table tbody tr:last-child td { border-bottom: none; }
    .sub-table tbody tr:hover { background-color: rgba(255,255,255,0.02); }

    /* Styles pour les badges de statut */
    .badge {
        display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
        border-radius: 999px; border: 1px solid; font-size: 12px; font-weight: 500;
    }
    .badge .fa-circle { font-size: 8px; }

    .badge.status-ok { border-color: var(--ok); color: var(--ok); background: radial-gradient(circle, rgba(34,197,94,0.15), transparent 70%); }
    .badge.status-danger { border-color: var(--danger); color: var(--danger); background: radial-gradient(circle, rgba(239,68,68,0.15), transparent 70%); }
    .badge.status-muted { border-color: var(--muted); color: var(--muted); background: radial-gradient(circle, rgba(156,163,175,0.15), transparent 70%); }

    /* Styles pour la pagination */
    .pagination-footer {
        padding: 16px 22px;
        border-top: 1px solid var(--border);
    }
    .pagination { display: flex; list-style: none; padding: 0; margin: 0; }
    .pagination li a, .pagination li span {
        display: block; padding: 8px 12px; margin-right: 4px; border-radius: var(--r-sm);
        color: var(--muted); border: 1px solid transparent; text-decoration: none;
    }
    .pagination li a:hover { border-color: var(--border); color: var(--text); }
    .pagination li.active span { background-color: var(--pri); color: white; font-weight: 600; }
    .pagination li.disabled span { color: #4b5563; }
</style>
@endsection
