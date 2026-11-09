<?php
declare(strict_types=1);

/**
 * potraviny.php – správa potravin (CRUD) pro administrátory
 * ---------------------------------------------------------------
 * C - Create: přidání potraviny (validace + prepared statement)
 * R - Read:   výpis potravin včetně aktuální ceny a slevy
 * U - Update: úprava potraviny
 * D - Delete: smazání potraviny (slevy se smažou díky
 *             ON DELETE CASCADE na cizím klíči – hezká ukázka FK!)
 *
 * Přístup: jen přihlášení a JEN admin role smí měnit data.
 * U každého POST formuláře kontrolujeme CSRF token.
 */

require_once __DIR__ . '/spolecne.php';
require_once __DIR__ . '/generator_funkce.php';   // pro nacti_potraviny_se_slevami() a esc/fmt

vyzaduj_prihlaseni();

$uzivatel = prihlaseny_uzivatel();
$pdo = getPDO();
$upravovana = null;

/* ==================== ZPRACOVNÁNÍ FORMULÁřŮ ==================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_zkontroluj()) {
        flash('Vypršela platnost formuláře, zkuste operaci opakovat.', 'warning');
        header('Location: potraviny.php');
        exit;
    }

    $akce = $_POST['akce'] ?? '';

    if (!je_admin()) {
        flash('K úpravě potravin má oprávnění jen administrátor.', 'danger');
    } elseif ($akce === 'ulozit') {
        // ---------- CREATE / UPDATE ----------
        $id       = ctype_digit((string) ($_POST['id'] ?? '')) ? (int) $_POST['id'] : null;
        $nazev    = trim((string) ($_POST['nazev'] ?? ''));
        $bilkoviny = (float) ($_POST['bilkoviny'] ?? 0);
        $sacharidy = (float) ($_POST['sacharidy'] ?? 0);
        $tuky      = (float) ($_POST['tuky'] ?? 0);
        $kalorie   = (float) ($_POST['kalorie'] ?? 0);
        $beznaCena = (float) ($_POST['bezna_cena'] ?? 0);

        // validace – pokud formulář neodpověděl správně, musíme uživateli říct přesně proč
        $ok = $nazev !== '' && mb_strlen($nazev) <= 100;
        $ok = $ok && $bilkoviny >= 0 && $bilkoviny <= 100;
        $ok = $ok && $sacharidy >= 0 && $sacharidy <= 100;
        $ok = $ok && $tuky >= 0 && $tuky <= 100;
        $ok = $ok && $kalorie >= 0 && $kalorie <= 900;
        $ok = $ok && $beznaCena > 0 && $beznaCena <= 1000;

        if (!$ok) {
            flash('Zkontrolujte zadané údaje – název i hodnoty (0–100 g, kcal 0–900, cena > 0).', 'danger');
        } elseif ($id === null) {
            $dotaz = $pdo->prepare(
                "INSERT INTO potraviny (nazev, bilkoviny, sacharidy, tuky, kalorie, bezna_cena)
                 VALUES (:nazev, :bilkoviny, :sacharidy, :tuky, :kalorie, :bezna_cena)"
            );
        } else {
            $dotaz = $pdo->prepare(
                "UPDATE potraviny
                 SET nazev = :nazev, bilkoviny = :bilkoviny, sacharidy = :sacharidy,
                     tuky = :tuky, kalorie = :kalorie, bezna_cena = :bezna_cena
                 WHERE id = :id"
            );
        }

        if ($ok) {
            $parametry = [
                'nazev'     => $nazev,
                'bilkoviny' => $bilkoviny,
                'sacharidy' => $sacharidy,
                'tuky'      => $tuky,
                'kalorie'   => $kalorie,
                'bezna_cena'=> $beznaCena,
            ];
            if ($id !== null) {
                $parametry['id'] = $id;
            }
            try {
                $dotaz->execute($parametry);
                flash($id === null ? 'Potravina byla přidána.' : 'Potravina byla upravena.');
            } catch (PDOException $e) {
                if ((int) $e->errorInfo[1] === 1062) {
                    flash('Potravina s tímto názvem už existuje.', 'danger');
                } else {
                    error_log('Chyba při ukládání potraviny: ' . $e->getMessage());
                    flash('Uložení se nepovedlo, zkuste to prosím znovu.', 'danger');
                }
            }
        }
    } elseif ($akce === 'smazat') {
        // ---------- DELETE ----------
        $id = ctype_digit((string) ($_POST['id'] ?? '')) ? (int) $_POST['id'] : null;
        if ($id !== null) {
            $dotaz = $pdo->prepare("DELETE FROM potraviny WHERE id = :id");
            $dotaz->execute(['id' => $id]);
            // díky ON DELETE CASCADE zmizely i její slevy (akce_obchody)
            flash('Potravina (i její slevy) byla smazána.', 'warning');
        }
    }

    header('Location: potraviny.php');
    exit;
}

/* ---------- úprava: načtení jedné potraviny do formuláře ---------- */
if (isset($_GET['upravit']) && ctype_digit((string) $_GET['upravit']) && je_admin()) {
    $dotaz = $pdo->prepare("SELECT * FROM potraviny WHERE id = :id");
    $dotaz->execute(['id' => (int) $_GET['upravit']]);
    $upravovana = $dotaz->fetch();
}

/* ==================== VÝPIS (READ) ==================== */
$potraviny = nacti_potraviny_se_slevami($pdo);
usort($potraviny, static fn(array $a, array $b): int => strcmp($a['nazev'], $b['nazev']));
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa potravin – OptiMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styl.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success no-tisk">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🥗 OptiMeal</a>
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="index.php">Generátor</a></li>
            <li class="nav-item"><a class="nav-link" href="demo_dotaz.php">Přehled slev</a></li>
            <li class="nav-item"><a class="nav-link active" href="potraviny.php">Správa potravin</a></li>
        </ul>
        <span class="navbar-text me-3">👤 <?= esc($uzivatel['jmeno']) ?> (<?= esc($uzivatel['role']) ?>)</span>
        <a href="odhlaseni.php" class="btn btn-outline-light btn-sm">Odhlásit</a>
    </div>
</nav>

<main class="container my-4">
    <?= zobraz_flash() ?>

    <!-- úvodní upozornění na oprávnění -->
    <?php
    $hostingPoznamka = je_admin()
        ? 'Máte plná práva – můžete potraviny přidávat, upravovat i mazat.'
        : 'Prohlížíte data. Úpravy můžou provádět jen administrátoři.';
    ?>
    <div class="alert alert-info py-2"><?= esc($hostingPoznamka) ?></div>

    <h1 class="h4 mb-3">📋 Potraviny (<?= count($potraviny) ?>)</h1>

    <!-- ==================== TABULKA VÝPISU ==================== -->
    <div class="card shadow-sm mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th class="text-end">B (g/100 g)</th>
                        <th class="text-end">S</th>
                        <th class="text-end">T</th>
                        <th class="text-end">kcal</th>
                        <th class="text-end">Běžná cena</th>
                        <th class="text-end">Aktuální cena</th>
                        <th>Sleva</th>
                        <?php if (je_admin()): ?><th class="no-tisk text-end">Akce</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($potraviny as $p): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($p['nazev']) ?></td>
                        <td class="text-end"><?= esc(fmt((float) $p['bilkoviny'])) ?></td>
                        <td class="text-end"><?= esc(fmt((float) $p['sacharidy'])) ?></td>
                        <td class="text-end"><?= esc(fmt((float) $p['tuky'])) ?></td>
                        <td class="text-end"><?= esc(fmt((float) $p['kalorie'], 0)) ?></td>
                        <td class="text-end"><?= esc(fmt((float) $p['bezna_cena'], 2)) ?> Kč</td>
                        <td class="text-end fw-semibold"><?= esc(fmt((float) $p['aktualni_cena'], 2)) ?> Kč</td>
                        <td>
                            <?php if ($p['sleva']): ?>
                                <span class="badge bg-warning text-dark">🏷️ <?= esc((string) $p['obchod']) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php if (je_admin()): ?>
                        <td class="no-tisk text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary no-tisk"
                               href="potraviny.php?upravit=<?= (int) $p['id'] ?>">Upravit</a>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Opravdu smazat potravinu „<?= esc($p['nazev']) ?>“?');">
                                <?= csrf_pole() ?>
                                <input type="hidden" name="akce" value="smazat">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ==================== FORMULÁř PŘIDAT / UPRAVIT ==================== -->
    <?php if (je_admin()): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong><?= $upravovana === false || $upravovana === null ? '➕ Přidat potravinu' : '✏️ Upravit: ' . esc($upravovana['nazev']) ?></strong>
        </div>
        <div class="card-body">
            <form method="post" action="potraviny.php" class="row g-3">
                <?= csrf_pole() ?>
                <input type="hidden" name="akce" value="ulozit">
                <input type="hidden" name="id" value="<?= $upravovana !== null ? esc($upravovana['id']) : '' ?>">

                <div class="col-md-4">
                    <label class="form-label" for="nazev">Název</label>
                    <input type="text" id="nazev" name="nazev" class="form-control" required maxlength="100"
                           value="<?= esc($upravovana['nazev'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="bilkoviny">Bílkoviny (g/100 g)</label>
                    <input type="number" step="0.01" min="0" max="100" id="bilkoviny" name="bilkoviny"
                           class="form-control" required value="<?= esc($upravovana['bilkoviny'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="sacharidy">Sacharidy</label>
                    <input type="number" step="0.01" min="0" max="100" id="sacharidy" name="sacharidy"
                           class="form-control" required value="<?= esc($upravovana['sacharidy'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="tuky">Tuky</label>
                    <input type="number" step="0.01" min="0" max="100" id="tuky" name="tuky"
                           class="form-control" required value="<?= esc($upravovana['tuky'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="kalorie">Kalorie (kcal/100 g)</label>
                    <input type="number" step="0.01" min="0" max="900" id="kalorie" name="kalorie"
                           class="form-control" required value="<?= esc($upravovana['kalorie'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="bezna_cena">Běžná cena (Kč)</label>
                    <input type="number" step="0.01" min="0.01" max="1000" id="bezna_cena" name="bezna_cena"
                           class="form-control" required value="<?= esc($upravovana['bezna_cena'] ?? '') ?>">
                </div>
                <div class="col-12 text-end">
                    <?php if ($upravovana !== null): ?>
                        <a href="potraviny.php" class="btn btn-outline-secondary me-2">Zrušit úpravu</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success">
                        <?= $upravovana !== null ? '💾 Uložit změny' : '➕ Přidat potravinu' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
