<?php
declare(strict_types=1);

/**
 * _ladeni.php – DOČASNÝ ladicí wrapper (po opravě SMAŽTE!)
 * Zobrazí chyby u stránky potraviny.php, aby bylo vidět, kde PHP padá.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/potraviny.php';
