<?php
declare(strict_types=1);

/**
 * slevy_funkce.php – jádro modulu AKTUÁLNÍCH SLEV
 * ---------------------------------------------------------------
 * Obsahuje:
 *  1) normalizaci názvů (pro párování letákových produktů na potraviny)
 *  2) uloz_slevu()          – zápis slevy (INSERT ... ON DUPLICATE KEY UPDATE)
 *  3) nacti_ukazkove_slevy() – DEMO: vloží ukázkový dataset slev bez stahování
 *  4) spustit_stahovani_slev() – stáhne a rozparsuje digitální letáky
 *     (cURL + DOMDocument/DOMXPath) a na páry potravina–produkt
 *     aktualizuje tabulku akce_obchody.
 *
 * Pozn. ke spouštění: během CRON probíhá vše bez loginu, proto
 * musíme pracovat výhradně s PDO (prepared statements) a logovat.
 */

/* ============================================================
 * 1) NORMALIZACE NÁZVŮ
 * ============================================================ */

/**
 * Sjednotí název pro párování: malá písmena, bez diakritiky, bez interpunkce.
 * („Kuřecí prsa 500 g" -> „kureci prsa 500 g")
 */
function normalizuj_nazev(string $nazev): string
{
    $nahrad = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
        'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't',
        'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
    ];
    $nazev = mb_strtolower(trim($nazev), 'UTF-8');
    $nazev = strtr($nazev, $nahrad);
    $nazev = (string) preg_replace('~[^a-z0-9 ]+~u', ' ', $nazev);
    $nazev = (string) preg_replace('~\s+~u', ' ', $nazev);
    return trim($nazev);
}

/* ============================================================
 * 2) ZÁPIS SLEVY
 * ============================================================ */

/**
 * Uloží/aktualizuje slevu potraviny v daném obchodě.
 * DÍKY unikátnímu klíči (id_potraviny, obchod) opakovaný běž
 * (každý týden z CRON) jen PŘEPÍŠE cenu – nikdy nezdvojí řádek.
 */
function uloz_slevu(PDO $pdo, int $idPotraviny, string $obchod, float $akcniCena, string $platnostDo): void
{
    $dotaz = $pdo->prepare(
        "INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
         VALUES (:id, :obchod, :cena, :do)
         ON DUPLICATE KEY UPDATE akcni_cena = :cena2, platnost_do = :do2"
    );
    $dotaz->execute([
        'id'     => $idPotraviny,
        'obchod' => $obchod,
        'cena'   => $akcniCena,
        'do'     => $platnostDo,
        'cena2'  => $akcniCena,
        'do2'    => $platnostDo,
    ]);
}

/* ============================================================
 * 3) DEMO DATASET – tlačítko „Načíst ukázkové slevy"
 * ============================================================ */

/**
 * Vloží pevně daný ukázkový dataset slev (pro jistý, snadný
 * prezentovatelný stav na obhajobě - bez závislosti na síti).
 * Vrací report pro flash zprávu.
 */
function nacti_ukazkove_slevy(PDO $pdo): string
{
    $platnostDo = date('Y-m-d', strtotime('+30 days'));

    // název potraviny => [obchod, cena za 100 g]
    $ukazkove = [
        'Kuřecí prsa'       => ['Billa', 9.49],
        'Kuřecí prsa'       => ['Tesco', 9.90],   // druhý obchod pro tutéž potravinu
        'Tvaroh polotučný'  => ['Tesco', 4.49],
        'Čočka červená'     => ['Kaufland', 4.10],
        'Hovězí svíčková'   => ['Billa', 24.50],
        'Losos'             => ['Kaufland', 27.90],
        'Mléko čerstvé'     => ['Billa', 1.89],
        'Těstoviny celozrnné'=> ['Kaufland', 3.75],
        'Brambory'          => ['Billa', 1.39],
        'Ovesné vločky'     => ['Billa', 4.20],
    ];

    $ulozeno = 0;
    $preskoceno = [];

    foreach ($ukazkove as $nazev => [$obchod, $cena]) {
        $dotaz = $pdo->prepare("SELECT id FROM potraviny WHERE nazev = :nazev LIMIT 1");
        $dotaz->execute(['nazev' => $nazev]);
        $rid = $dotaz->fetch();

        if ($rid === false) {
            $preskoceno[] = $nazev . ' (není v katalogu)';
            continue;
        }
        uloz_slevu($pdo, (int) $rid['id'], $obchod, $cena, $platnostDo);
        $ulozeno++;
    }

    $report = "Načteno ukázkových slev: {$ulozeno} (platnost do {$platnostDo}).";
    if ($preskoceno !== []) {
        $report .= ' Přeskočeno: ' . implode(', ', $preskoceno) . '.';
    }
    return $report;
}

/* ============================================================
 * 4) STAHOVÁNÍ A PARSOVÁNÍ LETÁKŮ (cURL + DOMDocument)
 * ============================================================ */

/** Stáhne obsah stránky: http(s) přes cURL, lokální cesta přes file. */
function nacti_obsah(string $url, array &$log): ?string
{
    if (preg_match('~^https?://~i', $url)) {
        // skutečný web přes cURL (tréninkový CRON na produkci)
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'OptiMeal-slevy/1.0 (+cron)',
        ]);
        $html = curl_exec($ch);
        if ($html === false) {
            $log[] = 'cURL chyba (' . $url . '): ' . curl_error($ch);
            curl_close($ch);
            return null;
        }
        $httpKod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpKod !== 200) {
            $log[] = "HTTP {$httpKod} u adresy {$url}";
            return null;
        }
        return (string) $html;
    }

    // lokální (ukázkový) leták – nejsnazší test bez závislosti na síti
    $cesta = __DIR__ . '/' . $url;
    if (!is_file($cesta)) {
        $log[] = 'Leták nenalezen: ' . $cesta;
        return null;
    }
    return (string) file_get_contents($cesta);
}

/**
 * Vytěží z HTML produkty: název -> cena.
 * Logika selektorů pochází z konfigurace obchodu (XPath), takže se
 * dají snadno upravit, když obchod změní vzhled svého webu/letáku.
 */
function parsuj_produkty(string $html, array $konfig, array &$log): array
{
    $produkty = [];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // HTML z webů bývá „syrové", chyby potlačíme
    // DOMDocument defaultně parsuje jako ISO-8859-1 – bez XML hintu
    // vzniká z UTF-8 mozarza („KuÅÅecÃ prsa"). Standardní oprava při scraping.
    $ok = $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    if (!$ok) {
        $log[] = 'HTML letáku (' . $konfig['obchod'] . ') se nepovedlo rozparsovat.';
        return [];
    }

    $xpath = new DOMXPath($dom);
    $uzly = $xpath->query($konfig['selektorProduktu']);

    foreach ($uzly as $uzel) {
        $nazev = trim((string) $xpath->evaluate('string(' . $konfig['selektorNazev'] . ')', $uzel));
        $cenaText = trim((string) $xpath->evaluate('string(' . $konfig['selektorCena'] . ')', $uzel));

        if ($nazev === '' || $cenaText === '') {
            continue;
        }

        // ceny jako „49,90 Kč" / „49.90" / „4,29" -> 49.90
        if (!preg_match('~\d+([ .]\d+)?,\s?\d\d?~u', str_replace("\u{00a0}", ' ', $cenaText), $m)) {
            continue;   // bez ceny nerozhodneme
        }
        $cenaCislo = (float) str_replace([',', ' '], '.', preg_replace('~\s+~u', '', $m[0]));
        if ($cenaCislo <= 0) {
            continue;
        }

        $produkty[] = ['nazev' => $nazev, 'cena' => $cenaCislo];
    }

    return $produkty;
}

/**
 * Párovaní letákových produktů na potraviny a zápis slev.
 * $obchody = konfigurace: obchod/url + XPath selektory.
 * Vrací report (pro flash zprávu v slevy.php a CLI výpis).
 */
function spustit_stahovani_slev(PDO $pdo, ?array $obchody = null): string
{
    // výchozí konfigurace – ukázkový digitální leták (funguje i offline!)
    // skríté REALLY obch: při obhajobě vysvětlíme, „ukázkový letak"
    // je ekvivalentní HTML struktura re<dimu webu supermarketu.
    $obchody ??= [
        [
            'obchod'           => 'Ukázkový letak',
            'url'              => 'letak_ukazka.html',
            'selektorProduktu' => '//div[contains(concat(" ", normalize-space(@class), " "), " produkt ")]',
            'selektorNazev'    => './/h3',
            'selektorCena'     => './/span[@class="cena"]',
        ],
    ];

    $log = [];
    $ulozeno = 0;
    $nenalezeno = [];

    // katalog potravin + normalizované názvy (pro rychlé párování)
    $potraviny = $pdo->query("SELECT id, nazev FROM potraviny")->fetchAll();
    $mapaPotravin = [];
    foreach ($potraviny as $p) {
        $mapaPotravin[ (int) $p['id'] ] = [
            'nazev'   => $p['nazev'],
            'normalizovane' => normalizuj_nazev($p['nazev']),
        ];
    }

    $platnostDo = date('Y-m-d', strtotime('+14 days'));

    foreach ($obchody as $konfig) {
        $html = nacti_obsah($konfig['url'], $log);
        if ($html === null) {
            continue;
        }
        $produkty = parsuj_produkty($html, $konfig, $log);
        $log[] = $konfig['obchod'] . ': nalezeno ' . count($produkty) . ' produktů v letáku.';

        foreach ($produkty as $produkt) {
            $nazevNorm = normalizuj_nazev($produkt['nazev']);
            $shoda = null;

            foreach ($mapaPotravin as $id => $data) {
                // páruje se BUĎ přesná shoda, NEBO jedna strana je podřetězec druhé
                if ($data['normalizovane'] === $nazevNorm
                    || str_contains($nazevNorm, $data['normalizovane'])
                    || str_contains($data['normalizovane'], $nazevNorm)) {
                    $shoda = $id;
                    break;
                }
            }

            if ($shoda !== null) {
                uloz_slevu($pdo, $shoda, $konfig['obchod'], $produkt['cena'], $platnostDo);
                $ulozeno++;
                $log[] = sprintf('%s <= "%s" (%.2f Kč) -> uloženo do obchodu %s.',
                    $mapaPotravin[$shoda]['nazev'], $produkt['nazev'], $produkt['cena'], $konfig['obchod']);
            } else {
                $nenalezeno[] = $produkt['nazev'];
            }
        }
    }

    $report = "Staženo a uloženo slev: {$ulozeno}.";
    if ($nenalezeno !== []) {
        $report .= ' Produkty bez shody v katalogu: ' . implode(', ', array_slice($nenalezeno, 0, 5))
                 . (count($nenalezeno) > 5 ? ' a další.' : '.');
    }

    // log pro CRON (průkaz automatizace – klidně přiložit k dokumentaci!)
    file_put_contents(
        __DIR__ . '/aktualizace_slev.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $report . PHP_EOL
        . ($log !== [] ? '  ' . implode(PHP_EOL . '  ', $log) . PHP_EOL : PHP_EOL),
        FILE_APPEND
    );

    return $report;
}
