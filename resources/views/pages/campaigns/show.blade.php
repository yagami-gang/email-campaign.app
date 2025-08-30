@extends('layouts.layout')

@section('title','Dashboard')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Campagne: {{ $campaign->name }}</h2>
    
  </div>

  @php
      $del = max(1, (int)($metrics['delivered'] ?? 0));
      $opens = (int)($metrics['opens'] ?? 0);
      $clicks = (int)($metrics['clicks'] ?? 0);
      $openRate  = round(($opens / $del) * 100, 1);
      $clickRate = round(($clicks / $del) * 100, 1);
      $ctor      = $opens > 0 ? round(($clicks / max(1,$opens)) * 100, 1) : 0; // Click-To-Open
    @endphp

  {{-- Cards KPI --}}
  <div class="grid cols-3" style="margin-bottom:16px">
    

    <div class="card">
      <div class="field">
        <label><i class="fa-solid fa-paper-plane"></i> Envoyés</label>
        <div style="font-size:26px;font-weight:700">{{ $metrics['sent'] ?? 0 }}</div>
      </div>
      <div class="hint">Tous statuts confondus</div>
    </div>

    <div class="card">
      <div class="field">
        <label><i class="fa-solid fa-envelope-circle-check"></i> Délivrés</label>
        <div style="font-size:26px;font-weight:700">{{ $metrics['delivered'] ?? 0 }}</div>
      </div>
      <div class="hint">Mails acceptés</div>
    </div>

    <div class="card">
      <div class="field">
        <label><i class="fa-solid fa-envelope-open-text"></i> Taux d’ouverture</label>
        <div style="display:flex;align-items:center;gap:10px">
          <progress value="{{ $openRate }}" max="100" style="width:160px"></progress>
          <strong>{{ $openRate }}%</strong>
        </div>
      </div>
      <div class="hint">{{ $opens }} ouvertures / {{ $del }} délivrés</div>
    </div>

  </div>

  <div class="grid cols-3" style="margin-bottom:16px">
    

    <div class="card">
      <div class="field">
        <label><i class="fa-solid fa-envelope-open-text"></i> Taux d’ouverture</label>
        <div style="display:flex;align-items:center;gap:10px">
          <progress value="{{ $openRate }}" max="100" style="width:160px"></progress>
          <strong>{{ $openRate }}%</strong>
        </div>
      </div>
      <div class="hint">{{ $opens }} ouvertures / {{ $del }} délivrés</div>
    </div>

    <div class="card">
      <div class="field">
        <label><i class="fa-solid fa-mouse-pointer"></i> Taux de clic</label>
        <div style="display:flex;align-items:center;gap:10px">
          <progress value="{{ $clickRate }}" max="100" style="width:160px"></progress>
          <strong>{{ $clickRate }}%</strong>
        </div>
      </div>
      <div class="hint">{{ $clicks }} clics / {{ $del }} délivrés (CTR). CTOR: {{ $ctor }}%</div>
    </div>

    <div class="card">
      @php
        $bounces = (int)($metrics['bounces'] ?? 0);
        $unsubs  = (int)($metrics['unsubscribes'] ?? 0);
      @endphp
      <div class="field">
        <label><i class="fa-solid fa-triangle-exclamation"></i> Bounces & désinscriptions</label>
        <div class="grid cols-2">
          <div><strong>{{ $bounces }}</strong><div class="hint">Bounces</div></div>
          <div><strong>{{ $unsubs }}</strong><div class="hint">Unsubs</div></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Graphiques --}}
  <div class="grid cols-2">
    <div class="card">
      <div class="field">
        <label><i class="fa-solid fa-chart-line"></i> Ouvertures & clics (par jour)</label>
        <canvas id="lineChart" height="120"></canvas>
      </div>
    </div>

    <div class="card">
      <div class="field">
        <label><i class="fa-solid fa-chart-pie"></i> Répartition statuts</label>
        <canvas id="pieChart" height="120"></canvas>
      </div>
      <div class="hint">Sends / Delivered / Opens / Clicks / Bounces</div>
    </div>
  </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  // Données fournies par le contrôleur
  const labels = @json($timeseries['labels'] ?? []);
  const opens  = @json($timeseries['opens']  ?? []);
  const clicks = @json($timeseries['clicks'] ?? []);
  const sends  = @json($timeseries['sends']  ?? []);

  // Courbe opens/clicks
  const ctx1 = document.getElementById('lineChart');
  if (ctx1 && labels.length) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Ouvertures', data: opens },
          { label: 'Clics', data: clicks },
          { label: 'Envois', data: sends }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  // Camembert statuts
  const ctx2 = document.getElementById('pieChart');
  if (ctx2) {
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: ['Envoyés','Délivrés','Ouvertures','Clics','Bounces'],
        datasets: [{
          data: [
            {{ (int)($metrics['sent'] ?? 0) }},
            {{ (int)($metrics['delivered'] ?? 0) }},
            {{ (int)($metrics['opens'] ?? 0) }},
            {{ (int)($metrics['clicks'] ?? 0) }},
            {{ (int)($metrics['bounces'] ?? 0) }}
          ]
        }]
      },
      options: { plugins: { legend: { position: 'bottom' } } }
    });
  }
</script>
@endsection
