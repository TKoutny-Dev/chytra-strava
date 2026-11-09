<?php
declare(strict_types=1);

/**
 * demo_dotaz.php – ukázka klíčového dotazu generátoru
 * Vybere bílkovinné potraviny a seřadí je podle ceny
 * (gramy bílkovin za 1 Kč – výsledkem je „výhodnost“).
 * Aktuální slevy z tabulky akce_obchody se promítnou pomocí LEFT JOIN.
 */

require __DIR__ . '/db.php';

$pdo = getPDO();

/** Minimální počet gramů bílkovin, abychom vybrali „bílkovinné“ potraviny */
$minBilkoviny = ($_GET['min_bilkoviny'] ?? '20');
$minBilkoviny = max(0, min(100, (int) $minBilkoviny)); // ošetření na rozsah 0–100

$sql = "SELECT
            p.id,
            p.nazev,
            p.bilkoviny,
            p.bezna_cena,
            COALESCE(a.akcni_cena, p.bezna_cena) AS aktualni_cena,
            a.obchod,
            a.platnost_do,
            ROUND((p.bilkoviny / COALESCE(a.akcni_cena, p.bezna_cena)) * 100, 1)
                AS bilkoviny_za_korunu
        FROM potraviny AS p
        LEFT JOIN akce_obchody AS a
            ON a.id_potraviny = p.id
           AND a.platnost_do >= CURDATE()
        WHERE p.bilkoviny >= :min_bilkoviny
        ORDER BY bilkoviny_za_korunu DESC,
                 aktualni_cena ASC";

$statement = $pdo->prepare($sql);
$statement->execute(['min_bilkoviny' => $minBilkoviny]);
$potraviny = $statement->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Náhled – bílkoviny podle výhodnosti</title>
</head>
<body>
    <h1>Nejvýhodnější zdroje bílkovin</h1>

    <form method="get">
        <label>
            Minimální bílkoviny (g/100 g):
            <input type="number" name="min_bilkoviny" min="0" max="100"
                   value="<?= htmlspecialchars((string) $minBilkoviny) ?>">
        </label>
        <button type="submit">Filtrovat</button>
    </form>

    <table border="1" cellpadding="5">
        <tr>
            <th>Potravina</th>
            <th>Bílkoviny (g/100 g)</th>
            <th>Cena (Kč/100 g)</th>
            <th>Obchod se slevou</th>
            <th>Výhodnost (g bílkovin / 1 Kč)</th>
        </tr>
        <?php foreach ($potraviny as $potravina): ?>
        <tr>
            <td><?= htmlspecialchars($potravina['nazev']) ?></td>
            <td><?= htmlspecialchars((string) $potravina['bilkoviny']) ?></td>
            <td>
                <?= htmlspecialchars((string) $potravina['aktualni_cena']) ?>
                <?php if ($potravina['aktualni_cena'] != $potravina['bezna_cena']): ?>
                    <small>(běžně <?= htmlspecialchars((string) $potravina['bezna_cena']) ?>
                    | sleva do <?= htmlspecialchars((string) $potravina['platnost_do']) ?>)</small>
                <?php endif; ?>
            </td>
            <td><?= $potravina['obchod'] !== null ? htmlspecialchars($potravina['obchod']) : '—' ?></td>
            <td><?= htmlspecialchars((string) $potravina['bilkoviny_za_korunu']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
