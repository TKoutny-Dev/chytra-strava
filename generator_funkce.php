<?php
declare(strict_types=1);

/**
 * generator_funkce.php – JÁDRO aplikace OptiMeal
 * ---------------------------------------------------------------
 * Algoritmus "Chytrý generátor jídelníčku":
 *
 *  1) Načtu všechny potraviny včetně jejich AKTUÁLNÍCH slev
 *     (LEFT JOIN + filtr platnost_do >= CURDATE(); u potraviny
 *      ve slevě ve více obchodech vyberu vždy nejlevnější slevu).
 *  2) Rozdělím denní cíle uživatele na 4 jídla podle podílů
 *     (Snídaně 25 %, Oběd 35 %, Svačina 15 %, Večeře 25 %).
 *  3) Každou potravinu zařadím do kategorie podle dominujícího
 *     makroživiny (bilkovinová / sacharidová / tuková).
 *  4) Pro každé jídlo vyberu kandidáty – SLEVOVÉ potraviny mají
 *     ve skóre 25% bonus, takže generátor "automaticky" upřednostní
 *     zlevněné potraviny (= odkaz na zadání projektu).
 *  5) Spočítám gramace tak, aby jídlo co nejléze pokrylo cíle
 *     daného jídla, a složím nákupní seznam s nejlevnějším obchodem.
 *
 * Funkce jsou čisté (vstup -> výstup), díky čemuž je lze lehce
 * testovat a vysvětlit na maturitní obhajobě.
 */

/** Podíly denních cílů na jednotlivá jídla */
const PODILY_JIDEL = [
    'Snídaně'  => 0.25,
    'Oběd'     => 0.35,
    'Svačina'  => 0.15,
    'Večeře'   => 0.25,
];

/** Bonus ve skóre pro potraviny, které jsou právě ve slevě */
const BONUS_SLEVY = 1.25;

/* ============================================================
 * 1) NAČTENÍ DAT – potraviny + akce (slevy)
 * ============================================================ */

/**
 * Vrátí pole potravin, kde každá má nastavenou AKTUÁLNÍ cenu
 * (akční, pokud existuje, jinak běžnou) a informaci o slevě.
 * Pokud je potravina ve slevě ve více obchodech najednou,
 * zůstane ta NEJLEVNĚJŠÍ.
 */
function nacti_potraviny_se_slevami(PDO $pdo): array
{
    $sql = "SELECT p.id,
                   p.nazev,
                   p.bilkoviny,
                   p.sacharidy,
                   p.tuky,
                   p.kalorie,
                   p.bezna_cena,
                   COALESCE(a.akcni_cena, p.bezna_cena) AS aktualni_cena,
                   a.obchod,
                   a.platnost_do
            FROM potraviny AS p
            LEFT JOIN akce_obchody AS a
                ON a.id_potraviny = p.id
               AND a.platnost_do >= CURDATE()";

    $radky = $pdo->query($sql)->fetchAll();
    $potraviny = [];

    foreach ($radky as $radek) {
        $id = (int) $radek['id'];

        if (!isset($potraviny[$id])) {
            // první výskyt potraviny – rovnou víme, zda má slevu
            $radek['sleva'] = ($radek['obchod'] !== null);
            $potraviny[$id] = $radek;
            continue;
        }

        // další sleva téže potraviny – ponecháme výhodnější
        if ($radek['obchod'] !== null
            && (float) $radek['aktualni_cena'] <= (float) $potraviny[$id]['aktualni_cena']) {
            $potraviny[$id]['aktualni_cena'] = $radek['aktualni_cena'];
            $potraviny[$id]['obchod']        = $radek['obchod'];
            $potraviny[$id]['platnost_do']   = $radek['platnost_do'];
            $potraviny[$id]['sleva']         = true;
        }
    }

    return $potraviny;
}

/* ============================================================
 * 3) KATEGORIE A SKÓRE POTRAVIN
 * ============================================================ */

/**
 * Kategorie podle DOMINUJÍCÍ makroživiny (podle ¥ílu kcal):
 *   bilkoviny  4 kcal/g, sacharidy 4 kcal/g, tuky 9 kcal/g.
 * Vrací: 'bilkovinova' | 'sacharidova' | 'tukova'
 */
function kategorie_potraviny(array $p): string
{
    $kcalBilkovin = (float) $p['bilkoviny'] * 4;
    $kcalSacharid = (float) $p['sacharidy'] * 4;
    $kcalTuku     = (float) $p['tuky'] * 9;

    $celkem = max($kcalBilkovin + $kcalSacharid + $kcalTuku, 0.001);

    if ($kcalBilkovin / $celkem >= max($kcalSacharid, $kcalTuku) / $celkem) {
        return 'bilkovinova';
    }
    return ($kcalSacharid >= $kcalTuku) ? 'sacharidova' : 'tukova';
}

/**
 * Skóre = kolik „úžitku" dává potravina za 1 Kč:
 *   bilkovinová potravina -> gramy bilkovin / Kč
 *   sacharidová           -> gramy sacharidů / Kč
 *   tuková                -> gramy tuků / Kč
 * Potraviny ve slevě dostanou bonus (BONUS_SLEVY), aby je
 * generátor preferoval – přesně dle zadání projektu.
 */
function skore_potraviny(array $p, string $kategorie): float
{
    $cena = max(0.01, (float) $p['aktualni_cena']);

    $hodnota = match ($kategorie) {
        'bilkovinova' => (float) $p['bilkoviny'],
        'sacharidova' => (float) $p['sacharidy'],
        default       => (float) $p['tuky'],
    };

    $skore = $hodnota / $cena;

    if (!empty($p['sleva'])) {
        $skore *= BONUS_SLEVY;
    }

    return $skore;
}

/* ============================================================
 * 4+5) VLASTNÍ GENERÁTOR
 * ============================================================ */

/**
 * Výběr kandidáta s ohledem na RŮZNORODOST (2 pravidla k obhajobě):
 *   1) FILTR POUŽITÝCH: potraviny už obsažené v dnešním jídelníčku
 *      (přinesené přes $pouziteId) jsou odsunuty k pozadí – dřív
 *      se spolkne ostatní nabídka, než se tatáž potravina zopakuje.
 *   2) DENNÍ ROTACE: offset podle dne v týdnu (date('N') = 1–7)
 *      a pořadí jídla – jídelník je jiný každý den, ale přitom
 *      DETERMINISTICKÝ (stejný den = stejný výsledek = obhajitelno).
 */
function vyber_kandidata(array $kandidati, int $poradi, array $pouziteId = []): ?array
{
    if ($kandidati === []) {
        return null;
    }

    // 1) preferujeme potraviny, které dnes dosud nebyly
    $nove = array_values(array_filter(
        $kandidati,
        static fn(array $p): bool => !in_array((int) $p['id'], $pouziteId, true)
    ));
    if ($nove !== []) {
        $kandidati = $nove;
    }

    // 2) denní rotace – pondělí ≠ úterý, snídaně ≠ oběd
    $offset = (int) date('N') + $poradi * 2;
    return $kandidati[$offset % count($kandidati)];
}

/** vytvoří položku jídelníčku z potraviny a počtu gramů */
function vytvor_jidlo(array $p, int $gramy): array
{
    $koeficient = $gramy / 100;

    return [
        'id'        => (int) $p['id'],
        'nazev'     => $p['nazev'],
        'gramy'     => $gramy,
        'kalorie'   => (int) round((float) $p['kalorie'] * $koeficient),
        'bilkoviny' => round((float) $p['bilkoviny'] * $koeficient, 1),
        'sacharidy' => round((float) $p['sacharidy'] * $koeficient, 1),
        'tuky'      => round((float) $p['tuky'] * $koeficient, 1),
        'cena'      => round((float) $p['aktualni_cena'] * $koeficient, 2),
        'obchod'    => $p['sleva'] ? $p['obchod'] : null,
        'sleva'     => (bool) $p['sleva'],
    ];
}

/**
 * Naplánuje jednotlivé jídlo ze 3 kroků:
 *   1. „kotva" = bílkovinová potravina (kvantita podle bílkovin)
 *   2. doplněk sacharidů (kvantita podle sacharidů)
 *   3. doplněk tuků (jen když zbývá výraznější množství)
 * Každá porce navíc musí zůstat v limitu kcal tohoto jídla
 * – díky tomu celý den nesjede nad kalorie.
 * Vybraná ID přibudou do pole $pouziteId => další jídla upřednostní
 * jiné potraviny (viz vyber_kandidata()).
 */
function naplanuj_jidlo(array $kategorie, int $poradi, float $cilB, float $cilS, float $cilT, float $cilK, array &$pouziteId = []): array
{
    $jidla   = [];
    $zbyva   = [
        'b' => max($cilB, 0),
        's' => max($cilS, 0),
        't' => max($cilT, 0),
        'k' => max($cilK, 0), // kcal rozpočet jídla
    ];

    /** spočítá gramace podle požadovaného makra + kalorického stropu */
    $gramace = static function (array $p, string $makro, float $cilNaMakro, int $min, int $max) use (&$zbyva): int {
        $hodnotaNaGram = max((float) $p[$makro], 0.5) / 100;
        $gramyPodleMakra = (int) round($cilNaMakro / $hodnotaNaGram);
        // doplněk nemá přesáhnout ani kalorický rozpočet jídla
        $kcalNaGram = max((float) $p['kalorie'], 1) / 100;
        $gramyPodleKcal = (int) (($zbyva['k'] ?? 0) / $kcalNaGram);
        return clamp_int(min($gramyPodleMakra, $gramyPodleKcal), $min, $max);
    };

    // --- 1. kotva (bílkovinová potravina) ---
    $kotva = vyber_kandidata($kategorie['bilkovinova'], $poradi, $pouziteId)
             ?? vyber_kandidata($kategorie['sacharidova'], $poradi, $pouziteId);
    if ($kotva !== null && $zbyva['b'] >= 5) {
        $pouziteId[] = (int) $kotva['id'];
        $gramy = $gramace($kotva, 'bilkoviny', $zbyva['b'], 30, 400);

        $jidlo = vytvor_jidlo($kotva, $gramy);
        $jidla[] = $jidlo;
        $zbyva['b'] -= $jidlo['bilkoviny'];
        $zbyva['s'] -= $jidlo['sacharidy'];
        $zbyva['t'] -= $jidlo['tuky'];
        $zbyva['k'] -= $jidlo['kalorie'];
    }

    // --- 2. doplněk sacharidů ---
    // Námitka: doplněk sacharidů nechceme „přeflakovat" dalšímy bílkovinami,
    // proto preferujeme potraviny s nízkým obsahem bílkovin
    // (rýže a vločky, ne čočka), existují-li mezi kandidáty.
    $kandidatiS = $kategorie['sacharidova'];
    $nizkoproteinovi = array_values(array_filter(
        $kandidatiS,
        static fn(array $p): bool => (float) $p['bilkoviny'] <= 15.0
    ));
    if ($nizkoproteinovi !== []) {
        $kandidatiS = $nizkoproteinovi;
    }
    $dopln = vyber_kandidata($kandidatiS, $poradi, $pouziteId);
    if ($dopln !== null && $zbyva['s'] >= 10 && $zbyva['k'] >= 60) {
        $pouziteId[] = (int) $dopln['id'];
        $gramy = $gramace($dopln, 'sacharidy', $zbyva['s'], 30, 350);

        $jidlo = vytvor_jidlo($dopln, $gramy);
        $jidla[] = $jidlo;
        $zbyva['b'] -= $jidlo['bilkoviny'];
        $zbyva['s'] -= $jidlo['sacharidy'];
        $zbyva['t'] -= $jidlo['tuky'];
        $zbyva['k'] -= $jidlo['kalorie'];
    }

    // --- 3. doplněk tuků (jen když zbývá potřeba i kcal prostor) ---
    $tukovina = vyber_kandidata($kategorie['tukova'], $poradi, $pouziteId);
    if ($tukovina !== null && $zbyva['t'] >= 8 && $zbyva['k'] >= 80) {
        $pouziteId[] = (int) $tukovina['id'];
        $gramy = $gramace($tukovina, 'tuky', $zbyva['t'], 20, 100);

        $jidlo = vytvor_jidlo($tukovina, $gramy);
        $jidla[] = $jidlo;
        $zbyva['b'] -= $jidlo['bilkoviny'];
        $zbyva['s'] -= $jidlo['sacharidy'];
        $zbyva['t'] -= $jidlo['tuky'];
        $zbyva['k'] -= $jidlo['kalorie'];
    }

    return $jidla;
}

/**
 * Hlavní funkce generátoru.
 * Vrací: ['jidelnicek' => [jídlo => souhrn], 'celkem' => denní souhrn]
 */
function vygeneruj_jidelnicek(PDO $pdo, array $cile): array
{
    // krok 1 – data + krok 3 – kategorie a skóre
    $potraviny = nacti_potraviny_se_slevami($pdo);

    $kategorie = ['bilkovinova' => [], 'sacharidova' => [], 'tukova' => []];
    foreach ($potraviny as $p) {
        $k = kategorie_potraviny($p);
        $p['skore'] = skore_potraviny($p, $k);
        $kategorie[$k][] = $p;
    }

    // seřadíme každou kategorii od NEJVÝHODnějšího kandidáta
    foreach ($kategorie as $k => $seznam) {
        usort($seznam, static fn(array $a, array $b): int => $b['skore'] <=> $a['skore']);
        $kategorie[$k] = $seznam;
    }

    // krok 2 – rozdělení cílů na jídla (použité ID zapustí rozmanitost
    // – každé další jídlo upřednostní potraviny, co dnes ještě nebyly)
    $jidelnicek = [];
    $celkem = ['kalorie' => 0.0, 'bilkoviny' => 0.0, 'sacharidy' => 0.0, 'tuky' => 0.0, 'cena' => 0.0];
    $poradi = 0;
    $pouziteId = [];

    foreach (PODILY_JIDEL as $nazevJidla => $podil) {
        $jidla = naplanuj_jidlo(
            $kategorie,
            $poradi,
            (float) $cile['bilkoviny'] * $podil,
            (float) $cile['sacharidy'] * $podil,
            (float) $cile['tuky'] * $podil,
            (float) $cile['kalorie'] * $podil,
            $pouziteId
        );

        $souhrn = ['kalorie' => 0, 'bilkoviny' => 0.0, 'sacharidy' => 0.0, 'tuky' => 0.0, 'cena' => 0.0];
        foreach ($jidla as $jidlo) {
            $souhrn['kalorie']   += $jidlo['kalorie'];
            $souhrn['bilkoviny'] += $jidlo['bilkoviny'];
            $souhrn['sacharidy'] += $jidlo['sacharidy'];
            $souhrn['tuky']      += $jidlo['tuky'];
            $souhrn['cena']      += $jidlo['cena'];
        }

        $jidelnicek[$nazevJidla] = ['jidla' => $jidla] + $souhrn;

        $celkem['kalorie']   += $souhrn['kalorie'];
        $celkem['bilkoviny'] += $souhrn['bilkoviny'];
        $celkem['sacharidy'] += $souhrn['sacharidy'];
        $celkem['tuky']      += $souhrn['tuky'];
        $celkem['cena']      += $souhrn['cena'];

        $poradi++;
    }

    return ['jidelnicek' => $jidelnicek, 'celkem' => $celkem];
}

/* ============================================================
 * NÁKUPNÍ SEZNAM
 * ============================================================ */

/**
 * Sesbírá potraviny ze všech jídel, sečte gramy
 * a ke každé spočítá celkovou cenu dle aktuální (nebo běžné) ceny.
 * Cena za 100 g už zahrnuje SLEVU – díky tomu seznam ukazuje,
 * v KTERÉM obchodě nakoupit nejvýhodneji.
 */
function sestav_nakupni_seznam(array $jidelnicek): array
{
    $seznam = [];

    foreach ($jidelnicek as $jidloDenne) {
        foreach ($jidloDenne['jidla'] as $jidlo) {
            $id = $jidlo['id'];
            if (!isset($seznam[$id])) {
                $seznam[$id] = [
                    'nazev'        => $jidlo['nazev'],
                    'gramy'        => 0,
                    'cena_za_100g' => $jidlo['gramy'] > 0
                        ? round($jidlo['cena'] * 100 / $jidlo['gramy'], 2)
                        : 0.0,
                    'obchod'       => $jidlo['obchod'],
                    'sleva'        => $jidlo['sleva'],
                ];
            }
            $seznam[$id]['gramy'] += $jidlo['gramy'];
        }
    }

    foreach ($seznam as $id => $polozka) {
        $seznam[$id]['cena_celkem'] = round($polozka['gramy'] * $polozka['cena_za_100g'] / 100, 2);
    }

    // seřadíme od nejdražší položky (hlavní nákup nahoře)
    usort($seznam, static fn(array $a, array $b): int => $b['cena_celkem'] <=> $a['cena_celkem']);

    return $seznam;
}
