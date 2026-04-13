<x-filament::page>

    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
        <style>
            .container {
                max-width: 1400px;
                margin: 0 auto;
                display: grid;
                grid-template-columns: 3fr 2fr;
                gap: 20px;
            }

            #map {
                height: 600px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }
        </style>
    </head>

    <div class="container mx-auto grid grid-cols-[3fr_2fr] gap-5 max-w-[1400px]">
        <div>
            <div id="map" class="h-[600px] rounded-xl border border-gray-200 shadow-sm"></div>
        </div>

        <div>
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm p-5">
                <div style="padding:1rem">
                    <div id="statusMsg" class="hidden text-sm font-medium text-warning-600 bg-warning-50 border border-warning-200 rounded-lg px-3 py-2 mb-4">
                        Clique no mapa para adicionar o ponto
                    </div>

                    <div class="flex gap-2 mb-4">
                        <button id="btn" onclick="setMode('parada')"
                            class="flex-1 px-3 py-2 rounded-lg border text-sm font-medium transition 
                           bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 
                           hover:bg-gray-50">
                            Visualizar Pontos
                        </button>
                        <button id="btn" onclick="setMode('parada')"
                            class="flex-1 px-3 py-2 rounded-lg border text-sm font-medium transition 
                           bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 
                           hover:bg-gray-50">
                            Visualizar Registros
                        </button>
                    </div>

                    <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-200 pb-2 mb-3">
                        Pontos na Rota
                    </h3>
                    <div id="pointList" class="space-y-2 max-h-72 overflow-y-auto"></div>

                    <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-200 pb-2 mb-3">
                        Lista de registros
                    </h3>
                    <div id="studentList" class="space-y-2 max-h-72 overflow-y-auto"></div>
                </div>
            </div>
            <div style="margin-top: 1rem;" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm p-5">
                <div style="padding:1rem">
                    {{ $this->form }}
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script>
        const map = L.map('map').setView([-23.7666, -53.3121], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    </script>
</x-filament::page>
