<?php

return [
    /*
     * json: mantém o comportamento legado.
     * shadow: lê JSON e grava também no relacional para auditoria de paridade.
     * relacional: lê e grava somente nas tabelas operacionais.
     */
    'driver' => env('AVALIACOES_PERSISTENCIA_DRIVER', 'json'),

    // Janela temporária de rollback após o cutover relacional.
    'projetar_json_legado_assincrono' => (bool) env('AVALIACOES_PROJETAR_JSON_LEGADO', false),
];
