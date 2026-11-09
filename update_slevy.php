<?php
declare(strict_types=1);

/**
 * update_slevy.php – SAMOSTATNÝ skript pro aktualizaci slev z letáků
 * ---------------------------------------------------------------
 * Postup dle zadání projektu:
 *   - stáhne digitální letáky přes cURL (žádné PDF OCR),
 *   - rozparsuje HTML pomocí DOMDocument/DOMXPath,
 *   - páruje produkty na katalog potravin (normalizace názvů),
 *   - aktualizuje tabulku akce_obchody (ON DUPLICATE KEY UPDATE).
 *
 * Spouštění:
 *   1) CRON na hostingu (CLI):  php /cesta/htdocs/update_slevy.php
 *      (např. každý středu 03:00)
 *   2) Webcron (cron-job.org…): volno URL s tokenem
 *      https://optimeal.site.je/update_slevy.php?token=slevy_cron_2026
 *
 * Log automatizace: aktualizace_slev.log (kontrola v panelu).
 */

require_once __DIR__ . '/spolecne.php';
require_once __DIR__ . '/slevy_funkce.php';

/* ---------- ochrana spuštění: CLI / webcron s tokenem ---------- */
$jeCli = php_sapi_name() === 'cli';
if (!$jeCli && ($_GET['token'] ?? '') !== 'slevy_cron_2026') {
    http_response_code(403);
    exit('Neautorizováno – spouštění CRON skriptu jen s tokenem.');
}

/* ---------- konfigurace obchodů ----------
 * U reálných supermarketů stačí upravit URL + XPath selektory
 * podle aktuální podoby jejich webu (zakomentovaný příklad níže).
 */
$obchody = [
    [
        'obchod'           => 'Ukázkový letak',
        'url'              => 'letak_ukazka.html',
        'selektorProduktu' => '//div[contains(concat(" ", normalize-space(@class), " "), " produkt ")]',
        'selektorNazev'    => './/h3',
        'selektorCena'     => './/span[@class="cena"]',
    ],
    /*
    [
        'obchod'           => 'Skutečný supermarket',
        'url'              => 'https://example.cz/akce/letak',
        'selektorProduktu' => '//article[contains(@class, "product-card")]',
        'selektorNazev'    => './/h2[@class="product-title"]',
        'selektorCena'     => './/span[@class="product-price"]',
    ],
    */
];

/* ---------- spustí stahování + párování + zápis slev ---------- */
$report = spustit_stahovani_slev(getPDO(), $obchody);

if ($jeCli) {
    // CRON výstup – jde do země/logs para nebo systémového mailingu
    echo "Update slev: {$report}", PHP_EOL;
} else {
    // webcron vidí stručnou stránku s potvrzením
    echo '<!doctype html><html lang="cs"><meta charset="utf-8">'
       . '<title>update_slevy</title><body style="font-family:sans-serif">'
       . '<h1>✔ Aktualizace slev proběhla</h1><p>' . esc($report) . '</p>'
       . '<p><small>Detaily: aktualizace_slev.log</small></p></body></html>';
}
