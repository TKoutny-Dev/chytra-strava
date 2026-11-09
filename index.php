<?php
declare(strict_types=1);

/**
 * index.php – hlavní strana aplikace OptiMeal
 * Obsahuje: zadání cílů (nebo výběr profilu), výstup generátoru
 * (jídelníček) a nákupní seznam s uvedením obchodů se slevami.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/generator_funkce.php';
require_once __DIR__ . '/spolecne.php';

$pdo = getPDO();

/* ---------- profily uživatelů z databáze (tabulka uzivatele) ---------- */
$uzivatele = $pdo->query(
    "SELECT id, jmeno, cilove_kalorie, cil_bilkoviny, cil_sacharidy, cil_tuky
     FROM uzivatele
     ORDER BY jmeno"
)->fetchAll();

/* ---------- řešení cílů: profil / formulář / výchozí hodnoty ---------- */

// výchozí cíle (ukázkové zadání z dokumentace – 2400 kcal, 160 g bílkovin)
$cile = ['kalorie' => 2400, 'bilkoviny' => 160, 'sacharidy' => 280, 'tuky' => 80];
$vybranyProfil = null;

// 1) pokud uživatel vybral profil, načteme cíle z databáze (prepared statement)
if (isset($_GET['uzivatel']) && ctype_digit($_GET['uzivatel'])) {
    $dotazProfil = $pdo->prepare(
        "SELECT * FROM uzivatele WHERE id = :id"
    );
    $dotazProfil->execute(['id' => (int) $_GET['uzivatel']]);
    $profil = $dotazProfil->fetch();

    if ($profil !== false) {
        $vybranyProfil = $profil;
        $cile = [
            'kalorie'   => (int) $profil['cilove_kalorie'],
            'bilkoviny' => (int) $profil['cil_bilkoviny'],
            'sacharidy' => (int) $profil['cil_sacharidy'],
            'tuky'      => (int) $profil['cil_tuky'],
        ];
    }
} elseif (isset($_GET['cil_kalorie'], $_GET['cil_bilkoviny'], $_GET['cil_sacharidy'], $_GET['cil_tuky'])) {
    // 2) ručně zadané hodnoty z formuláře – vždy argumentmi (int) = bezpečné
    $cile = [
        'kalorie'   => clamp_int((int) ($_GET['cil_kalorie'] ?? 2400), 800, 5000),
        'bilkoviny' => clamp_int((int) ($_GET['cil_bilkoviny'] ?? 160), 40, 300),
        'sacharidy' => clamp_int((int) ($_GET['cil_sacharidy'] ?? 280), 0, 600),
        'tuky'      => clamp_int((int) ($_GET['cil_tuky'] ?? 80), 20, 200),
    ];
}

/* ---------- spustí generátor? (jen máme-li zadány cíle) ---------- */
$generovat = isset($_GET['generovat'])
    || isset($_GET['uzivatel'])
    || isset($_GET['cil_kalorie']);

$jidelnicekVysledek = null;
if ($generovat) {
    $jidelnicekVysledek = vygeneruj_jidelnicek($pdo, $cile);
    $nakupniSeznam = sestav_nakupni_seznam($jidelnicekVysledek['jidelnicek']);
}

/* ---------- pomocná funkce pro panel cíle vs. realita ---------- */
function panelTik(int $aktualni, float $cilovy): array
{
    $odchylka = ($cilovy > 0) ? ($aktualni - $cilovy) / $cilovy * 100 : 0.0;
    $shoda = clamp_int((int) round(100 - abs($odchylka)), 0, 100);

    $trida = 'bg-success';
    if ($shoda < 70) {
        $trida = 'bg-danger';
    } elseif ($shoda < 90) {
        $trida = 'bg-warning text-dark';
    }

    return ['shoda' => $shoda, 'trida' => $trida];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OptiMeal – chytrá strava a nákupní seznam</title>

    <!-- Bootstrap 5 z CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- přizpůsobení stylu -->
    <link href="styl.css" rel="stylesheet">
</head>
<body>

<!-- ====================== NAVIGACE ====================== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success no-tisk">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🥗 OptiMeal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active" href="index.php">Generátor</a></li>
                <li class="nav-item"><a class="nav-link" href="demo_dotaz.php">Přehled slev</a></li>
                <?php if (je_admin()): ?>
                    <li class="nav-item"><a class="nav-link" href="potraviny.php">Správa potravin</a></li>
                    <li class="nav-item"><a class="nav-link" href="slevy.php">Správa slev</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text">
                <?php $prihlaseny = prihlaseny_uzivatel(); ?>
                <?php if ($prihlaseny !== null): ?>
                    👤 <?= esc($prihlaseny['jmeno']) ?>
                    <a href="odhlaseni.php" class="btn btn-outline-light btn-sm ms-2">Odhlásit</a>
                <?php else: ?>
                    <a href="prihlaseni.php" class="btn btn-light btn-sm">Přihlásit se</a>
                    <a href="registrace.php" class="btn btn-outline-light btn-sm">Registrace</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?= zobraz_flash() ?>

    <div class="text-center mb-4">
        <h1 class="fw-bold">Chytrý generátor jídelníčku</h1>
        <p class="text-muted">
            Zadejte denní cíle a generátor sestaví jídelníček i nákupní seznam –
            <strong>s prioritou právě probíhajících slev v obchodech</strong>.
        </p>
    </div>

    <!-- ====================== FORMULÁŘ CÍLŮ ====================== -->
    <div class="card shadow-sm mb-5 no-tisk">
        <div class="card-header bg-white"><strong>Denní cíle</strong></div>
        <div class="card-body">
            <form method="get" action="index.php" class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="uzivatel">Profil uživatele (z databáze)</label>
                    <select name="uzivatel" id="uzivatel" class="form-select" onchange="this.form.submit()">
                        <option value="">— vlastní cíle (ruční zadání) —</option>
                        <?php foreach ($uzivatele as $u): ?>
                            <option value="<?= esc($u['id']) ?>"
                                <?= ($vybranyProfil !== null && (int) $vybranyProfil['id'] === (int) $u['id']) ? 'selected' : '' ?>>
                                <?= esc($u['jmeno']) ?> (<?= fmt((float) $u['cilove_kalorie'], 0) ?> kcal)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Výběr profilu přepíše ručně zadané hodnoty.</div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_kalorie">Kalorie (kcal)</label>
                    <input type="number" id="cil_kalorie" name="cil_kalorie" class="form-control"
                           min="800" max="5000" required
                           value="<?= esc($cile['kalorie']) ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_bilkoviny">Bílkoviny (g)</label>
                    <input type="number" id="cil_bilkoviny" name="cil_bilkoviny" class="form-control"
                           min="40" max="300" required
                           value="<?= esc($cile['bilkoviny']) ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_sacharidy">Sacharidy (g)</label>
                    <input type="number" id="cil_sacharidy" name="cil_sacharidy" class="form-control"
                           min="0" max="600" required
                           value="<?= esc($cile['sacharidy']) ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_tuky">Tuky (g)</label>
                    <input type="number" id="cil_tuky" name="cil_tuky" class="form-control"
                           min="20" max="200" required
                           value="<?= esc($cile['tuky']) ?>">
                </div>

                <div class="col-12 text-end">
                    <input type="hidden" name="generovat" value="1">
                    <button type="submit" class="btn btn-success btn-lg px-4">
                        🍽️ Vygenerovat jídelníček
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($jidelnicekVysledek === null): ?>
        <div class="alert alert-info no-tisk">
            Nastavte cíle výše (nebo vyberte profil) a klikněte na
            <strong>„Vygenerovat jídelníček"</strong>.
        </div>
    <?php else: ?>
        <?php $celkem = $jidelnicekVysledek['celkem']; ?>

        <!-- ====================== SOUHRN CÍLŮ ====================== -->
        <h2 class="h4 mb-3">Skutečnost vs. cíle</h2>
        <div class="row g-3 mb-5">
            <?php
            $metry = [
                'Kalorie'   => [(float) $celkem['kalorie'],   (float) $cile['kalorie']],
                'Bílkoviny' => [(float) $celkem['bilkoviny'], (float) $cile['bilkoviny']],
                'Sacharidy' => [(float) $celkem['sacharidy'], (float) $cile['sacharidy']],
                'Tuky'      => [(float) $celkem['tuky'],      (float) $cile['tuky']],
            ];
            foreach ($metry as $nazev => [$aktualni, $cilovy]):
                $panel = panelTik((int) round($aktualni), $cilovy);
            ?>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold"><?= esc($nazev) ?></span>
                            <span class="badge bg-light text-dark"><?= esc((string) $panel['shoda']) ?> % shody</span>
                        </div>
                        <div class="fs-4">
                            <?= esc(fmt($aktualni, 0)) ?>
                            <small class="text-muted">/ <?= esc(fmt($cilovy, 0)) ?></small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar <?= esc($panel['trida']) ?>"
                                 style="width: <?= esc((string) $panel['shoda']) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ====================== JÍDELNÍČEK ====================== -->
        <h2 class="h4 mb-3">Jídelníček na den</h2>
        <div class="row g-3 mb-5">
            <?php foreach ($jidelnicekVysledek['jidelnicek'] as $nazevJidla => $jidloDenne): ?>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm karta-jidla">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong><?= esc($nazevJidla) ?></strong>
                        <span class="text-muted small"><?= esc(fmt((float) $jidloDenne['kalorie'], 0)) ?> kcal</span>
                    </div>
                    <?php if ($jidloDenne['jidla'] === []): ?>
                        <div class="card-body text-muted small">Pro toto jídlo se nenašly vhodné potraviny.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($jidloDenne['jidla'] as $jidlo): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <span class="fw-semibold"><?= esc($jidlo['nazev']) ?></span>
                                <span><?= esc(fmt((float) $jidlo['gramy'], 0)) ?> g</span>
                            </div>
                            <div class="d-flex justify-content-between flex-wrap gap-2 small text-muted">
                                <span>B: <?= esc(fmt((float) $jidlo['bilkoviny'])) ?> |
                                    S: <?= esc(fmt((float) $jidlo['sacharidy'])) ?> |
                                    T: <?= esc(fmt((float) $jidlo['tuky'])) ?> g</span>
                                <?php if ($jidlo['sleva']): ?>
                                    <span class="badge bg-warning text-dark">🏷️ sleva: <?= esc($jidlo['obchod']) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="card-footer bg-white small">
                        Denní cena jídla: <strong><?= esc(fmt((float) $jidloDenne['cena'], 2)) ?> Kč</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ====================== NÁKUPNÍ SEZNAM ====================== -->
        <h2 class="h4 mb-3">🛒 Nákupní seznam</h2>
        <div class="card shadow-sm mb-2 karta-tisk">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Potravina</th>
                            <th class="text-end">Množství</th>
                            <th class="text-end">Cena / 100 g</th>
                            <th>Obchod (nejlevnější)</th>
                            <th class="text-end">Cena celkem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $celkovaCena = 0.0; ?>
                        <?php foreach ($nakupniSeznam as $polozka): ?>
                            <?php $celkovaCena += (float) $polozka['cena_celkem']; ?>
                            <tr>
                                <td>
                                    <?= esc($polozka['nazev']) ?>
                                    <?php if ($polozka['sleva']): ?>
                                        <span class="badge bg-warning text-dark ms-1">sleva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= esc(fmt((float) $polozka['gramy'], 0)) ?> g</td>
                                <td class="text-end"><?= esc(fmt((float) $polozka['cena_za_100g'], 2)) ?> Kč</td>
                                <td>
                                    <?= $polozka['obchod'] !== null ? esc($polozka['obchod']) : '—' ?>
                                </td>
                                <td class="text-end"><?= esc(fmt((float) $polozka['cena_celkem'], 2)) ?> Kč</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Celkem den nákupu:</th>
                            <th class="text-end"><?= esc(fmt($celkovaCena, 2)) ?> Kč</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-5 no-tisk">
            <span class="text-muted small">
                Ve sloupci obchodu je vždy obchod s nejlevnější <strong>aktuální</strong> slevou
                (sleva musí být stále platná &ndash; <em>platnost_do ≥ dnešní datum</em>).
            </span>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                🖨️ Vytisknout seznam
            </button>
        </div>
    <?php endif; ?>
</main>

<footer class="text-center text-muted small pb-4 no-tisk">
    OptiMeal – maturitní projekt • PHP 8 + MySQL (PDO) + Bootstrap 5 •
    <a href="demo_dotaz.php">technický náhled dotazu</a>
</footer>

<!-- Bootstrap JS (rozbalovací menu) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
