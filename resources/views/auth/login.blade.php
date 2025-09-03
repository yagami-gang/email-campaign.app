<x-guest-layout>
    <div class="card">
        <div style="text-align: center; margin-bottom: 24px;">
            <h2 style="font-size: 24px; font-weight: 700; margin:0;">Connexion</h2>
            <p class="hint" style="margin-top: 4px;">Accédez à votre panneau d'administration.</p>
        </div>

        <!-- Statut de la session (ex: lien de réinitialisation de mot de passe envoyé) -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Adresse Email -->
            <div class="field">
                <label for="email"><i class="fa-solid fa-envelope" style="opacity: 0.7;"></i> Email</label>
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Mot de passe -->
            <div class="field" style="margin-top: 16px;">
                <label for="password"><i class="fa-solid fa-lock" style="opacity: 0.7;"></i> Mot de passe</label>
                <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Se souvenir de moi -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
                <label for="remember_me" class="inline-flex items-center" style="gap: 8px; cursor:pointer;">
                    <input id="remember_me" type="checkbox" name="remember" class="toggle-checkbox">
                    <span class="hint" style="font-size: 13px;">Se souvenir de moi</span>
                </label>
            </div>

            <div style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 24px; display: flex; justify-content: space-between; align-items: center;">
                <button type="submit" class="btn">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    {{ __('Se connecter') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Styles spécifiques pour cette page, y compris les erreurs et le checkbox personnalisé --}}
    <style>
        .mt-2 ul { list-style: none; margin: 4px 0 0; padding: 0; }
        .mt-2 li { color: var(--danger); font-size: 13px; }

        .mb-4 {
            padding: 12px;
            margin-bottom: 16px;
            border-radius: var(--r-sm);
            background-color: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #bbf7d0;
            font-size: 13px;
        }

        /* Checkbox personnalisé simple */
        .toggle-checkbox {
            appearance: none;
            width: 38px;
            height: 22px;
            border-radius: 999px;
            background-color: var(--border);
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }
        .toggle-checkbox::before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: var(--text);
            top: 3px;
            left: 3px;
            transition: transform 0.2s ease-in-out;
        }
        .toggle-checkbox:checked {
            background-color: var(--ok);
        }
        .toggle-checkbox:checked::before {
            transform: translateX(16px);
        }
    </style>
</x-guest-layout>
