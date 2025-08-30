@extends('layouts.layout')

@section('title','Éditer la campagne')

@section('content')
  <div class="toolbar">
    <h2 style="margin:0">Éditer la campagne — Étape 2/2</h2>
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
    {{-- Un seul formulaire qui met à jour la campagne ET toutes les lignes pivot --}}
    <form method="POST" action="{{ route('admin.campaigns.update', $campaign->id) }}">
      @csrf
      @method('PUT')

      {{-- ====== SECTION 1 : Infos générales de la campagne ====== --}}
      <h3 style="margin-top:0"><i class="fa-solid fa-circle-info"></i> Informations générales</h3>
      <div class="grid cols-2">
        <div class="field">
          <label for="name"><i class="fa-solid fa-tag"></i> Nom de la campagne</label>
          <input id="name" name="name" type="text" value="{{ old('name', $campaign->name) }}" required>
        </div>

        <div class="field">
          <label for="subject"><i class="fa-solid fa-envelope-open-text"></i> Objet du mail</label>
          <input id="subject" name="subject" type="text" value="{{ old('subject', $campaign->subject) }}" required>
        </div>

        <div class="field">
          <label for="template_id"><i class="fa-solid fa-layer-group"></i> Template HTML</label>
          <select id="template_id" name="template_id" required>
            @foreach($templates as $tpl)
              <option value="{{ $tpl->id }}" @selected(old('template_id', $campaign->template_id)==$tpl->id)>{{ $tpl->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label for="nbre_contacts"> Nombre limite de shoot</label>
          <input id="nbre_contacts" name="nbre_contacts" type="text" value="{{ old('nbre_contacts', $campaign->nbre_contacts) }}" required>
        </div>
      </div>

      <hr style="margin:24px 0;border-color:var(--border)">

      {{-- ====== SECTION 2 : Lignes pivot (serveurs SMTP + paramètres) ====== --}}
      <h3 style="margin-top:0"><i class="fa-solid fa-server"></i> Serveurs SMTP & paramètres d’envoi</h3>
      <div class="hint" style="margin-bottom:8px">
        Associe un ou plusieurs serveurs SMTP à cette campagne. Chaque ligne ci-dessous alimente la table
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
            {{-- Lignes existantes (pivot) --}}
            @php $rows = old('smtp_rows', []); @endphp

            @if(empty($rows))
              @foreach($campaign->smtpServers as $i => $srv)
                @php
                  $p = $srv->pivot;
                  $dt = $p->scheduled_at ? \Illuminate\Support\Carbon::parse($p->scheduled_at)->format('Y-m-d\TH:i') : '';
                @endphp
                <tr class="smtp-row">
                  <td style="min-width:220px">
                    <select name="smtp_rows[{{ $i }}][smtp_server_id]" required>
                      @foreach($smtpServers as $opt)
                        <option value="{{ $opt->id }}" @selected($opt->id==$srv->id)>{{ $opt->name }} — {{ $opt->host }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="text" name="smtp_rows[{{ $i }}][sender_name]" value="{{ $p->sender_name }}"></td>
                  <td><input type="email" name="smtp_rows[{{ $i }}][sender_email]" value="{{ $p->sender_email }}"></td>
                  <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][send_frequency_minutes]" value="{{ (int)$p->send_frequency_minutes }}"></td>
                  <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][max_daily_sends]" value="{{ (int)$p->max_daily_sends }}"></td>
                  <td><input type="datetime-local" name="smtp_rows[{{ $i }}][scheduled_at]" value="{{ $dt }}"></td>
                  <td>
                    <select name="smtp_rows[{{ $i }}][status]">
                      @php $statuses = ['draft'=>'Brouillon','scheduled'=>'Planifiée','running'=>'En cours','paused'=>'En pause','completed'=>'Terminée','failed'=>'Échec']; @endphp
                      @foreach($statuses as $val=>$label)
                        <option value="{{ $val }}" @selected($p->status===$val)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" min="0" max="100" step="1" name="smtp_rows[{{ $i }}][progress]" value="{{ (int)$p->progress }}"></td>
                  <td><input type="number" min="0" step="1" name="smtp_rows[{{ $i }}][nbre_contacts]" value="{{ (int)$p->nbre_contacts }}"></td>
                  <td class="actions" style="white-space:nowrap">
                    <button type="button" class="btn danger del-row" title="Retirer cette ligne">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              @endforeach
            @else
              {{-- Si validation échouée : on réaffiche les old() --}}
              @foreach($rows as $i => $r)
                <tr class="smtp-row">
                  <td style="min-width:220px">
                    <select name="smtp_rows[{{ $i }}][smtp_server_id]" required>
                      @foreach($smtpServers as $opt)
                        <option value="{{ $opt->id }}" @selected(old("smtp_rows.$i.smtp_server_id")==$opt->id)>{{ $opt->name }} — {{ $opt->host }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="text" name="smtp_rows[{{ $i }}][sender_name]" value="{{ old("smtp_rows.$i.sender_name") }}"></td>
                  <td><input type="email" name="smtp_rows[{{ $i }}][sender_email]" value="{{ old("smtp_rows.$i.sender_email") }}"></td>
                  <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][send_frequency_minutes]" value="{{ old("smtp_rows.$i.send_frequency_minutes") }}"></td>
                  <td><input type="number" min="1" step="1" name="smtp_rows[{{ $i }}][max_daily_sends]" value="{{ old("smtp_rows.$i.max_daily_sends") }}"></td>
                  <td><input type="datetime-local" name="smtp_rows[{{ $i }}][scheduled_at]" value="{{ old("smtp_rows.$i.scheduled_at") }}"></td>
                  <td>
                    @php $statuses = ['draft'=>'Brouillon','scheduled'=>'Planifiée','running'=>'En cours','paused'=>'En pause','completed'=>'Terminée','failed'=>'Échec']; @endphp
                    <select name="smtp_rows[{{ $i }}][status]">
                      @foreach($statuses as $val=>$label)
                        <option value="{{ $val }}" @selected(old("smtp_rows.$i.status")===$val)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" min="0" max="100" step="1" name="smtp_rows[{{ $i }}][progress]" value="{{ old("smtp_rows.$i.progress") }}"></td>
                  <td><input type="number" min="0" step="1" name="smtp_rows[{{ $i }}][nbre_contacts]" value="{{ old("smtp_rows.$i.nbre_contacts") }}"></td>
                  <td class="actions" style="white-space:nowrap">
                    <button type="button" class="btn danger del-row" title="Retirer cette ligne">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              @endforeach
            @endif
          </tbody>
        </table>
      </div>

      <div style="margin-top:12px">
        <button type="button" class="btn" id="addRow"><i class="fa-solid fa-plus"></i> Ajouter un serveur SMTP</button>
      </div>

      <div style="margin-top:18px;display:flex;gap:10px">
        <button type="submit" class="btn ok">
          <i class="fa-solid fa-floppy-disk"></i> Enregistrer toutes les modifications
        </button>
        <a href="{{ route('admin.campaigns.index') }}" class="btn"><i class="fa-solid fa-arrow-left"></i> Annuler</a>
      </div>

      {{-- Template caché pour insertion dynamique de lignes --}}
      <template id="rowTemplate">
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
    </form>
  </div>
@endsection

@section('scripts')
<script>
  (function(){
    const tbody = document.getElementById('smtpRows');
    const tpl = document.getElementById('rowTemplate').innerHTML;
    const addBtn = document.getElementById('addRow');

    function nextIndex(){
      const rows = tbody.querySelectorAll('.smtp-row');
      let max = -1;
      rows.forEach(r => {
        const selects = r.querySelectorAll('select, input');
        selects.forEach(el => {
          const m = (el.name||'').match(/^smtp_rows\[(\d+)\]/);
          if(m){ max = Math.max(max, parseInt(m[1],10)); }
        });
      });
      return max + 1;
    }

    addBtn?.addEventListener('click', function(){
      const i = nextIndex();
      const html = tpl.replaceAll('__NAME__', `smtp_rows[${i}]`);
      const tr = document.createElement('tbody'); // wrapper temporaire
      tr.innerHTML = html.trim();
      tbody.appendChild(tr.firstElementChild);
    });

    // suppression d'une ligne (DOM only)
    tbody?.addEventListener('click', function(e){
      if(e.target.closest('.del-row')){
        const row = e.target.closest('tr.smtp-row');
        if(row) row.remove();
      }
    });
  })();
</script>
@endsection
