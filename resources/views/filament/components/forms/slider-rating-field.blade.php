<div class="slider-rating-wrapper" x-data="{ valor: @entangle($getStatePath()).defer }">
    <input
        type="range"
        id="valorSlider"
        class="slider-rating"
        min="0"
        max="10"
        step="1"
        x-model.number="valor"
        @input="document.getElementById('valorDisplay').textContent = valor">
    <div class="slider-labels">
        <span class="label-left">0</span>
        <span class="label-center" id="valorDisplay">{{ $getState() ?? 5 }}</span>
        <span class="label-right">10</span>
    </div>
</div>

<style>
    .slider-rating-wrapper {
        width: 100%;
        padding: 20px 0;
    }

    .slider-rating {
        width: 100%;
        height: 10px;
        border-radius: 5px;
        background: linear-gradient(to right, #ff0000 0%, #ffcc00 50%, #00ff33 100%);
        outline: none;
        -webkit-appearance: none;
        appearance: none;
        cursor: pointer;
    }

    .slider-rating::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: white;
        cursor: pointer;
        border: 3px solid #333;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        transition: all 0.2s ease;
    }

    .slider-rating::-webkit-slider-thumb:hover {
        transform: scale(1.15);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .slider-rating::-moz-range-thumb {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: white;
        cursor: pointer;
        border: 3px solid #333;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .slider-labels {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 12px;
        font-weight: 600;
    }

    .label-left,
    .label-right {
        font-size: 12px;
        color: #666;
    }

    .label-center {
        font-size: 24px;
        font-weight: bold;
        color: #333;
        min-width: 40px;
        text-align: center;
    }
    .dark .label-center,
    .dark .label-left,
    .dark .label-right {
        color: #fff;
    }
</style>