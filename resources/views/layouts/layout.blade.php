<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','Admin campagnes')</title>

  {{-- DataTables (CDN) --}}
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  {{-- Font Awesome (CDN) --}}
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root{
      --bg:#0f172a; --sidebar:#0b1220; --card:#111827; --border:#1f2937; --muted:#9ca3af; --text:#e5e7eb;
      --pri:#3b82f6; --pri-2:#2563eb; --ok:#22c55e; --warn:#f59e0b; --danger:#ef4444; --shadow:0 8px 30px rgba(0,0,0,.35);
      --r:14px; --r-sm:10px;
    }
    /* Thème "dim" (un peu plus clair) */
    [data-theme="dim"]{
      --bg:#1f2937; --sidebar:#171e27; --card:#182032; --border:#2b3648; --muted:#a5b4c4; --text:#f1f5f9;
    }
    /* Thème clair */
    [data-theme="light"]{
      --bg:#f8fafc; --sidebar:#ffffff; --card:#ffffff; --border:#e5e7eb; --muted:#475569; --text:#0f172a;
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:radial-gradient(1200px 700px at 10% 0%, #0b1220 0%, var(--bg) 55%);
      color:var(--text);
      font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;
    }
    /* Fond clair pour le thème light */
    [data-theme="light"] body, body[data-theme="light"]{
      background: radial-gradient(1200px 700px at 10% 0%, #f1f5f9 0%, var(--bg) 55%);
    }

    a{color:#c7d2fe;text-decoration:none}
    a:hover{text-decoration:underline}
    [data-theme="light"] a{ color:#2563eb }

    .layout{display:grid;grid-template-columns:260px 1fr;min-height:100dvh}
    @media (max-width: 960px){ .layout{grid-template-columns:1fr} aside.sidebar{position:sticky;top:0;z-index:5} }

    aside.sidebar{
      background:var(--sidebar); border-right:1px solid var(--border); padding:18px 14px;
    }
    .brand{display:flex;align-items:center;gap:10px;margin:10px 10px 22px 6px}
    .brand .dot{width:10px;height:10px;border-radius:50%;background:linear-gradient(135deg,var(--ok),#16a34a);box-shadow:0 0 18px rgba(34,197,94,.6)}
    .brand h1{font-size:16px;margin:0;letter-spacing:.3px}

    .menu a{
      display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:var(--text);
    }
    .menu a:hover{background:rgba(255,255,255,.05)}
    .menu a.active{background:linear-gradient(180deg,rgba(59,130,246,.18),rgba(59,130,246,.06));border:1px solid var(--border)}
    .menu .section{color:var(--muted);font-weight:700;text-transform:uppercase;font-size:12px;margin:16px 10px 6px}

    /* Colonne principale = header + contenu */
    .maincol{display:flex;flex-direction:column;min-width:0}

    /* Header topbar */
    header.topbar{
      position:sticky; top:0; z-index:4;
      display:flex; align-items:center; justify-content:flex-end; gap:12px;
      padding:10px 18px; background:rgba(0,0,0,.18);
      border-bottom:1px solid var(--border); backdrop-filter: blur(6px);
    }
    [data-theme="light"] header.topbar{ background:rgba(255,255,255,.7) }

    .topbar .spacer{flex:1}
    .topbar .user{display:flex;align-items:center;gap:10px;margin-right:auto;color:var(--muted)}
    .theme-switch{display:flex;align-items:center;gap:8px}
    .theme-switch select{
      background:#0b1220; color:var(--text); border:1px solid var(--border); border-radius:10px; padding:8px 10px;
    }
    [data-theme="light"] .theme-switch select{ background:#fff }

    main.content{padding:26px}
    .card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.00));border:1px solid var(--border);
      border-radius:var(--r);box-shadow:var(--shadow);padding:22px}
    .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px}

    .grid{display:grid;gap:16px}
    .cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    @media (max-width: 980px){ .cols-2,.cols-3{grid-template-columns:1fr} }

    .field{display:flex;flex-direction:column;gap:8px}
    .field label{color:var(--muted);font-weight:600}
    .hint{color:var(--muted);font-size:12px}

    input[type="text"],input[type="password"],input[type="email"],input[type="number"],input[type="datetime-local"],select,textarea,input[type="file"]{
      background:#0b1220;border:1px solid var(--border);color:var(--text);border-radius:var(--r-sm);padding:10px 12px;outline:none
    }
    [data-theme="light"] input[type="text"], [data-theme="light"] input[type="email"],
    [data-theme="light"] input[type="number"], [data-theme="light"] input[type="datetime-local"],
    [data-theme="light"] select, [data-theme="light"] textarea, [data-theme="light"] input[type="file"]{
      background:#ffffff;
    }
    textarea{min-height:110px;resize:vertical}
    input:focus,select:focus,textarea:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(59,130,246,.18);background:#0f172a}
    [data-theme="light"] input:focus, [data-theme="light"] select:focus, [data-theme="light"] textarea:focus{
      background:#fff;
    }

    .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;border:1px solid var(--border);cursor:pointer;
      background:linear-gradient(180deg,rgba(59,130,246,.18),rgba(59,130,246,.05));color:#eaf1ff;transition:transform .08s,box-shadow .2s}
    .btn:hover{transform:translateY(-1px)}
    .btn.ok{background:linear-gradient(180deg,rgba(34,197,94,.18),rgba(34,197,94,.05))}
    .btn.danger{background:linear-gradient(180deg,rgba(239,68,68,.18),rgba(239,68,68,.05));color:#fecaca}
    [data-theme="light"] .btn{ color:#0f172a }

    table.dataTable{background:transparent;border-radius:12px;overflow:hidden}
    table.dataTable thead th{background:#0d1324;color:#c7d2fe}
    [data-theme="light"] table.dataTable thead th{background:#e5e7eb;color:#0f172a}
    table.dataTable td, table.dataTable th{border-color:var(--border)}
    .actions a, .actions button{margin-right:8px}

    .badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:12px;color:#d1d5db}
    [data-theme="light"] .badge{ color:#0f172a }
    .dropdown summary{cursor:pointer; padding:10px 12px; border-radius:10px}
    .dropdown[open] summary{background:linear-gradient(180deg,rgba(59,130,246,.18),rgba(59,130,246,.06)); border:1px solid var(--border)}
    .submenu{padding-left:8px; margin-top:6px}
    .submenu a{padding-left:28px}

    /* Styles spécifiques à la page, utilisant les variables du layout */

    /* Statuts des campagnes avec des couleurs thématiques */
    .badge.status-draft, .badge.status-scheduled {
        background: radial-gradient(circle, rgba(156,163,175,0.2), transparent 70%);
        border-color: var(--muted);
        color: var(--muted);
    }
    .badge.status-running { background: radial-gradient(circle, rgba(59,130,246,0.2), transparent 70%); border-color:var(--pri); color:var(--pri); }
    .badge.status-paused { background: radial-gradient(circle, rgba(245,158,11,0.2), transparent 70%); border-color:var(--warn); color:var(--warn); }
    .badge.status-completed { background: radial-gradient(circle, rgba(34,197,94,0.2), transparent 70%); border-color:var(--ok); color:var(--ok); }
    .badge.status-failed { background: radial-gradient(circle, rgba(239,68,68,0.2), transparent 70%); border-color:var(--danger); color:var(--danger); }
    .badge .fa-circle { font-size: 8px; margin-right: 4px; }

    /* Barre de progression */
    .progress-container { display: flex; align-items: center; gap: 8px; min-width: 120px; }
    .progress-bar { flex-grow: 1; height: 6px; background-color: var(--border); border-radius: 3px; overflow: hidden; }
    .progress-value { height: 100%; background-color: var(--pri); border-radius: 3px; transition: width 0.4s ease-in-out; }
    .progress-text { font-size: 12px; color: var(--muted); }

    /* Dropdown d'actions */
    .dropdown-actions { position: relative; display: inline-block; }
    .dropdown-toggle {
        background: transparent; border: none; color: var(--muted); cursor: pointer;
        padding: 8px; border-radius: 50%; width: 32px; height: 32px; transition: background .2s;
    }
    .dropdown-toggle:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    .dropdown-menu {
        display: none; position: relative; right: 0; top: 110%;
        background-color: var(--sidebar); min-width: 180px; box-shadow: var(--shadow);
        z-index: 1000; border-radius: var(--r-sm); border: 1px solid var(--border);
        overflow: hidden; padding: 6px;
        transform: scale(0.95);
    opacity: 0;
    transition: transform 0.1s ease-out, opacity 0.1s ease-out;
    transform-origin: top right;
    pointer-events: none; /* Empêche l'interaction quand il est caché */
    }
    .dropdown-menu.show {
        display: block;
        transform: scale(1);
        opacity: 1;
        pointer-events: auto; /* Autorise l'interaction quand il est visible */
    }
    .dropdown-item {
        color: var(--text); padding: 8px 12px; text-decoration: none; display: flex; align-items: center;
        gap: 10px; background: none; border: none; width: 100%; text-align: left;
        cursor: pointer; transition: background-color 0.2s; border-radius: 8px;
    }
    .dropdown-item i { width: 16px; text-align: center; opacity: .7; }
    .dropdown-item:hover { background-color: rgba(59,130,246,.15); color: #c7d2fe; }
    .dropdown-item.danger:hover { background-color: rgba(239,68,68,.15); color: #fecaca; }
    .dropdown-item.is-disabled { color: var(--muted) !important; cursor: not-allowed; background: none !important; opacity: .5; }
    .dropdown-divider { height: 1px; background-color: var(--border); margin: 6px 0; }

    /* Ajustements pour DataTables avec le thème */
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: var(--r-sm); padding: 6px 8px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button { color: var(--text) !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { background: var(--pri) !important; color: white !important; border-color: var(--pri-2) !important; }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { color: var(--muted); }
    table.dataTable tbody tr:hover { background-color: rgba(255,255,255,0.02); }
    table.dataTable td { padding: 12px 14px; }
    .dropdown-menu.drop-up {
        top: auto; /* On annule la position par défaut */
        bottom: 100%; /* On le place au-dessus du bouton */
        margin-bottom: 6px; /* Un petit espace */
        transform-origin: bottom right; /* On change l'origine de l'animation */
    }
</style>
<style>
    /* Composant d'alerte pour les erreurs */
    .alert {
        display: flex; align-items: flex-start; gap: 14px; padding: 16px;
        border-radius: var(--r); border: 1px solid transparent; margin-bottom: 20px;
    }
    .alert-danger {
        background-color: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.3); color: #fecaca;
    }
    .alert-danger i { font-size: 18px; padding-top: 2px; color: var(--danger); }
    [data-theme="light"] .alert-danger { background-color: #fef2f2; color: #991b1b; }

    /* Composant Interrupteur (Toggle Switch) pour le checkbox "Activer" */
    .toggle-switch { position: relative; display: inline-block; width: 48px; height: 26px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background-color: var(--border); transition: .4s; border-radius: 26px;
    }
    .slider:before {
        position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px;
        background-color: white; transition: .4s; border-radius: 50%;
    }
    input:checked + .slider { background-color: var(--ok); }
    input:focus + .slider { box-shadow: 0 0 1px var(--ok); }
    input:checked + .slider:before { transform: translateX(22px); }

    /* Conteneur pour les boutons du formulaire */
    .form-actions {
        grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 20px;
        padding-top: 20px; border-top: 1px solid var(--border);
    }
</style>

<style>
    /* Styles spécifiques à cette page */
    .table-responsive-wrapper { overflow-x: auto; }
    .table-actions, .table-actions-header { text-align: center !important; width: 50px; }

    /* Badges de statut */
    .badge.status-active {
        background: radial-gradient(circle, rgba(34,197,94,0.2), transparent 70%);
        border-color: var(--ok); color: var(--ok);
    }
    .badge.status-inactive {
        background: radial-gradient(circle, rgba(156,163,175,0.2), transparent 70%);
        border-color: var(--muted); color: var(--muted);
    }
    .badge i { margin-right: 4px; font-size: 11px; }

    /* Styles du Dropdown (cohérents avec les autres pages) */
    .dropdown-actions { position: relative; display: inline-block; }
    .dropdown-toggle {
        background: transparent; border: none; color: var(--muted); cursor: pointer;
        padding: 8px; border-radius: 50%; width: 32px; height: 32px; transition: background .2s;
    }
    .dropdown-toggle:hover { background: rgba(255,255,255,0.05); color: var(--text); }

    .dropdown-menu.show { display: block; }
    .dropdown-item {
        color: var(--text); padding: 8px 12px; text-decoration: none; display: flex; align-items: center;
        gap: 10px; background: none; border: none; width: 100%; text-align: left;
        cursor: pointer; transition: background-color 0.2s; border-radius: 8px;
    }
    .dropdown-item i { width: 16px; text-align: center; opacity: .7; }
    .dropdown-item:hover { background-color: rgba(59,130,246,.15); color: #c7d2fe; }
    .dropdown-item.danger:hover { background-color: rgba(239,68,68,.15); color: #fecaca; }
    .dropdown-divider { height: 1px; background-color: var(--border); margin: 6px 0; }

    /* Styles pour DataTables (thème) */
    .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input { background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: var(--r-sm); padding: 6px 8px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { color: var(--text) !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current, .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { background: var(--pri) !important; color: white !important; border-color: var(--pri-2) !important; }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { color: var(--muted); }
    table.dataTable tbody tr:hover { background-color: rgba(255,255,255,0.02); }
    table.dataTable td { padding: 12px 14px; }
</style>
<style>
    /* Styles généraux pour la page de profil */
    .card-header { padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 24px; }
    .card-header h3 { font-size: 1.1rem; font-weight: 600; margin: 0 0 4px 0; }
    .card-header .hint { margin: 0; }
    .card-footer { display: flex; justify-content: flex-end; align-items: center; padding-top: 24px; margin-top: 24px; border-top: 1px solid var(--border); }
    .mt-4 { margin-top: 16px; }
    .mt-6 { margin-top: 24px; }
    .mt-1 { margin-top: 4px; }
    .mb-4 { margin-bottom: 16px; }
    .input-error { color: var(--danger); font-size: 13px; margin-top: 4px; }

    /* Styles pour le message de succès */
    .alert { display: flex; align-items: flex-start; gap: 14px; padding: 16px; border-radius: var(--r); border: 1px solid transparent; }
    .alert-success { background-color: rgba(34, 197, 94, 0.08); border-color: rgba(34, 197, 94, 0.3); color: #bbf7d0; }
    .alert-success i { color: var(--ok); font-size: 18px; padding-top: 2px; }

</style>
@yield('styles')

</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">
        <span class="dot"></span><h1>Mailing Admin</h1>
      </div>
      @include('partials.aside')
    </aside>

    <div class="maincol">
      {{-- HEADER avec Profil / Thème / Déconnexion --}}
      <header class="topbar">
        <div class="user">
          <i class="fa-regular fa-user"></i>
          <span>
            @auth
              {{ auth()->user()->name }}
            @else
              Utilisateur
            @endauth
          </span>
        </div>

        <div class="theme-switch" title="Changer le thème">
          <i class="fa-solid fa-palette" aria-hidden="true"></i>
          <select id="themeSelect" aria-label="Sélecteur de thème">
            <option value="dark">Sombre</option>
            <option value="dim">Dim</option>
            <option value="light">Clair</option>
          </select>
        </div>

          <a class="btn" href="{{ route('admin.profile.edit') }}"><i class="fa-regular fa-id-badge"></i> Profil</a>


        @if (Route::has('logout'))
          <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none">@csrf</form>
          <button class="btn danger" id="logoutBtn"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</button>
        @endif

      </header>

      <main class="content">
        @yield('content')
      </main>
    </div>
  </div>

  {{-- jQuery + DataTables JS (CDN) --}}
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

  <script>
    // --- Déconnexion (POST)
    document.getElementById('logoutBtn')?.addEventListener('click', function(){
      document.getElementById('logout-form')?.submit();
    });

    // --- Thème (localStorage + data-theme)
    (function(){
      const root = document.documentElement;
      const select = document.getElementById('themeSelect');
      const saved = localStorage.getItem('theme') || 'dark';
      root.setAttribute('data-theme', saved);
      if (select) select.value = saved;

      select?.addEventListener('change', function(){
        const t = this.value || 'dark';
        root.setAttribute('data-theme', t);
        localStorage.setItem('theme', t);
      });
    })();
  </script>

  @yield('scripts')
</body>
</html>
