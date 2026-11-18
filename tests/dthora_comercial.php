<?php

if (PHP_SAPI !== 'cli') {
    exit('Execute este teste pelo terminal.');
}

require_once __DIR__ . '/../public_html/biblioteca.php';

$casos = [
    ['25/12/2026 10:00', '2026-12-28 08:00:00'],
    ['24/10/2026 10:00', '2026-10-26 08:00:00'],
    ['24/10/2025 10:00', '2025-10-27 08:00:00'],
    ['31/12/2026 10:00', '2026-12-31 10:00:00'],
    ['01/05/2026 10:00', '2026-05-04 08:00:00'],
    ['18/11/2026 04:30', '2026-11-18 08:00:00'],
    ['18/11/2026 19:00', '2026-11-19 08:00:00']
];

foreach ($casos as $caso) {
    $resultado = dthora_comercial($caso[0]);
    if ($resultado !== $caso[1]) {
        throw new RuntimeException(
            'FALHOU: ' . $caso[0] . ' => ' . $resultado . '; esperado: ' . $caso[1]
        );
    }

    echo $caso[0] . ' => ' . $resultado . PHP_EOL;
}

echo 'Todos os testes passaram.' . PHP_EOL;
