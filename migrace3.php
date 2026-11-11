<?php
declare(strict_types=1);

/**
 * migrace3.php – JEDNORÁZOVÝ skript pro aktualizaci struktury databáze
 * ---------------------------------------------------------------
 * Migrace č. 3: volitelný údaj o tělesném tuku v % – když ho uživatel
 * vyplní, počítají se cíle přesnějším vzorcem Katch-McArdle
 * (viz cile_funkce.php a profil.php).
 *
 * Ochranu zajišťuje TOKEN v parametru URL:
 *      migrace3.php?token=optimeal2026
 * Po úspěšné migraci tento soubor SMAŽTE z produkce.
 */

require_once __DIR__ . '/spolecne.php';

$TOKEN = 'optimeal2026';   // tajný token – po migraci smažeme celý soubor

if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(403);
    exit('Neautorizováno – chybí nebo je špatný token (migrace3.php?token=...).');
}

$pdo = getPDO();

/* ---------- SQL kroky migrace č. 3 ---------- */
$kroky = [
    'Volitelný údaj o tělesném tuku v % (pro vzorec Katch-McArdle)' =>
        "ALTER TABLE uzivatele
            ADD COLUMN IF NOT EXISTS telo_tuk DECIMAL(4,1) NULL AFTER cil_telo",
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Migrace DB č. 3 – OptiMeal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        .ok { color: #0a7; } .chyba { color: #c22; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
    </style>
</head>
<body>
<h1>🔧 Migrace databáze č. 3</h1>
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
    $sloupce = $pdo->query("SHOW COLUMNS FROM uzivatele")->fetchAll(PDO::FETCH_COLUMN);
    echo '<ul><li>Sloupce uzivatele: <strong>' . esc(implode(', ', $sloupce)) . '</strong></li></ul>';
} catch (PDOException $e) {
    echo '<p class="chyba">' . esc($e->getMessage()) . '</p>';
}
?>
<p><strong>⚠️ Migrace proběhla? TENTO SOUBOR SMAŽTE.</strong> (bezpečnost na produkci)</p>
</body>
</html>
