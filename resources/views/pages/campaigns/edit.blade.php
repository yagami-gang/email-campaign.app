@extends('layouts.layout')

@section('title', isset($campaign) ? 'Éditer la campagne' : 'Créer une campagne')

@section('content')
  <div class="toolbar">
    {{-- Le titre change dynamiquement en fonction de l'étape --}}
    @if(isset($campaign))
      <div>
        <h2>Éditer la campagne <span class="subtitle">— {{ $campaign->name }}</span></h2>
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

  {{-- Affichage des messages et des erreurs --}}
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

  {{-- On stocke l'état de la campagne pour le réutiliser facilement --}}
  @php
    $isLocked = isset($campaign) && $campaign->status !== 'pending';
  @endphp

  @if($isLocked)
    <div class="alert alert-warning">
        <i class="fa-solid fa-lock"></i>
        <div>
            <strong>Campagne verrouillée</strong>
            <p class="hint" style="margin: 4px 0 0;">Cette campagne n'est plus au statut "pending". Les informations de base et les canaux d'envoi ne peuvent plus être modifiés pour garantir la cohérence des statistiques.</p>
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
          <input id="name" name="name" type="text" value="{{ old('name', $campaign->name ?? '') }}" @disabled($isLocked) required>
        </div>
        <div class="field">
          <label for="subject">Objet du mail</label>
          <input id="subject" name="subject" type="text" value="{{ old('subject', $campaign->subject ?? '') }}" @disabled($isLocked) required>
        </div>
        <div class="field">
          <label for="template_id">Template HTML</label>
          <select id="template_id" name="template_id" @disabled($isLocked) required>
            @foreach($templates as $tpl)
              <option value="{{ $tpl->id }}" @selected(old('template_id', $campaign->template_id ?? '') == $tpl->id)>{{ $tpl->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="shoot_limit">Nombre limite de shoot</label>
          <input id="shoot_limit" name="shoot_limit" type="number" value="{{ old('shoot_limit', $campaign->shoot_limit ?? '0') }}" @disabled($isLocked) required>
          <div class="hint">0 (zéro) pour aucune limite</div>
        </div>
      </div>
    </div>

    {{-- Les sections suivantes ne s'affichent que lors de l'édition (Étape 2) --}}
    @if(isset($campaign))
      {{-- SECTION 2 : Canaux d'envoi (API) --}}
      <div class="card form-section">
        <div class="form-section-header">
          <h3><i class="fa-solid fa-server"></i> Canaux d'envoi (API)</h3>
          <p class="hint">Configurez chaque serveur d'envoi avec ses propres paramètres. Seuls les paramètres de fréquence, de quota et de date sont modifiables après le lancement.</p>
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
                $smtpRows = old('apiEndpoint_rows', $campaign->apiEndpoints->map(function($srv) {
                  return [
                    'api_endpoint_id' => $srv->id, 'sender_name' => $srv->pivot->sender_name,
                    'sender_email' => $srv->pivot->sender_email, 'send_frequency_minutes' => $srv->pivot->send_frequency_minutes,
                    'max_daily_sends' => $srv->pivot->max_daily_sends,
                    'scheduled_at' => $srv->pivot->scheduled_at ? \Carbon\Carbon::parse($srv->pivot->scheduled_at)->format('Y-m-d\TH:i') : null,
                  ];
                })->toArray() ?? []);
              @endphp
              @forelse($smtpRows as $i => $r)
                <tr class="smtp-row">
                  <td><input type="text" name="apiEndpoint_rows[{{ $i }}][sender_name]" value="{{ $r['sender_name'] ?? '' }}" @disabled($isLocked) required></td>
                  <td><input type="email" name="apiEndpoint_rows[{{ $i }}][sender_email]" value="{{ $r['sender_email'] ?? '' }}" @disabled($isLocked) required></td>
                  <td>
                    <select name="apiEndpoint_rows[{{ $i }}][api_endpoint_id]" @disabled($isLocked) required>
                      @foreach($apiEndpoints as $opt)
                        <option value="{{ $opt->id }}" @selected(($r['api_endpoint_id'] ?? null) == $opt->id)>{{ $opt->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" min="1" step="1" name="apiEndpoint_rows[{{ $i }}][send_frequency_minutes]" value="{{ $r['send_frequency_minutes'] ?? '' }}" placeholder="Ex: 5"></td>
                  <td><input type="number" min="1" step="1" name="apiEndpoint_rows[{{ $i }}][max_daily_sends]" value="{{ $r['max_daily_sends'] ?? '' }}" placeholder="Ex: 1000"></td>
                  <td><input type="datetime-local" name="apiEndpoint_rows[{{ $i }}][scheduled_at]" value="{{ $r['scheduled_at'] ?? '' }}"></td>
                  <td class="table-actions">
                    <button type="button" class="btn danger btn-icon del-row" title="Retirer cette ligne" @disabled($isLocked)><i class="fa-solid fa-trash"></i></button>
                  </td>
                </tr>
              @empty
              @endforelse
            </tbody>
          </table>
        </div>
        {{-- On n'affiche le bouton "Ajouter" que si la campagne n'est pas verrouillée --}}
        @if(!$isLocked)
        <div style="margin-top:12px">
          <button type="button" class="btn" id="addSmtpRow"><i class="fa-solid fa-plus"></i> Ajouter un canal d'envoi</button>
        </div>
        @endif
      </div>

      {{-- SECTION 3 : Fichiers de contacts --}}
      <div class="card form-section">
        <div class="form-section-header">
          <h3><i class="fa-solid fa-file-lines"></i> Fichiers de contacts</h3>
          <p class="hint">Sélectionnez un ou plusieurs fichiers JSON. Cette action réimportera tous les contacts et réinitialisera les statistiques.</p>
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
          <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
        @else
          <i class="fa-solid fa-arrow-right"></i> Passer à l'étape suivante
        @endif
      </button>
      <a href="{{ route('admin.campaigns.index') }}" class="btn">Annuler</a>
    </div>
  </form>

  {{-- Template pour les nouvelles lignes (uniquement si non verrouillé) --}}
  @if(isset($campaign) && !$isLocked)
  <template id="smtpRowTemplate">
    <tr class="smtp-row">
      <td><input type="text" name="__NAME__[sender_name]" required></td>
      <td><input type="email" name="__NAME__[sender_email]" required></td>
      <td>
        <select name="__NAME__[api_endpoint_id]" required>
          @foreach($apiEndpoints as $opt)
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
  @endif
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  const smtpTbody = document.getElementById('smtpRows');
  const addSmtpBtn = document.getElementById('addSmtpRow');
  const smtpTplElement = document.getElementById('smtpRowTemplate');

  // Si on est en mode verrouillé, le bouton et le template n'existent pas, on ne fait rien.
  if (!addSmtpBtn || !smtpTplElement) return;

  const smtpTpl = smtpTplElement.innerHTML;
  let rowIndex = smtpTbody.querySelectorAll('tr').length;

  const addRow = () => {
    const html = smtpTpl.replaceAll('__NAME__', `apiEndpoint_rows[${rowIndex}]`);
    smtpTbody.insertAdjacentHTML('beforeend', html);
    rowIndex++;
  };

  addSmtpBtn.addEventListener('click', addRow);
  smtpTbody.addEventListener('click', function(e){
    const delBtn = e.target.closest('.del-row');
    if (delBtn && !delBtn.disabled) { // Vérifie aussi que le bouton n'est pas désactivé
      delBtn.closest('tr').remove();
    }
  });

  if (smtpTbody.children.length === 0) {
    addRow();
  }
});
</script>
@endsection

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
    .alert-warning { background-color: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.3); color: #fde68a; }
    .alert-warning i { font-size: 18px; padding-top: 2px; color: var(--warn); }
    .alert-success { background-color: rgba(34, 197, 94, 0.08); border-color: rgba(34, 197, 94, 0.3); color: #bbf7d0; }
    .alert-success i { color: var(--ok); }
    /* Styles pour les champs désactivés */
    input:disabled, select:disabled, button:disabled {
        background-color: var(--border) !important;
        color: var(--muted) !important;
        cursor: not-allowed;
        opacity: 0.6;
    }
</style>
@endsection
