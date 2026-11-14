<?php
declare(strict_types=1);

/**
 * mena.php – více měn v OptiMealu (CZK / EUR / USD)
 * ---------------------------------------------------------------
 * ARCHITEKTURA:
 *   - Databáze držíme v jediném základním měnovém standardu: CZK / 100 g.
 *     Všechna data (potraviny.bezna_cena i akce_obchody.akcni_cena) jsou v korunách.
 *   - Zobrazení převádí podle právě zvolené měny uživatele (session).
 *   - VSTUP Z FORMULÁŘŮ: uživatel zadává ve své měně; skript hodnotu
 *     vynásobí kurzem a uloží zpět v CZK. (Sjednocené rozhraní.)
 *   - KURZY se denně aktualizují skriptem skripty/aktualizuj_kurzy.php,
 *     který běží z GitHub Actions (workflow aktualizace_kurzu.yml).
 */

/** Seznam podporovaných měn: kod => [znacka, kurz k CZK, pocet desetinnych mist] */
function mena_seznam(): array
{
    return [
        // snímek kurzů ECB z 13. listopadu 2026
        'czk' => ['znacka' => 'Kč', 'kurz' => 1.00,  'desetinna' => 2],
        'eur' => ['znacka' => '€',  'kurz' => 24.42, 'desetinna' => 2],
        'usd' => ['znacka' => '$',  'kurz' => 22.10, 'desetinna' => 2],
    ];
}

/**
 * Platná měna: ?mena= > session > default czk.
 * Měna je samostatná volba (nezávislá na jazyku).
 */
function mena_aktualni(): string
{
    $zUrl = $_GET['mena'] ?? '';
    if (is_string($zUrl) && isset(mena_seznam()[$zUrl])) {
        $_SESSION['mena'] = $zUrl;
        return $zUrl;
    }
    $vSession = $_SESSION['mena'] ?? '';
    if (is_string($vSession) && isset(mena_seznam()[$vSession])) {
        return $vSession;
    }
    $_SESSION['mena'] = 'czk';
    return 'czk';
}

/** Značka aktivní měny (Kč / € / $) – pro labely formulářů atd. */
function mena_znacka(): string
{
    return mena_seznam()[mena_aktualni()]['znacka'];
}

/** Kurz aktivní měny k CZK (kolik CZK je 1 jednotka měny) */
function mena_kurz(): float
{
    return mena_seznam()[mena_aktualni()]['kurz'];
}

/**
 * Zobrazí cenu (uloženou v CZK / 100 g) v aktivní měně.
 * 50 CZK → EUR: „2,00 €"; CZK: „50,00 Kč"; USD: „$ 2.16".
 */
function mena_zobraz(float $cenaCzk, ?string $kod = null): string
{
    $kod ??= mena_aktualni();
    if (!isset(mena_seznam()[$kod])) {
        $kod = 'czk';
    }
    $m  = mena_seznam()[$kod];
    $cena  = $cenaCzk / $m['kurz'];
    $cislo = number_format($cena, $m['desetinna'], $kod === 'usd' ? '.' : ',', ' ');

    return $kod === 'usd' ? ('$ ' . $cislo) : ($cislo . ' ' . $m['znacka']);
}

/**
 * Hodnota ze vstupního formuláře (zadaná v aktivní měně) -> CZK (základ
 * databáze). Např. 1,00 € -> 24,38 Kč. Pro CZK se kurz rovná 1.
 */
function mena_na_czk(float $hodnotaVMenate): float
{
    return round($hodnotaVMenate * mena_kurz(), 2);
}

/** Odkaz pro přepnutí měny (zachová současný kontext URL, jako přepínač jazyka) */
function mena_odkaz(string $kod): string
{
    $params = $_GET;
    $params['mena'] = $kod;
    return '?' . http_build_query($params);
}
