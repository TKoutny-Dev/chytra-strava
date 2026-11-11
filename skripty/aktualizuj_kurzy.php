<?php
declare(strict_types=1);

/**
 * aktualizuj_kurzy.php – denní aktualizace kurzů měn v mena.php
 * ---------------------------------------------------------------
 * Táhne aktuální kurzy ECB (bezplatné API frankfurter.dev, bez klíče),
 * přepočítá je na „kolik CZK stojí 1 EUR / 1 USD" a přepíše hodnoty
 * v mena.php.GitHub Actions workflow pak soubor commitne a pošle
 * na hosting přes FTP (stejný scénář jako aktualizace slev).
 *
 * Použití:  php skripty/aktualizuj_kurzy.php
 * Chybové stavy končí exit kódem 1 (workflow je vidí červeně).
 */

$soubor = dirname(__DIR__) . '/mena.php';
if (!is_file($soubor)) {
    fwrite(STDERR, "mena.php nenalezen: $soubor\n");
    exit(1);
}

/* ---------- 1) stažení kurzů (ECB přes frankfurter) ---------- */

$kontext = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'OptiMeal/1.0']]);
$raw = @file_get_contents('https://api.frankfurter.dev/v1/latest?base=CZK&symbols=EUR,USD', false, $kontext);
if ($raw === false) {
    fwrite(STDERR, "API frankfurter.dev nereaguje.\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['rates']['EUR'], $data['rates']['USD'], $data['date'])) {
    fwrite(STDERR, "Neočekávaná odpověď API: " . substr((string) $raw, 0, 200) . "\n");
    exit(1);
}

/* API vrací kolik EUR/USD je 1 CZK – my potřebujeme opačně (CZK za 1 jednotku) */
$kurzEur = 1 / (float) $data['rates']['EUR'];
$kurzUsd = 1 / (float) $data['rates']['USD'];
$datum   = (string) $data['date'];

/* ---------- 2) zdravý rozsah (API hack = neprůstřelné ceny) ---------- */

if ($kurzEur < 15 || $kurzEur > 40 || $kurzUsd < 10 || $kurzUsd > 40) {
    fwrite(STDERR, "Kurzy mimo zdravý rozsah (EUR $kurzEur / USD $kurzUsd) – nepřepisuji.\n");
    exit(1);
}

/* ---------- 3) přepis mena.php ---------- */

$cestinaMesice = [1 => 'ledna', 'února', 'března', 'dubna', 'května', 'června',
                  'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];
$casti = explode('-', $datum);
$datumCs = (int) $casti[2] . '. ' . $cestinaMesice[(int) $casti[1]] . ' ' . $casti[0];

$obsah = (string) file_get_contents($soubor);
$pred  = $obsah;

$obsah = (string) preg_replace(
    "~('eur' => \[[^\]]*?'kurz' => )[\d.]+~",
    '${1}' . number_format($kurzEur, 2, '.', ''),
    $obsah
);
$obsah = (string) preg_replace(
    "~('usd' => \[[^\]]*?'kurz' => )[\d.]+~",
    '${1}' . number_format($kurzUsd, 2, '.', ''),
    $obsah
);
$obsah = (string) preg_replace(
    '~// snímek kurzů [^\r\n]+~',
    "// snímek kurzů ECB z $datumCs",
    $obsah
);

/* pojistka: po přepisu musí být v souboru obě nové hodnoty – jinak vrátím zálohu */
$kontrola = substr_count($obsah, 'kurz') >= 3
    && str_contains($obsah, number_format($kurzEur, 2, '.', ''))
    && str_contains($obsah, number_format($kurzUsd, 2, '.', ''))
    && str_contains($obsah, "'eur'") && str_contains($obsah, "'usd'") && str_contains($obsah, "'czk'");
if (!$kontrola) {
    fwrite(STDERR, "Přepis by poškodil strukturu mena.php – vráceno zpět, nic se neukládá.\n");
    exit(1);
}

if ($obsah === $pred) {
    echo "Kurzy se od posledního běhu nezměnily – mena.php zůstává beze změny.\n";
    exit(0);
}

file_put_contents($soubor, $obsah);
printf("Kurzy aktualizovány (ECB %s): EUR %.2f Kč, USD %.2f Kč.\n", $datum, $kurzEur, $kurzUsd);
exit(0);
