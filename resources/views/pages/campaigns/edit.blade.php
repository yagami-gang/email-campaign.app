@extends('layouts.layout')

@section('title', isset($campaign) ? 'Éditer la campagne' : 'Créer une campagne')
@section('styles')
<style>
    .subtitle { color: var(--muted); font-weight: 400; }
    .form-section { display: flex; flex-direction: column; gap: 20px; margin-bottom: 20px; }
    .form-section-header { padding-bottom: 16px; border-bottom: 1px solid var(--border); }
    .form-section-header h3 { margin: 0 0 4px 0; }
    .form-section-header .hint { margin: 0; }
    .table-responsive-wrapper { overflow-x: auto; margin: -10px; padding: 10px; }
    .sub-table { width: 100%; border-collapse: collapse; }
    .sub-table th { text-align: left; padding: 8px 10px; color: var(--muted); font-size: 12px; border-bottom: 1px solid var(--border); }
    .sub-table td { padding: 8px 10px 8px 10px; vertical-align: top; border-bottom: 1px solid var(--border); }
    .sub-table tr:last-child td { border-bottom: none; }
    .sub-table input, .sub-table select { width: 100%; }
    .btn-icon { padding: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }
    .form-actions { display: flex; gap: 10px; margin-top: 20px; }
    select[multiple] { min-height: 150px; padding: 10px; }
    /* Composants d'alerte */
    .alert { display: flex; align-items: flex-start; gap: 14px; padding: 16px; border-radius: var(--r); border: 1px solid transparent; margin-bottom: 20px; }
    .alert-danger { background-color: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.3); color: #fecaca; }
    .alert-danger i { font-size: 18px; padding-top: 2px; color: var(--danger); }
    [data-theme="light"] .alert-danger { background-color: #fef2f2; color: #991b1b; }
    .alert-success { background-color: rgba(34, 197, 94, 0.08); border-color: rgba(34, 197, 94, 0.3); color: #bbf7d0; }
    .alert-success i { color: var(--ok); }
    [data-theme="light"] .alert-success { background-color: #f0fdf4; color: #15803d; }
  </style>
@endsection
@section('content')
  <div class="toolbar">
    {{-- Le titre change dynamiquement en fonction de l'étape --}}
    @if(isset($campaign))
      <div>
        <h2>Éditer la campagne <span class="subtitle">— Étape 2/2</span></h2>
        <span class="hint">Configuration des sources de données et des canaux d'envoi.</span>
      </div>
    @else
      <div>
        <h2>Nouvelle campagne <span class="subtitle">— Étape 1/2</span></h2>
        <span class="hint">Définissez les informations générales de la campagne.</span>
      </div>
    @endif
    <a class="btn" href="{{ route('admin.campaigns.index') }}"><i class="fa-solid fa-rectangle-list"></i> Retour</a>
  </div>

  {{-- Affichage des messages de session et des erreurs de validation --}}
  @if (session('status'))
    <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> {{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div>
        <strong>Une ou plusieurs erreurs ont été détectées :</strong>
        <ul style="margin:8px 0 0;padding-left:18px">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  <form method="POST" action="{{ isset($campaign) ? route('admin.campaigns.update', $campaign->id) : route('admin.campaigns.store') }}" enctype="multipart/form-data">
    @csrf
    @if(isset($campaign))
      @method('PUT')
    @endif

    {{-- SECTION 1 : Informations Générales --}}
    <div class="card form-section">
      <div class="form-section-header">
        <h3><i class="fa-solid fa-circle-info"></i> Informations générales</h3>
        <p class="hint">Le nom, l'objet et le modèle de votre campagne.</p>
      </div>
      <div class="grid cols-2">
        <div class="field">
          <label for="name">Nom de la campagne</label>
          <input id="name" name="name" type="text" value="{{ old('name', $campaign->name ?? '') }}" required>
        </div>
        <div class="field">
          <label for="subject">Objet du mail</label>
          <input id="subject" name="subject" type="text" value="{{ old('subject', $campaign->subject ?? '') }}" required>
        </div>
        <div class="field" style="grid-column: 1 / -1;">
          <label for="template_id">Template HTML</label>
          <select id="template_id" name="template_id" required>
            @foreach($templates as $tpl)
              <option value="{{ $tpl->id }}" @selected(old('template_id', $campaign->template_id ?? '') == $tpl->id)>{{ $tpl->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>

    {{-- Les sections suivantes ne s'affichent que lors de l'édition (Étape 2) --}}
    @if(isset($campaign))
      {{-- SECTION 2 : Canaux d'envoi (API) --}}
      <div class="card form-section">
        <div class="form-section-header">
          <h3><i class="fa-solid fa-server"></i> Canaux d'envoi (API)</h3>
          <p class="hint">Configurez chaque serveur d'envoi avec ses propres paramètres (expéditeur, fréquence, etc.).</p>
        </div>
        <div class="table-responsive-wrapper">
          <table class="sub-table" style="min-width: 980px;">
            <thead>
              <tr>
                <th>Nom d'expéditeur</th>
                <th>Email d'expéditeur</th>
                <th>Serveur API</th>
                <th>Fréq. (min)</th>
                <th>Max/jour</th>
                <th>Date de départ</th>
                <th class="no-sort" style="width: 50px;"></th>
              </tr>
            </thead>
            <tbody id="smtpRows">
              @php
                // Logique pour récupérer les anciennes données en cas d'erreur de validation
                $smtpRows = old('smtp_rows', $campaign->smtpServers->map(function($srv) {
                  return [
                    'smtp_server_id' => $srv->id, 'sender_name' => $srv->pivot->sender_name,
                    'sender_email' => $srv->pivot->sender_email, 'send_frequency_minutes' => $srv->pivot->send_frequency_minutes,
                    'max_daily_sends' => $srv->pivot->max_daily_sends,
                    'scheduled_at' => $srv->pivot->scheduled_at ? \Carbon\Carbon::parse($srv->pivot->scheduled_at)->format('Y-m-d\TH:i') : null,
                  ];
                })->toArray() ?? []);
              @endphp
              @forelse($smtpRows as $i => $r)
                <tr class="smtp-row">
                  <td><input type="text" name="smtp_rows[{{ $i }}][sender_name]" value="{{ $r['sender_name'] ?? '' }}" required></td>
                  <td><input type="email" name="smtp_rows[{{ $i }}][sender_email]" value="{{ $r['sender_email'] ?? '' }}" required></td>
                  <td>
                    <select name="smtp_rows[{{ $i }}][smtp_server_id]" required>
                      @foreach($smtpServers as $opt)
                        <option value="{{ $opt->id }}" @selected(($r['smtp_server_id'] ?? null) == $opt->id)>{{ $opt->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][send_frequency_minutes]" value="{{ $r['send_frequency_minutes'] ?? '' }}" placeholder="Ex: 5"></td>
                  <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][max_daily_sends]" value="{{ $r['max_daily_sends'] ?? '' }}" placeholder="Ex: 1000"></td>
                  <td><input type="datetime-local" name="smtp_rows[{{ $i }}][scheduled_at]" value="{{ $r['scheduled_at'] ?? '' }}"></td>
                  <td class="table-actions">
                    <button type="button" class="btn danger btn-icon del-row" title="Retirer cette ligne"><i class="fa-solid fa-trash"></i></button>
                  </td>
                </tr>
              @empty
                {{-- La première ligne est ajoutée par JS si aucune n'existe --}}
              @endforelse
            </tbody>
          </table>
        </div>
        <div style="margin-top:12px">
          <button type="button" class="btn" id="addSmtpRow"><i class="fa-solid fa-plus"></i> Ajouter un canal d'envoi</button>
        </div>
      </div>

      {{-- SECTION 3 : Fichiers de contacts --}}
      <div class="card form-section">
        <div class="form-section-header">
          <h3><i class="fa-solid fa-file-lines"></i> Fichiers de contacts</h3>
          <p class="hint">Sélectionnez un ou plusieurs fichiers JSON contenant les contacts de votre campagne.</p>
        </div>
        <div class="field">
          <label for="json_file_path">Fichiers JSON disponibles</label>
          <select id="json_file_path" name="json_file_path[]" required multiple>
            @foreach($jsonFiles as $filePath)
              <option value="{{ $filePath }}" @selected(in_array($filePath, old('json_file_path', $campaign->json_file_path ?? [])))>{{ basename($filePath) }}</option>
            @endforeach
          </select>
           <span class="hint">Maintenez la touche Ctrl (ou Cmd sur Mac) pour en sélectionner plusieurs.</span>
        </div>
      </div>
    @endif

    {{-- Boutons d'action principaux --}}
    <div class="form-actions">
      <button type="submit" class="btn ok">
        @if(isset($campaign))
          <i class="fa-solid fa-floppy-disk"></i> Enregistrer et lancer l'import
        @else
          <i class="fa-solid fa-arrow-right"></i> Passer à l'étape suivante
        @endif
      </button>
      <a href="{{ route('admin.campaigns.index') }}" class="btn">Annuler</a>
    </div>
  </form>

  {{-- Template pour les nouvelles lignes de la table --}}
  <template id="smtpRowTemplate">
    <tr class="smtp-row">
      <td><input type="text" name="__NAME__[sender_name]" required></td>
      <td><input type="email" name="__NAME__[sender_email]" required></td>
      <td>
        <select name="__NAME__[smtp_server_id]" required>
          @foreach($smtpServers as $opt)
            <option value="{{ $opt->id }}">{{ $opt->name }}</option>
          @endforeach
        </select>
      </td>
      <td><input type="number" min="1" step="1" name="__NAME__[send_frequency_minutes]" placeholder="Ex: 5"></td>
      <td><input type="number" min="1" step="1" name="__NAME__[max_daily_sends]" placeholder="Ex: 1000"></td>
      <td><input type="datetime-local" name="__NAME__[scheduled_at]"></td>
      <td class="table-actions">
        <button type="button" class="btn danger btn-icon del-row" title="Retirer cette ligne"><i class="fa-solid fa-trash"></i></button>
      </td>
    </tr>
  </template>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  const smtpTbody = document.getElementById('smtpRows');
  if (!smtpTbody) return; // Ne rien faire si on est sur la page de création simple

  const smtpTpl = document.getElementById('smtpRowTemplate').innerHTML;
  const addSmtpBtn = document.getElementById('addSmtpRow');
  let rowIndex = smtpTbody.querySelectorAll('tr').length;

  const addRow = () => {
    const html = smtpTpl.replaceAll('__NAME__', `smtp_rows[${rowIndex}]`);
    smtpTbody.insertAdjacentHTML('beforeend', html);
    rowIndex++;
  };

  addSmtpBtn.addEventListener('click', addRow);
  smtpTbody.addEventListener('click', function(e){
    const delBtn = e.target.closest('.del-row');
    if (delBtn) {
      delBtn.closest('tr').remove();
    }
  });

  // Si le tableau est vide au chargement, ajouter la première ligne
  if (smtpTbody.children.length === 0) {
    addRow();
  }
});
</script>
@endsection
