<?php
declare(strict_types=1);

/**
 * db.php – připojení k databázi přes PDO
 * Vrací jeden sdílený PDO objekt pro celou aplikaci.
 *
 * Přihlašovací údaje čte ze souboru config.php (není v repozitáři,
 * viz .gitignore). Na lokálním XAMPP vznikne kopírováním
 * souboru config.php.ukazka, na hostingu vyplníte údaje
 * z kontrolního panelu hostingu.
 */

$config = require __DIR__ . '/config.php';

/**
 * Vrací PDO instanci (lazy inicializace – vytvoří se jen jednou).
 *
 * Nastavení:
 *  - ERRMODE_EXCEPTION      -> chyba vyhodí výjimku, žádné tiché problémy
 *  - EMULATE_PREPARES=false -> skutečné prepared statements v MySQL,
 *                              silná ochrana proti SQL injection
 *  - FETCH_ASSOC            -> výsledky jako asociativní pole
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        global $config;
        $dsn = 'mysql:host=' . $config['databaze_host']
             . ';dbname='   . $config['databaze_jmeno']
             . ';charset='  . $config['znakova_sada'];

        try {
            $pdo = new PDO($dsn, $config['databaze_uzivatel'], $config['databaze_heslo'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Uživateli nic nesmíme prozradit (jméno DB, cesty, hesla...).
            // Detail si zalogujeme pro ladění i budoucí obhajobu.
            error_log('Chyba PDO: ' . $e->getMessage());
            http_response_code(500);
            exit('Chyba připojení k databázi. Zkuste to prosím později.');
        }
    }

    return $pdo;
}
