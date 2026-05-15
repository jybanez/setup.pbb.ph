<?php

declare(strict_types=1);

require __DIR__ . '/../src/KitSetupRunner.php';

$runner = new KitSetupRunner();
exit($runner->main($argv));

