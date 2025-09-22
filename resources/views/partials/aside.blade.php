@php
  $isCamp = request()->routeIs('admin.campaigns.*');
  $isSmtp = request()->routeIs('admin.api_endpoints.*');
  $isTpl  = request()->routeIs('admin.templates.*');
  $isBlk  = request()->routeIs('admin.blacklist.*');
@endphp

<nav class="menu">
  <div class="section">Navigation</div>

  <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
    <i class="fa-solid fa-chart-line" style="width:18px;text-align:center"></i> Dashboard
  </a>

  <details {{ $isCamp ? 'open' : '' }} class="dropdown">
    <summary>
      <i class="fa-solid fa-bullhorn" style="width:18px;text-align:center"></i>
      Campagnes
    </summary>
    <div class="submenu">
      <a href="{{ route('admin.campaigns.index') }}" class="{{ request()->routeIs('admin.campaigns.index') ? 'active' : '' }}">
        <i class="fa-solid fa-rectangle-list"></i> Liste
      </a>
      <a href="{{ route('admin.campaigns.create') }}" class="{{ request()->routeIs('admin.campaigns.create') ? 'active' : '' }}">
        <i class="fa-solid fa-plus"></i> Ajouter
      </a>
    </div>
  </details>

  <details {{ $isSmtp ? 'open' : '' }} class="dropdown">
    <summary>
      <i class="fa-solid fa-server" style="width:18px;text-align:center"></i>
      Serveurs SMTP
    </summary>
    <div class="submenu">
      <a href="{{ route('admin.api_endpoints.index') }}" class="{{ request()->routeIs('admin.api_endpoints.index') ? 'active' : '' }}">
        <i class="fa-solid fa-rectangle-list"></i> Liste
      </a>
      <a href="{{ route('admin.api_endpoints.create') }}" class="{{ request()->routeIs('admin.api_endpoints.create') ? 'active' : '' }}">
        <i class="fa-solid fa-plus"></i> Ajouter
      </a>
    </div>
  </details>

  <details {{ $isTpl ? 'open' : '' }} class="dropdown">
    <summary>
      <i class="fa-solid fa-file-code" style="width:18px;text-align:center"></i>
      Templates HTML
    </summary>
    <div class="submenu">
      <a href="{{ route('admin.templates.index') }}" class="{{ request()->routeIs('admin.templates.index') ? 'active' : '' }}">
        <i class="fa-solid fa-rectangle-list"></i> Liste
      </a>
      <a href="{{ route('admin.templates.create') }}" class="{{ request()->routeIs('admin.templates.create') ? 'active' : '' }}">
        <i class="fa-solid fa-plus"></i> Ajouter
      </a>
    </div>
  </details>

  <details {{ $isBlk ? 'open' : '' }} class="dropdown">
    <summary>
      <i class="fa-solid fa-ban" style="width:18px;text-align:center"></i>
      Blacklist
    </summary>
    <div class="submenu">
      <a href="{{ route('admin.blacklist.index') }}" class="{{ request()->routeIs('admin.blacklist.index') ? 'active' : '' }}">
        <i class="fa-solid fa-rectangle-list"></i> Liste
      </a>
      {{-- Si tu ajoutes plus tard un formulaire d’ajout :
      <a href="{{ route('admin.blacklist.create') }}" class="{{ request()->routeIs('admin.blacklist.create') ? 'active' : '' }}">
        <i class="fa-solid fa-plus"></i> Ajouter
      </a> --}}
    </div>
  </details>
</nav>
