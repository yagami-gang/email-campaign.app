@extends('layouts.layout')

@section('title', 'Mon Profil')

@section('content')
    <div class="toolbar">
        <div>
            <h2 style="margin:0">Mon Profil</h2>
            <p class="hint" style="margin-top:4px;">Gérez les informations de votre compte, votre mot de passe et vos paramètres de sécurité.</p>
        </div>
    </div>

    {{-- Affichage des messages de succès (status de session) --}}
    @if (session('status'))
        <div class="alert alert-success mb-4">
            <i class="fa-solid fa-check-circle"></i>
            <span>
                @if (session('status') === 'profile-updated')
                    Informations du profil enregistrées.
                @elseif (session('status') === 'password-updated')
                    Mot de passe mis à jour avec succès.
                @else
                    {{ session('status') }}
                @endif
            </span>
        </div>
    @endif

    <div class="grid cols-2">
        {{-- CARTE 1: Informations du Profil (INCHANGÉ) --}}
        <div class="card">
            <header class="card-header">
                <h3>Informations du profil</h3>
                <p class="hint">Mettez à jour le nom et l'adresse e-mail de votre compte.</p>
            </header>
            <form method="post" action="{{ route('admin.profile.update') }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="name">Nom</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                    @error('name')
                        <div class="input-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field mt-4">
                    <label for="email">Adresse e-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                    @error('email')
                        <div class="input-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn">Enregistrer</button>
                </div>
            </form>
        </div>

        {{-- CARTE 2: Mettre à jour le mot de passe (MODIFIÉ) --}}
        <div class="card">
            <header class="card-header">
                <h3>Mettre à jour le mot de passe</h3>
                <p class="hint">Assurez-vous que votre compte utilise un mot de passe long et aléatoire pour rester sécurisé.</p>
            </header>
            {{-- Le formulaire est maintenant géré par Alpine.js pour la visibilité des mots de passe --}}
            <form method="post" action="{{ route('admin.profile.update') }}" x-data="{ showPassword: false }">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="current_password">Mot de passe actuel</label>
                    {{-- On ajoute un conteneur pour l'icône --}}
                    <div class="password-wrapper">
                        <input id="current_password" name="current_password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password">
                        {{-- L'icône change dynamiquement --}}
                        <i class="password-toggle-icon fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" @click="showPassword = !showPassword"></i>
                    </div>
                    @error('current_password', 'updatePassword')
                        <div class="input-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field mt-4">
                    <label for="password">Nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input id="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password">
                        <i class="password-toggle-icon fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" @click="showPassword = !showPassword"></i>
                    </div>
                     @error('password', 'updatePassword')
                        <div class="input-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field mt-4">
                    <label for="password_confirmation">Confirmer le mot de passe</label>
                    <div class="password-wrapper">
                        <input id="password_confirmation" name="password_confirmation" :type="showPassword ? 'text' : 'password'" autocomplete="new-password">
                        <i class="password-toggle-icon fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" @click="showPassword = !showPassword"></i>
                    </div>
                     @error('password_confirmation', 'updatePassword')
                        <div class="input-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
{{-- Styles pour les messages, erreurs, etc. --}}

 <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
 @endsection
