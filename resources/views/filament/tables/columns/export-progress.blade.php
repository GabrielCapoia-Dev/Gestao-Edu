@php
    $value = (int) ($getState() ?? 0);
    $value = max(0, min(100, $value));
@endphp

<div style="min-width: 9rem">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:.75rem;color:#475569;margin-bottom:.25rem">
        <span>{{ $value }}%</span>
    </div>
    <div style="height:.5rem;border-radius:999px;background:#e2e8f0;overflow:hidden">
        <div style="height:100%;width:{{ $value }}%;background:#2563eb;transition:width .25s ease"></div>
    </div>
</div>
