<?php
declare(strict_types=1);

/**
 * db.php – připojení k databázi přes PDO
 * Vrací jeden sdílený PDO objekt pro celou aplikaci.
 *
 * Na XAMPP je výchozí účet root s prázdným heslem.
 * Při nahrání na hosting se změní údaje tady.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'chytra_strava');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');   // důležité kvůli české diakritice

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
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
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
