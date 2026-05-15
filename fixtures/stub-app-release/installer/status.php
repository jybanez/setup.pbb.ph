<?php

declare(strict_types=1);

header('Content-Type: application/json');
echo json_encode([
    'schema_version' => 1,
    'app' => 'pbb-stub',
    'version' => '0.1.0',
    'installed' => false,
    'status' => 'unknown',
    'services' => [],
    'warnings' => [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

