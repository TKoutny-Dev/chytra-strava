<?php
declare(strict_types=1);

/**
 * cile_funkce.php – výpočet denních cílů (100% přesná bilance)
 * ---------------------------------------------------------------
 * BMR dvěma vzorci:
 *   - Mifflin-St Jeor (1990), když neznáme % tělesného tuku:
 *       muž:  10·kg + 6,25·cm − 5·rok + 5
 *       žena: 10·kg + 6,25·cm − 5·rok − 161
 *   - Katch-McArdle, když údaj o tělesném tuku máme (přesnější,
 *     pracuje s čistou tělesnou hmotou):
 *       BMR = 370 + 21,6·(kg · (1 − tuk %/100))
 * Denní příjem = BMR × faktor aktivity × korekce cíle.
 * Makronutrienty: 30 % bílkoviny / 40 % sacharidy / 30 % tuky
 * (bílkoviny a sacharidy 4 kcal/g, tuky 9 kcal/g).
 *
 * ZÁRUKA 100% PŘESNOSTI: vrácená kalorie SE VŽDY ROVNÁ
 * součtu 4·bílkoviny + 4·sacharidy + 9·tuky – čísla na stránce
 * si přesně odpovídají, žádné zaokrouhlovací drifty.
 *
 * Soubor je čistý (žádná session/DB) – snadno testovatelný
 * a znovu použitelný (např. týdenní plán).
 */

/** faktory aktivity: kod => koeficient */
const AKTIVITA_FAKTORY = [
    'sedava'  => 1.2,    // práce vsedě, málo pohybu
    'mirna'   => 1.375,  // lehký pohyb 1–3× týdně
    'aktivni' => 1.55,   // pohyb 3–5× týdně
    'velmi'   => 1.725,  // trénink téměř denně
];

/** korekce cíle: kod => koeficient */
const CILE_KOREKCE = [
    'zhubnout'  => 0.85,  // deficit ~15 %
    'udrzet'    => 1.00,  // rovnováha
    'zhodnotit' => 1.10,  // přebytek ~10 %
];

/**
 * Spočítá denní cíle; vrací ['kalorie', 'bilkoviny', 'sacharidy', 'tuky'].
 *
 * $tukProcento – volitelný údaj o tělesném tuku v % (3–60);
 *   když je znám, počítá se přesnějším vzorcem Katch-McArdle.
 */
function spocitej_cile(float $vaha, int $vyska, int $vek, string $pohlavi, string $aktivita, string $cilTelo, ?float $tukProcento = null): array
{
    if ($tukProcento !== null) {
        // Katch-McArdle – pracuje s čistou tělesnou hmotou (bez tuku)
        $bezTuku = $vaha * (1.0 - $tukProcento / 100.0);
        $bmr = 370.0 + 21.6 * $bezTuku;
    } else {
        // Mifflin-St Jeor – klasika z dostupných údajů
        $bmr = 10.0 * $vaha + 6.25 * $vyska - 5.0 * $vek
             + ($pohlavi === 'm' ? 5.0 : -161.0);
    }

    $kcalVypocet = $bmr * AKTIVITA_FAKTORY[$aktivita] * CILE_KOREKCE[$cilTelo];

    // makra 30/40/30 % – gramy na 1 g přesnost (žádné hrubé násobky 5 g)
    $b = (int) round($kcalVypocet * 0.30 / 4.0);
    $s = (int) round($kcalVypocet * 0.40 / 4.0);
    $t = (int) round($kcalVypocet * 0.30 / 9.0);

    // hezký cíl na celé desítky kcal…
    $kcalCil = (int) (round($kcalVypocet / 10.0) * 10);

    // …a doladění makra tak, aby součet dával PŘESNĚ $kcalCil (100 % bilance)
    $doladene = dolad_makra($kcalCil, $b, $s, $t);

    return [
        'kalorie'   => $doladene['kalorie'],
        'bilkoviny' => $doladene['b'],
        'sacharidy' => $doladene['s'],
        'tuky'      => $doladene['t'],
    ];
}

/**
 * Doladí makra (posunem sacharidů a/nebo tuků o ±1 g) tak, aby
 * 4·b + 4·s + 9·t dávalo PŘESNĚ $kcalCil. Hledáme minimální zásah
 * do gramáží – procenta 30/40/30 zůstávají de facto beze změny
 * (posun je max ~1–2 g, tj. < 0,1 % příjmu).
 */
function dolad_makra(int $kcalCil, int $b, int $s, int $t): array
{
    $rozdil = $kcalCil - (4 * $b + 4 * $s + 9 * $t);

    if ($rozdil === 0) {
        return ['kalorie' => $kcalCil, 'b' => $b, 's' => $s, 't' => $t];
    }

    // hledáme 4·aS + 9·aT = $rozdil s minimálním počtem posunutých gramů
    $nejlepsi = null;
    $nejlepsiCena = PHP_INT_MAX;

    for ($aS = -8; $aS <= 8; $aS++) {
        for ($aT = -4; $aT <= 4; $aT++) {
            if (4 * $aS + 9 * $aT !== $rozdil) {
                continue;
            }
            $cena = abs($aS) + abs($aT);
            if ($cena < $nejlepsiCena) {
                $nejlepsiCena = $cena;
                $nejlepsi = ['b' => $b, 's' => $s + $aS, 't' => $t + $aT];
            }
        }
    }

    if ($nejlepsi === null) {
        // teoreticky nedosažitelný rozdíl (v praxi nevzniká, rozdíl je
        // vždy v rozsahu ±9 kcal) – pak dáme za kalorie přesný součet makra
        $kcalPresne = 4 * $b + 4 * $s + 9 * $t;
        return ['kalorie' => $kcalPresne, 'b' => $b, 's' => $s, 't' => $t];
    }

    $nejlepsi['kalorie'] = $kcalCil;
    return $nejlepsi;
}
