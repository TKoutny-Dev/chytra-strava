<?php
declare(strict_types=1);

/**
 * migrace4.php – JEDNORÁZOVÝ skript pro aktualizaci struktury databáze
 * ---------------------------------------------------------------
 * Migrace č. 4: tabulka plany – uložené jídelníčky uživatelů.
 * Vygenerovaný plán (denní i týdenní) se ukládá jako JSON,
 * aby si uživatel mohl později plán otevřít znovu.
 *
 * Ochranu zajišťuje TOKEN v parametru URL:
 *      migrace4.php?token=optimeal2026
 * Po úspěšné migraci tento soubor SMAŽTE z produkce.
 */

require_once __DIR__ . '/spolecne.php';

$TOKEN = 'optimeal2026';   // tajný token – po migraci smažeme celý soubor

if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(403);
    exit('Neautorizováno – chybí nebo je špatný token (migrace4.php?token=...).');
}

$pdo = getPDO();

/* ---------- SQL kroky migrace č. 4 ---------- */
$kroky = [
    'Tabulka plany – uložené jídelníčky (JSON)' =>
        "CREATE TABLE IF NOT EXISTS plany (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_uzivatele    INT UNSIGNED NOT NULL,
            vytvoreno       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            typ             VARCHAR(10) NOT NULL DEFAULT 'den',
            cil_kalorie     SMALLINT UNSIGNED NOT NULL,
            cil_bilkoviny   SMALLINT UNSIGNED NOT NULL,
            cil_sacharidy   SMALLINT UNSIGNED NOT NULL,
            cil_tuky        SMALLINT UNSIGNED NOT NULL,
            data            LONGTEXT NOT NULL,
            INDEX idx_plany_uzivatel (id_uzivatele, vytvoreno)
        )",
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Migrace DB č. 4 – OptiMeal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        .ok { color: #0a7; } .chyba { color: #c22; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
    </style>
</head>
<body>
<h1>🔧 Migrace databáze č. 4</h1>
<table>
    <tr><th>Krok</th><th>Výsledek</th></tr>
    <?php foreach ($kroky as $popis => $sql): ?>
        <?php
        try {
            $pdo->exec($sql);
            $stav = '<span class="ok">✔ provedeno</span>';
        } catch (PDOException $e) {
            $stav = '<span class="chyba">✘ ' . esc($e->getMessage()) . '</span>';
        }
        ?>
        <tr><td><?= esc($popis) ?></td><td><?= $stav ?></td></tr>
    <?php endforeach; ?>
</table>

<h2>Nový stav databáze</h2>
<?php
try {
    $tabulky = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<ul><li>Tabulky: <strong>' . esc(implode(', ', $tabulky)) . '</strong></li></ul>';
    if (in_array('plany', $tabulky, true)) {
        $sloupce = $pdo->query("SHOW COLUMNS FROM plany")->fetchAll(PDO::FETCH_COLUMN);
        echo '<ul><li>Sloupce plany: <strong>' . esc(implode(', ', $sloupce)) . '</strong></li></ul>';
    }
} catch (PDOException $e) {
    echo '<p class="chyba">' . esc($e->getMessage()) . '</p>';
}
?>
<p><strong>⚠️ Migrace proběhla? TENTO SOUBOR SMAŽTE.</strong> (bezpečnost na produkci)</p>
</body>
</html>
