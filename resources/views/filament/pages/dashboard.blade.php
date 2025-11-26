<x-filament-panels::page>
    <div class="looker-container">
        <iframe
            src="https://lookerstudio.google.com/embed/reporting/53b0953f-fce1-416a-a207-3188ab6cf5b5/page/p_v3vxjixtwd"
            frameborder="0"
            allowfullscreen
            sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
    </div>

    <style>
        .looker-container {
            position: relative;
            width: 100%;
            height: calc(100vh - 180px);
            /* Ajusta baseado no header do Filament */
            min-height: 600px;
            overflow: hidden;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            background: white;
        }

        .dark .looker-container {
            background: rgb(31 41 55);
        }

        .looker-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 0.75rem;
        }

        /* Mobile: Ocupa mais espaço vertical */
        @media (max-width: 768px) {
            .looker-container {
                height: calc(100vh - 140px);
                min-height: 500px;
            }
        }

        /* Tablet */
        @media (min-width: 769px) and (max-width: 1024px) {
            .looker-container {
                height: calc(100vh - 160px);
                min-height: 550px;
            }
        }

        /* Desktop grande: Mais espaço */
        @media (min-width: 1440px) {
            .looker-container {
                height: calc(100vh - 200px);
                min-height: 550px;
            }
        }
    </style>
</x-filament-panels::page>