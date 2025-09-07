@extends('layouts.layout')

@section('title', 'Rapport de la campagne')

@section('content')
  <div class="toolbar">
    <div>
      <h2 style="margin:0">Rapport : {{ $campaign->name }}</h2>
      <p class="hint" style="margin-top:4px;">Statistiques de performance en temps réel de votre campagne.</p>
    </div>
    <a class="btn" href="{{ route('admin.campaigns.index') }}"><i class="fa-solid fa-rectangle-list"></i> Retour à la liste</a>
  </div>

  @php
    // --- Les calculs PHP restent identiques ---
    $importedCount = (int)($metrics['imported_count'] ?? 0);
    $sentCount = (int)($metrics['sent_count'] ?? 0);
    $deliveredCount = (int)($metrics['delivered_count'] ?? 0);
    $openCount = (int)($metrics['open_count'] ?? 0);
    $clickCount = (int)($metrics['click_count'] ?? 0);
    $unsubscribeCount = (int)($metrics['unsubscribe_count'] ?? 0);

    $denominatorDelivered = max(1, $deliveredCount);
    $openRate = round(($openCount / $denominatorDelivered) * 100, 1);
    $clickRate = round(($clickCount / $denominatorDelivered) * 100, 1);
    $ctor = $openCount > 0 ? round(($clickCount / $openCount) * 100, 1) : 0;
  @endphp

  {{-- PREMIÈRE RANGÉE DE 3 CARTES KPI --}}
  <div class="grid cols-3 kpi-grid">

    <x-kpi-card
        icon="fa-users"
        label="Contacts Importés"
        value="{{ number_format($importedCount) }}"
        hint="Taille totale de l'audience."
    />

    <x-kpi-card
        icon="fa-envelope-circle-check"
        label="Emails Délivrés"
        value="{{ number_format($deliveredCount) }}"
        hint="{{ number_format($sentCount) }} tentatives d'envoi."
        progressColor="var(--pri)"
    />

    <x-kpi-card
        icon="fa-envelope-open-text"
        label="Taux d'Ouverture"
        value="{{ $openRate }}%"
        progress="{{ $openRate }}"
        hint="{{ number_format($openCount) }} ouvertures uniques."
        progressColor="var(--ok)"
    />
  </div>

  {{-- DEUXIÈME RANGÉE DE 3 CARTES KPI --}}
  <div class="grid cols-3 kpi-grid">

    <x-kpi-card
        icon="fa-mouse-pointer"
        label="Taux de Clic (CTR)"
        value="{{ $clickRate }}%"
        progress="{{ $clickRate }}"
        hint="{{ number_format($clickCount) }} clics uniques (CTOR : {{ $ctor }}%)."
        progressColor="var(--warn)"
    />

    <x-kpi-card
        icon="fa-user-slash"
        label="Désinscriptions"
        value="{{ number_format($unsubscribeCount) }}"
        hint="Contacts ayant demandé à ne plus recevoir d'emails."
        progressColor="var(--danger)"
    />

    {{-- Carte placeholder pour compléter la grille --}}
    <div class="card kpi-card is-placeholder"></div>

  </div>

@endsection

@section('styles')
<style>
    .kpi-grid {
        margin-bottom: 16px;
    }

    /* Les styles pour .kpi-card, .kpi-header, etc. restent les mêmes */
    .kpi-card {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .kpi-header {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .kpi-icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: var(--r-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border);
        color: var(--icon-color, var(--pri));
        font-size: 16px;
    }
    .kpi-label {
        font-weight: 600;
        color: var(--muted);
    }
    .kpi-body {
        flex-grow: 1;
    }
    .kpi-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
    }
    .kpi-value-with-progress {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .kpi-value-with-progress strong.kpi-value {
        font-size: 24px;
        order: -1;
    }
    .kpi-footer.hint {
        padding-top: 12px;
        border-top: 1px solid var(--border);
        font-size: 12px;
    }

    /* Styles pour les barres de progression */
    progress {
        width: 100%;
        height: 8px;
        border-radius: 4px;
        border: none;
        background-color: var(--border);
        --progress-color: var(--pri);
    }
    progress::-webkit-progress-bar {
        background-color: var(--border);
        border-radius: 4px;
    }
    progress::-webkit-progress-value {
        background-color: var(--progress-color);
        border-radius: 4px;
        transition: width 0.4s ease-in-out;
    }
    progress::-moz-progress-bar {
        background-color: var(--progress-color);
        border-radius: 4px;
    }

    /* Carte placeholder (invisible) */
    .is-placeholder {
      background: transparent;
      border-color: transparent;
      box-shadow: none;
    }

    /* --- Amélioration de la grille pour les écrans plus petits (MODIFIÉ) --- */
    @media (max-width: 1200px) {
        /* Applique le style 2 colonnes aux grilles de 4 ET 3 */
        .grid.cols-4, .grid.cols-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
     @media (max-width: 768px) {
        /* Applique le style 1 colonne à toutes les grilles */
        .grid.cols-4, .grid.cols-3, .grid.cols-2 { grid-template-columns: 1fr; }
    }
</style>
@endsection
