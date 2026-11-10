<?php
declare(strict_types=1);

/**
 * spolecne.php – společné jádro aplikace OptiMeal
 * ---------------------------------------------------------------
 * - zapíná session (přihlášení, flash zprávy, CSRF tokeny)
 * - načítá databázové připojení
 * - obsahuje pomocné funkce pro přihlášeného uživatele
 *
 * Účel pro obhajobu: jediné místo, kde řešíme typy bezpečnosti
 * společné pro všechny stránky (session, CSRF, role uživatele).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jazyky.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// rozhodne o jazyku (podle ?lang= / session / prohlížeče) – viz jazyky.php
aktualni_jazyk();

/* ============================================================
 * POMOCNÉ FUNKCE VÝSTUPU (pouští je každý soubor přes spolecne.php)
 * ============================================================ */

/** htmlspecialchars – ochrana proti XSS na jednom místě */
function esc($hodnota): string
{
    return htmlspecialchars((string) $hodnota, ENT_QUOTES, 'UTF-8');
}

/** formát čísla v českém zápisu (mezera jako tisíce, čárka jako desetinná) */
function fmt(float $cislo, int $desetinnaMista = 1): string
{
    return number_format($cislo, $desetinnaMista, ',', ' ');
}

/** ořízne celé číslo do povoleného rozsahu */
function clamp_int(int $hodnota, int $min, int $max): int
{
    return max($min, min($max, $hodnota));
}

/* ============================================================
 * PŘIHLÁŠENÍ A ROLE
 * ============================================================ */

/** vrací pole přihlášeného uživatele nebo null */
function prihlaseny_uzivatel(): ?array
{
    return $_SESSION['uzivatel'] ?? null;
}

/** vrací true, pokud je přihlášen uživatel s rolí admin */
function je_admin(): bool
{
    $u = prihlaseny_uzivatel();
    return is_array($u) && ($u['role'] ?? '') === 'admin';
}

/**
 * Zabrání přístupu nepřihlášenému uživateli –
 * přesměruje na přihlášení a uloží si „kudy dál".
 */
function vyzaduj_prihlaseni(): void
{
    if (prihlaseny_uzivatel() === null) {
        $_SESSION['cil_presmerovani'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: prihlaseni.php');
        exit;
    }
}

/** Odhlásí uživatele (zničí se celá session) */
function odhlasit(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ============================================================
 * FLASH ZPRÁVY (zpráva po přesměrování, např. „Uloženo")
 * ============================================================ */

function flash(string $text, string $typ = 'success'): void
{
    $_SESSION['flash'] = ['text' => $text, 'typ' => $typ];
}

function zobraz_flash(): string
{
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . esc($f['typ']) . ' alert-dismissible fade show" role="alert">'
         . esc($f['text'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zavřít"></button></div>';
}

/* ============================================================
 * CSRF OCHRANA FORMULÁŘŮ
 * ============================================================ */

/**
 * Vypíše/vygeneruje CSRF token uložený v session –
 * dává se jako skryté pole do každého POST formuláře.
 */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

/** skryté pole pro formuláře */
function csrf_pole(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** kontrola tokeny z formuláře (časově konstantní porovnání) */
function csrf_zkontroluj(): bool
{
    $poslany = $_POST['csrf'] ?? '';
    return isset($_SESSION['csrf'])
        && is_string($poslany)
        && hash_equals($_SESSION['csrf'], $poslany);
}
