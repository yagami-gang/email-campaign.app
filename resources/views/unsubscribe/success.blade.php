<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Désinscription Réussie</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    /* ... (copiez-collez le même bloc <style> que dans la vue du formulaire) ... */
    :root{
      --bg:#0f172a; --card:#111827; --border:#1f2937; --muted:#9ca3af; --text:#e5e7eb;
      --pri:#3b82f6; --ok:#22c55e; --danger:#ef4444; --shadow:0 8px 30px rgba(0,0,0,.35);
      --r:14px; --r-sm:10px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:radial-gradient(1200px 700px at 10% 0%, #0b1220 0%, var(--bg) 55%);
      color:var(--text);
      font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;
      display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px;
    }
    .card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.00));border:1px solid var(--border);
      border-radius:var(--r);box-shadow:var(--shadow);padding:32px; width:100%; max-width:500px;
    }
    .hint{color:var(--muted);font-size:12px}
  </style>
</head>
<body>
    <div class="card" style="text-align: center;">
        @if(session('status'))
            <i class="fa-solid fa-circle-check" style="font-size: 32px; color: var(--ok); margin-bottom: 16px;"></i>
            <h2 style="font-size: 24px; font-weight: 700; margin:0;">Désinscription réussie !</h2>
            <p class="hint" style="margin-top: 8px; font-size: 14px;">
                {{ session('status') }} Vous ne recevrez plus de communications de notre part.
            </p>
        @endif
    </div>
</body>
</html>
