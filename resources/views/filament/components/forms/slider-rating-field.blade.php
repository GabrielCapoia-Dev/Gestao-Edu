<div
    x-data="{
        value: @js($getState()),
        hoverValue: 0,
        updateValue(newVal) {
            this.value = newVal;
            this.$nextTick(() => $wire.set('{{ $getStatePath() }}', newVal));
        }
    }"
    @wire:update="{{ $getStatePath() }}"="value = $event.detail.payload"
    class="star-rating-container">

    <div class="stars-wrapper">
        <template x-for="star in 5" :key="star">
            <button
                type="button"
                @click="updateValue(star)"
                @mouseenter="hoverValue = star"
                @mouseleave="hoverValue = 0"
                :class="[
                    'star',
                    (hoverValue > 0 ? hoverValue : value) >= star ? 'star-filled' : 'star-empty'
                ]"
                :title="`${star} ${star === 1 ? 'estrela' : 'estrelas'}`"
                aria-label="Avaliar">
                ★
            </button>
        </template>
    </div>

    <div class="rating-info">
        <span class="rating-label">
            <span x-show="value === 0">Clique para avaliar</span>
            <span x-show="value === 1">Péssimo</span>
            <span x-show="value === 2">Ruim</span>
            <span x-show="value === 3">Bom</span>
            <span x-show="value === 4">Muito Bom</span>
            <span x-show="value === 5">Excelente</span>
        </span>
    </div>
</div>

<style>
    .star-rating-container {
        width: 100%;
        padding: 1rem 0;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        align-items: center;
        justify-content: center;
    }

    .stars-wrapper {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        justify-content: center;
    }

    .star {
        background: none;
        border: none;
        font-size: 2.5rem;
        cursor: pointer;
        padding: 0;
        transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 48px;
        min-height: 48px;
        border-radius: 4px;
    }

    .star:hover {
        transform: scale(1.2) rotate(8deg);
    }

    .star:active {
        transform: scale(0.95);
    }

    .star-empty {
        color: #e5e7eb;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .dark .star-empty {
        color: #4b5563;
    }

    .star-filled {
        color: #fbbf24;
        text-shadow: 0 2px 4px rgba(251, 191, 36, 0.3);
        animation: pop 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes pop {
        0% {
            transform: scale(0.8);
        }
        50% {
            transform: scale(1.15);
        }
        100% {
            transform: scale(1);
        }
    }

    .star:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    .rating-info {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border-radius: 6px;
        min-height: 44px;
    }

    .dark .rating-info {
        background: #334155;
    }

    .rating-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #64748b;
    }

    .dark .rating-label {
        color: #cbd5e1;
    }

    @media (max-width: 480px) {
        .star {
            font-size: 2rem;
            min-width: 44px;
            min-height: 44px;
        }

        .stars-wrapper {
            gap: 0.5rem;
        }

        .rating-info {
            flex-direction: row;
            gap: 0.5rem;
            padding: 0.75rem;
            align-items: center;
            justify-content: center;
        }

        .rating-label {
            font-size: 0.8125rem;
        }
    }
</style>