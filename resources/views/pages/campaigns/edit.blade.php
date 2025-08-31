@extends('layouts.layout')

@section('title', isset($campaign) ? 'Éditer la campagne' : 'Créer une campagne')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">{{ isset($campaign) ? 'Éditer la campagne — Étape 2/2' : 'Créer une nouvelle campagne — Étape 1/2' }}</h2>
    <a class="btn" href="{{ route('admin.campaigns.index') }}"><i class="fa-solid fa-rectangle-list"></i> Retour</a>
  </div>

  @if (session('status'))
    <div class="card"><strong>{{ session('status') }}</strong></div><br>
  @endif

  @if ($errors->any())
    <div class="card" style="border-color:#ef4444">
      <ul style="margin:0;padding-left:18px">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div><br>
  @endif

  <div class="card">
    {{-- Formulaire unique pour la création et l'édition --}}
    <form method="POST" action="{{ isset($campaign) ? route('admin.campaigns.update', $campaign->id) : route('admin.campaigns.store') }}">
      @csrf
      @if(isset($campaign))
        @method('PUT')
      @endif

      {{-- ====== SECTION 1 : Infos générales de la campagne ====== --}}
      <h3 style="margin-top:0"><i class="fa-solid fa-circle-info"></i> Informations générales</h3>
      <div class="grid cols-2">
        <div class="field">
          <label for="name"><i class="fa-solid fa-tag"></i> Nom de la campagne</label>
          <input id="name" name="name" type="text" value="{{ old('name', $campaign->name ?? '') }}" required>
        </div>

        <div class="field">
          <label for="subject"><i class="fa-solid fa-envelope-open-text"></i> Objet du mail</label>
          <input id="subject" name="subject" type="text" value="{{ old('subject', $campaign->subject ?? '') }}" required>
        </div>

        <div class="field">
          <label for="template_id"><i class="fa-solid fa-layer-group"></i> Template HTML</label>
          <select id="template_id" name="template_id" required>
            @foreach($templates as $tpl)
              <option value="{{ $tpl->id }}" @selected(old('template_id', $campaign->template_id ?? '')==$tpl->id)>{{ $tpl->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label for="nbre_contacts"> Nombre limite de shoot</label>
          <input id="nbre_contacts" name="nbre_contacts" type="text" value="{{ old('nbre_contacts', $campaign->nbre_contacts ?? '') }}" required>
        </div>
      </div>

      <hr style="margin:24px 0;border-color:var(--border)">

      {{-- ====== SECTION 2 : Lignes pivot (serveurs SMTP + paramètres) ====== --}}
      <h3 style="margin-top:0"><i class="fa-solid fa-server"></i> Serveurs SMTP & paramètres d’envoi</h3>
      <div class="hint" style="margin-bottom:8px">
        Associez un ou plusieurs serveurs SMTP à cette campagne. Chaque ligne ci-dessous alimente la table
        <code>campaign_smtp_server</code> avec ses propres paramètres (expéditeur, fréquence, etc.).
      </div>

      <div style="overflow:auto">
        <table class="display" style="width:100%;min-width:980px">
          <thead>
            <tr>
              <th>Serveur SMTP</th>
              <th>Nom expéditeur</th>
              <th>Email expéditeur</th>
              <th>Freq (min)</th>
              <th>Max/jour</th>
              <th>Départ</th>
              <th class="no-sort">Suppr.</th>
            </tr>
          </thead>
          <tbody id="smtpRows">
            @php
              // Utilisation de l'opérateur null-safe pour éviter l'erreur
              $smtpRows = old('smtp_rows', $campaign?->smtpServers?->map(function($srv) {
                return [
                  'smtp_server_id' => $srv->id,
                  'sender_name' => $srv->pivot->sender_name,
                  'sender_email' => $srv->pivot->sender_email,
                  'send_frequency_minutes' => $srv->pivot->send_frequency_minutes,
                  'max_daily_sends' => $srv->pivot->max_daily_sends,
                  'scheduled_at' => $srv->pivot->scheduled_at ? \Illuminate\Support\Carbon::parse($srv->pivot->scheduled_at)->format('Y-m-d\TH:i') : null,
                ];
              })->toArray() ?? []);
            @endphp
            @foreach($smtpRows as $i => $r)
              <tr class="smtp-row">
                <td style="min-width:220px">
                  <select name="smtp_rows[{{ $i }}][smtp_server_id]" required>
                    @foreach($smtpServers as $opt)
                      <option value="{{ $opt->id }}" @selected($r['smtp_server_id']==$opt->id)>{{ $opt->name }} — {{ $opt->host }}</option>
                    @endforeach
                  </select>
                </td>
                <td><input type="text" name="smtp_rows[{{ $i }}][sender_name]" value="{{ $r['sender_name'] }}"></td>
                <td><input type="email" name="smtp_rows[{{ $i }}][sender_email]" value="{{ $r['sender_email'] }}"></td>
                <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][send_frequency_minutes]" value="{{ $r['send_frequency_minutes'] }}"></td>
                <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][max_daily_sends]" value="{{ $r['max_daily_sends'] }}"></td>
                <td><input type="datetime-local" name="smtp_rows[{{ $i }}][scheduled_at]" value="{{ $r['scheduled_at'] }}"></td>
                <td class="actions" style="white-space:nowrap">
                  <button type="button" class="btn danger del-row" title="Retirer cette ligne">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px">
        <button type="button" class="btn" id="addSmtpRow"><i class="fa-solid fa-plus"></i> Ajouter un serveur SMTP</button>
      </div>

      <hr style="margin:24px 0;border-color:var(--border)">

      {{-- ====== SECTION 3 : Lignes pivot (listes de diffusion) ====== --}}
      <h3 style="margin-top:0"><i class="fa-solid fa-list"></i> Listes de diffusion</h3>
      <div class="hint" style="margin-bottom:8px">
        Associez une ou plusieurs listes de diffusion à cette campagne. Les contacts de ces listes seront envoyés.
      </div>

      <div style="overflow:auto">
        <table class="display" style="width:100%;min-width:400px">
          <thead>
            <tr>
              <th>Liste de diffusion</th>
              <th class="no-sort">Suppr.</th>
            </tr>
          </thead>
          <tbody id="mailingListRows">
            @php
              // Utilisation de l'opérateur null-safe pour éviter l'erreur
              $mailingListRows = old('mailing_list_rows', $campaign?->mailingLists?->map(function($list) {
                return ['mailing_list_id' => $list->id];
              })->toArray() ?? []);
            @endphp
            @foreach($mailingListRows as $i => $r)
              <tr class="mailing-list-row">
                <td>
                  <select name="mailing_list_rows[{{ $i }}][mailing_list_id]" required>
                    @foreach($mailingLists as $opt)
                      <option value="{{ $opt->id }}" @selected($r['mailing_list_id']==$opt->id)>{{ $opt->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td class="actions" style="white-space:nowrap">
                  <button type="button" class="btn danger del-row" title="Retirer cette ligne">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px">
        <button type="button" class="btn" id="addMailingListRow"><i class="fa-solid fa-plus"></i> Ajouter une liste de diffusion</button>
      </div>

      <div style="margin-top:18px;display:flex;gap:10px">
        <button type="submit" class="btn ok">
          <i class="fa-solid fa-floppy-disk"></i> Enregistrer toutes les modifications
        </button>
        <a href="{{ route('admin.campaigns.index') }}" class="btn"><i class="fa-solid fa-arrow-left"></i> Annuler</a>
      </div>

      {{-- Templates cachés pour l'insertion dynamique de lignes --}}
      <template id="smtpRowTemplate">
        <tr class="smtp-row">
          <td style="min-width:220px">
            <select name="__NAME__[smtp_server_id]" required>
              @foreach($smtpServers as $opt)
                <option value="{{ $opt->id }}">{{ $opt->name }} — {{ $opt->host }}</option>
              @endforeach
            </select>
          </td>
          <td><input type="text" name="__NAME__[sender_name]"></td>
          <td><input type="email" name="__NAME__[sender_email]"></td>
          <td><input type="number" min="1" step="1" name="__NAME__[send_frequency_minutes]"></td>
          <td><input type="number" min="1" step="1" name="__NAME__[max_daily_sends]"></td>
          <td><input type="datetime-local" name="__NAME__[scheduled_at]"></td>
          <td class="actions" style="white-space:nowrap">
            <button type="button" class="btn danger del-row" title="Retirer cette ligne">
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>
        </tr>
      </template>

      <template id="mailingListRowTemplate">
        <tr class="mailing-list-row">
          <td>
            <select name="__NAME__[mailing_list_id]" required>
              @foreach($mailingLists as $opt)
                <option value="{{ $opt->id }}">{{ $opt->name }}</option>
              @endforeach
            </select>
          </td>
          <td class="actions" style="white-space:nowrap">
            <button type="button" class="btn danger del-row" title="Retirer cette ligne">
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>
        </tr>
      </template>

    </form>
  </div>
@endsection

@section('scripts')
<script>
  (function(){
    const smtpTbody = document.getElementById('smtpRows');
    const mailingListTbody = document.getElementById('mailingListRows');
    const smtpTpl = document.getElementById('smtpRowTemplate').innerHTML;
    const mailingListTpl = document.getElementById('mailingListRowTemplate').innerHTML;
    const addSmtpBtn = document.getElementById('addSmtpRow');
    const addMailingListBtn = document.getElementById('addMailingListRow');

    /**
     * Calcule le prochain index disponible pour une ligne.
     * @param {HTMLElement} tbody Le corps de la table.
     * @param {string} prefix Le préfixe du nom (ex: 'smtp_rows').
     * @returns {number} Le prochain index.
     */
    function nextIndex(tbody, prefix){
      const rows = tbody.querySelectorAll('tr');
      let max = -1;
      rows.forEach(r => {
        const selects = r.querySelectorAll('select, input');
        selects.forEach(el => {
          const m = (el.name||'').match(new RegExp(`^${prefix}\\[(\\d+)\\]`));
          if(m){ max = Math.max(max, parseInt(m[1],10)); }
        });
      });
      return max + 1;
    }

    // Ajout d'une ligne pour les serveurs SMTP
    addSmtpBtn?.addEventListener('click', function(){
      const i = nextIndex(smtpTbody, 'smtp_rows');
      const html = smtpTpl.replaceAll('__NAME__', `smtp_rows[${i}]`);
      const tr = document.createElement('tbody');
      tr.innerHTML = html.trim();
      smtpTbody.appendChild(tr.firstElementChild);
    });

    // Ajout d'une ligne pour les listes de diffusion
    addMailingListBtn?.addEventListener('click', function(){
      const i = nextIndex(mailingListTbody, 'mailing_list_rows');
      const html = mailingListTpl.replaceAll('__NAME__', `mailing_list_rows[${i}]`);
      const tr = document.createElement('tbody');
      tr.innerHTML = html.trim();
      mailingListTbody.appendChild(tr.firstElementChild);
    });

    // Suppression d'une ligne pour les deux tables
    document.body.addEventListener('click', function(e){
      if(e.target.closest('.del-row')){
        const row = e.target.closest('tr');
        if(row) row.remove();
      }
    });
  })();
</script>
@endsection
