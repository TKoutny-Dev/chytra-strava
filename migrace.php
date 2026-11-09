<?php
declare(strict_types=1);

/**
 * migrace.php – JEDNORÁZOVÝ skript pro aktualizaci struktury databáze
 * ---------------------------------------------------------------
 * Spustí migraci č. 1 (rozšíření tabulky uzivatele: email, hash_hesla,
 * role + 12 nových potravin + slevy + ukázkové účty).
 *
 * Ochranu zajišťuje TOKEN v parametru URL:
 *      migrace.php?token=optimeal2026
 * Po úspěšné migraci tento soubor SMAŽTE (i kvůli obhajobě:
 * „jednorázové operace nesmí zůstat dostupné na produkci").
 * Totéž umí i databaze_migrace.sql v phpMyAdmin – tohle je jen
 * pohodlnější cesta bez přihlašování do panelu.
 */

require_once __DIR__ . '/spolecne.php';

$TOKEN = 'optimeal2026';   // tajný token – po migraci smažeme celý soubor

if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(403);
    exit('Neautorizovýno – chybí nebo je špatný token (migrace.php?token=...).');
}

$pdo = getPDO();

/* ---------- postupné SQL kroky migrace č. 1 ---------- */
$kroky = [
    'Rozšíření tabulky uzivatele (email, hash_hesla, role)' =>
        "ALTER TABLE uzivatele
            ADD COLUMN IF NOT EXISTS email VARCHAR(150) NOT NULL DEFAULT '' AFTER jmeno,
            ADD COLUMN IF NOT EXISTS hash_hesla VARCHAR(255) NULL AFTER email,
            ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'uzivatel' AFTER email",

    'Unikátní index na e-mail' =>
        "CREATE UNIQUE INDEX IF NOT EXISTS unique_email ON uzivatele (email)",

    'Nové potraviny (12)' =>
        "INSERT IGNORE INTO potraviny (nazev, bilkoviny, sacharidy, tuky, kalorie, bezna_cena) VALUES
        ('Hovězí svíčková',   27.00,  0.00, 11.00, 209.00, 28.00),
        ('Losos',             20.00,  0.00, 13.00, 208.00, 32.00),
        ('Mléko čerstvé',      3.30,  4.80,  3.60,  64.00,  2.50),
        ('Banán',              1.10, 23.00,  0.30,  97.00,  3.00),
        ('Brambory',           2.00, 17.00,  0.10,  77.00,  2.00),
        ('Těstoviny celozrnné',13.00, 65.00,  2.50, 335.00,  5.50),
        ('Chléb celozrnný',    9.00, 46.00,  3.50, 253.00,  4.50),
        ('Quinoa',            14.00, 57.00,  6.00, 368.00, 22.00),
        ('Sýr Niva',          21.00,  0.50, 32.00, 371.00, 18.00),
        ('Pohanka',           13.00, 62.00,  3.40, 343.00,  6.50),
        ('Jablko',             0.30, 14.00,  0.20,  58.00,  3.50),
        ('Arašidy',           26.00,  9.00, 49.00, 567.00,  8.00)",

    'Sleva: Hovězí svíčková (Kaufland)' =>
        "INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
            SELECT id, 'Kaufland', 24.90, '2030-12-31' FROM potraviny WHERE nazev = 'Hovězí svíčková'
            ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do)",

    'Sleva: Losos (Kaufland)' =>
        "INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
            SELECT id, 'Kaufland', 29.90, '2030-12-31' FROM potraviny WHERE nazev = 'Losos'
            ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do)",

    'Sleva: Mléko čerstvé (Billa)' =>
        "INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
            SELECT id, 'Billa', 1.99, '2030-12-31' FROM potraviny WHERE nazev = 'Mléko čerstvé'
            ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do)",

    'Sleva: Těstoviny celozrnné (Tesco)' =>
        "INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
            SELECT id, 'Tesco', 3.90, '2030-12-31' FROM potraviny WHERE nazev = 'Těstoviny celozrnné'
            ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do)",

    'Sleva: Brambory (Billa)' =>
        "INSERT INTO akce_obchody (id_potraviny, obchod, akcni_cena, platnost_do)
            SELECT id, 'Billa', 1.49, '2030-12-31' FROM potraviny WHERE nazev = 'Brambory'
            ON DUPLICATE KEY UPDATE akcni_cena = VALUES(akcni_cena), platnost_do = VALUES(platnost_do)",

    'Ukázkové účty (Admin/Petr)' =>
        "INSERT IGNORE INTO uzivatele (jmeno, email, hash_hesla, role, cilove_kalorie, cil_bilkoviny, cil_sacharidy, cil_tuky) VALUES
        ('Admin', 'admin@optimeal.cz', '\$2y\$12\$opovmRFlCkFU42abs3UkKej.Wf3Apfitg5pH1dkcKQf.Y8RZo9PG6', 'admin', 2400, 160, 280, 80),
        ('Petr Novák', 'petr@optimeal.cz', '\$2y\$12\$EK/EDIuQlbYzKmcqpEPEK.u3G1YROlgBA/EpZztTHzth3EW/z69zO', 'uzivatel', 2200, 150, 250, 70)",
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Migrace DB – OptiMeal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        .ok { color: #0a7; } .chyba { color: #c22; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
    </style>
</head>
<body>
<h1>🔧 Migrace databáze č. 1</h1>
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
<ul>
    <?php
    $kontrola = $pdo->query("SELECT
            (SELECT COUNT(*) FROM potraviny)      AS potraviny,
            (SELECT COUNT(*) FROM akce_obchody)   AS slevy,
            (SELECT COUNT(*) FROM uzivatele)      AS uzivatele")->fetch();
    ?>
    <li>Potraviny: <strong><?= (int) $kontrola['potraviny'] ?></strong></li>
    <li>Slevy: <strong><?= (int) $kontrola['slevy'] ?></strong></li>
    <li>Uživatelé: <strong><?= (int) $kontrola['uzivatele'] ?></strong></li>
</ul>

<p><strong>⚠️ Migrace proběhla? TENTO SOUBOR SMAŽTE.</strong> (bezpečnost na produkci)</p>
</body>
</html>
