<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Désinscription</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
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
    .field{display:flex;flex-direction:column;gap:8px; margin-top: 16px;}
    textarea{
      background:#0b1220;border:1px solid var(--border);color:var(--text);border-radius:var(--r-sm);padding:10px 12px;outline:none;
      width: 100%; min-height: 90px; resize: vertical;
    }
    textarea:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(59,130,246,.18);background:#0f172a}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;border-radius:12px;border:1px solid var(--border);cursor:pointer;
      background:linear-gradient(180deg,rgba(239,68,68,.18),rgba(239,68,68,.05));color:#fecaca;transition:transform .08s,box-shadow .2s;
      font-weight: 600;
    }
    .btn:hover{transform:translateY(-1px)}
    .hint{color:var(--muted);font-size:12px}
    .email-display {
        font-weight: 700;
        color: var(--pri);
        word-break: break-all;
    }
  </style>
</head>
<body>
    <div class="card">
        @if ($isBlacklisted)
            <div style="text-align: center;">
                <i class="fa-solid fa-circle-check" style="font-size: 32px; color: var(--ok); margin-bottom: 16px;"></i>
                <h2 style="font-size: 24px; font-weight: 700; margin:0;">Vous êtes déjà désinscrit</h2>
                <p class="hint" style="margin-top: 8px; font-size: 14px;">
                    L'adresse <span class="email-display">{{ $email }}</span> fait déjà partie de notre liste de suppression. Aucune action n'est requise.
                </p>
            </div>
        @else
            <div style="text-align: center; margin-bottom: 24px;">
                <h2 style="font-size: 24px; font-weight: 700; margin:0;">Confirmer la désinscription</h2>
                <p class="hint" style="margin-top: 8px; font-size: 14px;">
                    Vous êtes sur le point de vous désinscrire avec l'adresse : <br>
                    <span class="email-display">{{ $email }}</span>
                </p>
            </div>
            <form method="POST" action="{{ route('unsubscribe.process') }}">
                @csrf
                <input type="hidden" name="encrypted_email" value="{{ $encryptedEmail }}">
                <input type="hidden" name="campaign_id" value="{{ $campaign_id }}">
                <div style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 24px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn">
                        <i class="fa-solid fa-user-slash"></i>
                        Confirmer ma désinscription
                    </button>
                </div>
            </form>
        @endif
    </div>
</body>
</html>
