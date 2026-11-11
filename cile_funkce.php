<?php
declare(strict_types=1);

/**
 * cile_funkce.php – výpočet denních cílů metodou Mifflin-St Jeor
 * ---------------------------------------------------------------
 * BMR (Mifflin-St Jeor, 1990):
 *      muž:    10·kg + 6,25·cm − 5·rok + 5
 *      žena:   10·kg + 6,25·cm − 5·rok − 161
 * Denní příjem = BMR × faktor aktivity × korekce cíle.
 * Makronutrienty: 30 % bílkoviny / 40 % sacharidy / 30 % tuky
 * (bílkoviny a sacharidy 4 kcal/g, tuky 9 kcal/g).
 *
 * Soubor je čistý (žádná session/DB) – snadno testovatelný
 * a později znovu použitelný (např. týdenní plán).
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
 * Kalorie zaokrouhleno na 10, gramy na 5 – hezčí čísla pro uživatele.
 */
function spocitej_cile(float $vaha, int $vyska, int $vek, string $pohlavi, string $aktivita, string $cilTelo): array
{
    $bmr = 10.0 * $vaha + 6.25 * $vyska - 5.0 * $vek
         + ($pohlavi === 'm' ? 5.0 : -161.0);

    $kcal = $bmr * AKTIVITA_FAKTORY[$aktivita] * CILE_KOREKCE[$cilTelo];

    return [
        'kalorie'   => (int) (round($kcal / 10.0) * 10),
        'bilkoviny' => (int) (round($kcal * 0.30 / 4.0 / 5.0) * 5),
        'sacharidy' => (int) (round($kcal * 0.40 / 4.0 / 5.0) * 5),
        'tuky'      => (int) (round($kcal * 0.30 / 9.0 / 5.0) * 5),
    ];
}
