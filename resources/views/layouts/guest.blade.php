<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title','Admin Mailing')</title>

  {{-- Font Awesome (CDN) --}}
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    /* Intégration des variables CSS de votre thème principal */
    :root{
      --bg:#0f172a; --card:#111827; --border:#1f2937; --muted:#9ca3af; --text:#e5e7eb;
      --pri:#3b82f6; --pri-2:#2563eb; --ok:#22c55e; --warn:#f59e0b; --danger:#ef4444; --shadow:0 8px 30px rgba(0,0,0,.35);
      --r:14px; --r-sm:10px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:radial-gradient(1200px 700px at 10% 0%, #0b1220 0%, var(--bg) 55%);
      color:var(--text);
      font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }
    a{color:var(--pri);text-decoration:none}
    a:hover{text-decoration:underline}

    /* Styles pour les composants du formulaire de connexion */
    .card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.00));border:1px solid var(--border);
      border-radius:var(--r);box-shadow:var(--shadow);padding:32px; width:100%; max-width:420px;
    }
    .field{display:flex;flex-direction:column;gap:8px}
    .field label{color:var(--muted);font-weight:600}
    .hint{color:var(--muted);font-size:12px}
    input[type="text"],input[type="email"],input[type="password"]{
      background:#0b1220;border:1px solid var(--border);color:var(--text);border-radius:var(--r-sm);padding:10px 12px;outline:none;
      width: 100%;
    }
    input:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(59,130,246,.18);background:#0f172a}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;border-radius:12px;border:1px solid var(--border);cursor:pointer;
      background:linear-gradient(180deg,rgba(59,130,246,.18),rgba(59,130,246,.05));color:#eaf1ff;transition:transform .08s,box-shadow .2s;
      font-weight: 600;
    }
    .btn.ok{background:linear-gradient(180deg,rgba(34,197,94,.18),rgba(34,197,94,.05))}
    .btn:hover{transform:translateY(-1px)}
  </style>
</head>
<body>
    <div class="brand" style="position: absolute; top: 30px; left: 30px; display:flex; align-items:center; gap:10px;">
        <span class="dot" style="width:10px;height:10px;border-radius:50%;background:linear-gradient(135deg,var(--ok),#16a34a);box-shadow:0 0 18px rgba(34,197,94,.6)"></span>
        <h1 style="font-size:16px;margin:0;letter-spacing:.3px">Mailing Admin</h1>
    </div>

    {{-- Le contenu de la page de connexion sera injecté ici --}}
    {{ $slot }}

</body>
</html>
