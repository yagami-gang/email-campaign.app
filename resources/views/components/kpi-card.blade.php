@props([
    'icon',
    'label',
    'value',
    'hint' => null,
    'progress' => null,
    'progressColor' => 'var(--pri)'
])

<div class="card kpi-card">
    <div class="kpi-header">
        <div class="kpi-icon-wrapper" style="--icon-color: {{ $progressColor }}">
            <i class="fa-solid {{ $icon }}"></i>
        </div>
        <span class="kpi-label">{{ $label }}</span>
    </div>

    <div class="kpi-body">
        @if ($progress !== null)
            <div class="kpi-value-with-progress">
                <progress value="{{ $progress }}" max="100" style="--progress-color: {{ $progressColor }}"></progress>
                <strong class="kpi-value">{{ $value }}</strong>
            </div>
        @else
            <div class="kpi-value">{{ $value }}</div>
        @endif
    </div>

    @if ($hint)
        <div class="kpi-footer hint">
            {{ $hint }}
        </div>
    @endif
</div>
