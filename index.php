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
<html lang="<?= esc(aktualni_jazyk()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('titulek_hlavni')) ?></title>

    <!-- Bootstrap 5 z CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- písmo Inter (	optimalizované číslice, čistý moderní vzhled) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <!-- přizpůsobení stylu -->
    <link href="styl.css" rel="stylesheet">
    <!-- MOTIV: nastavíme hned před vykreslením, aby stránka NEPROBLIKLA chybou barvou -->
    <script>
        (function () {
            try {
                var t = localStorage.getItem('optimeal-motiv') || 'system';
                var dark = t === 'dark'
                    || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
                document.documentElement.setAttribute('data-motiv', t);
            } catch (e) { /* localStorage blokován -> necháme světlo */ }
        })();
    </script>
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
                <li class="nav-item"><a class="nav-link active" href="index.php"><?= esc(t('nav_generator')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="demo_dotaz.php"><?= esc(t('nav_slevy_prehled')) ?></a></li>
                <?php if (je_admin()): ?>
                    <li class="nav-item"><a class="nav-link" href="potraviny.php"><?= esc(t('nav_potraviny')) ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="slevy.php"><?= esc(t('nav_slevy')) ?></a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <!-- přepínač jazyka (zapamatuje se v session) -->
                    <a class="nav-link px-2" href="<?= esc(jazyk_odkaz('cs')) ?>" title="Čeština" lang="cs">🇨🇿</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-2" href="<?= esc(jazyk_odkaz('en')) ?>" title="English" lang="en">🇬🇧</a>
                </li>
                <?php $_UI_KUS = 'tlacitko'; require __DIR__ . '/ui_nastaveni.php'; ?>
            </ul>
            <span class="navbar-text">
                <?php $prihlaseny = prihlaseny_uzivatel(); ?>
                <?php if ($prihlaseny !== null): ?>
                    👤 <?= esc($prihlaseny['jmeno']) ?>
                    <a href="odhlaseni.php" class="btn btn-outline-light btn-sm ms-2"><?= esc(t('nav_odhlasit')) ?></a>
                <?php else: ?>
                    <a href="prihlaseni.php" class="btn btn-light btn-sm"><?= esc(t('nav_prihlasit')) ?></a>
                    <a href="registrace.php" class="btn btn-outline-light btn-sm"><?= esc(t('nav_registrace')) ?></a>
                <?php endif; ?>
            </span>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?= zobraz_flash() ?>

    <div class="om-hero text-center mb-4">
        <span class="om-odrazka">🥗 &nbsp;<?= esc(t('hero_odrazka')) ?></span>
        <h1 class="om-hero-nadpis fs-1 fw-bold mb-2"><?= esc(t('hero_nadpis')) ?></h1>
        <p class="text-muted mx-auto" style="max-width: 620px;">
            <?= t('hero_podtext') /* obsahuje vlastní <strong> – vědomě bez esc */ ?>
        </p>
    </div>

    <!-- ====================== FORMULÁŘ CÍLŮ ====================== -->
    <div class="card shadow-sm mb-5 no-tisk">
        <div class="card-header bg-white"><strong><?= esc(t('cile_nadpis')) ?></strong></div>
        <div class="card-body">
            <form method="get" action="index.php" class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="uzivatel"><?= esc(t('profil_label')) ?></label>
                    <select name="uzivatel" id="uzivatel" class="form-select" onchange="this.form.submit()">
                        <option value=""><?= esc(t('profil_vlastni')) ?></option>
                        <?php foreach ($uzivatele as $u): ?>
                            <option value="<?= esc($u['id']) ?>"
                                <?= ($vybranyProfil !== null && (int) $vybranyProfil['id'] === (int) $u['id']) ? 'selected' : '' ?>>
                                <?= esc($u['jmeno']) ?> (<?= fmt((float) $u['cilove_kalorie'], 0) ?> kcal)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?= esc(t('profil_hint')) ?></div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_kalorie"><?= esc(t('kcal_label')) ?></label>
                    <input type="number" id="cil_kalorie" name="cil_kalorie" class="form-control"
                           min="800" max="5000" required
                           value="<?= esc($cile['kalorie']) ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_bilkoviny"><?= esc(t('bilkoviny_label')) ?></label>
                    <input type="number" id="cil_bilkoviny" name="cil_bilkoviny" class="form-control"
                           min="40" max="300" required
                           value="<?= esc($cile['bilkoviny']) ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_sacharidy"><?= esc(t('sacharidy_label')) ?></label>
                    <input type="number" id="cil_sacharidy" name="cil_sacharidy" class="form-control"
                           min="0" max="600" required
                           value="<?= esc($cile['sacharidy']) ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="cil_tuky"><?= esc(t('tuky_label')) ?></label>
                    <input type="number" id="cil_tuky" name="cil_tuky" class="form-control"
                           min="20" max="200" required
                           value="<?= esc($cile['tuky']) ?>">
                </div>

                <div class="col-12 text-end">
                    <input type="hidden" name="generovat" value="1">
                    <button type="submit" class="btn btn-success btn-lg px-4">
                        <?= esc(t('tlacitko_generovat')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($jidelnicekVysledek === null): ?>
        <div class="alert alert-info no-tisk">
            <?= t('prazdno_zprava') /* obsahuje vlastní <strong> – vědomě bez esc */ ?>
        </div>
    <?php else: ?>
        <?php $celkem = $jidelnicekVysledek['celkem']; ?>

        <!-- ====================== SOUHRN CÍLŮ ====================== -->
        <h2 class="h4 mb-3"><?= esc(t('skutecnost_cile')) ?></h2>
        <div class="row g-3 mb-5">
            <?php
            $metry = [
                'metrika_kalorie'   => [(float) $celkem['kalorie'],   (float) $cile['kalorie']],
                'metrika_bilkoviny' => [(float) $celkem['bilkoviny'], (float) $cile['bilkoviny']],
                'metrika_sacharidy' => [(float) $celkem['sacharidy'], (float) $cile['sacharidy']],
                'metrika_tuky'      => [(float) $celkem['tuky'],      (float) $cile['tuky']],
            ];
            foreach ($metry as $nazev => [$aktualni, $cilovy]):
                $panel = panelTik((int) round($aktualni), $cilovy);
            ?>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold"><?= esc(t($nazev)) ?></span>
                            <span class="badge bg-light text-dark"><?= esc(t('shoda_znacka', ['%shoda%' => (string) $panel['shoda']])) ?></span>
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
        <h2 class="h4 mb-3"><?= esc(t('jidelnicek_nadpis')) ?></h2>
        <div class="row g-3 mb-5">
            <?php foreach ($jidelnicekVysledek['jidelnicek'] as $nazevJidla => $jidloDenne): ?>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm karta-jidla">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong><?= esc(t('jidlo_' . $nazevJidla)) ?></strong>
                        <span class="text-muted small"><?= esc(fmt((float) $jidloDenne['kalorie'], 0)) ?> kcal</span>
                    </div>
                    <?php if ($jidloDenne['jidla'] === []): ?>
                        <div class="card-body text-muted small"><?= esc(t('prazdny_jidlo')) ?></div>
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
                                    <span class="badge bg-warning text-dark"><?= esc(t('sleva_badge')) ?> <?= esc($jidlo['obchod']) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="card-footer bg-white small">
                        <?= esc(t('denni_cena_jidla')) ?> <strong><?= esc(fmt((float) $jidloDenne['cena'], 2)) ?> Kč</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ====================== NÁKUPNÍ SEZNAM ====================== -->
        <h2 class="h4 mb-3"><?= esc(t('nakup_nadpis')) ?></h2>
        <div class="card shadow-sm mb-2 karta-tisk">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= esc(t('th_potravina')) ?></th>
                            <th class="text-end"><?= esc(t('th_mnozstvi')) ?></th>
                            <th class="text-end"><?= esc(t('th_cena_100g')) ?></th>
                            <th><?= esc(t('th_obchod')) ?></th>
                            <th class="text-end"><?= esc(t('th_cena_celkem')) ?></th>
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
                                        <span class="badge bg-warning text-dark ms-1"><?= esc(t('sleva_kratky')) ?></span>
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
                            <th colspan="4" class="text-end"><?= esc(t('celkem_den')) ?></th>
                            <th class="text-end"><?= esc(fmt($celkovaCena, 2)) ?> Kč</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-5 no-tisk">
            <span class="text-muted small">
                <?= t('poznamka_obchody') /* obsahuje vlastní <strong>/<em> – vědomě bez esc */ ?>
            </span>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <?= esc(t('tlacitko_tisk')) ?>
            </button>
        </div>
    <?php endif; ?>
</main>

<footer class="text-center text-muted small pb-4 no-tisk">
    <?= esc(t('paticka_text')) ?> •
    <a href="demo_dotaz.php"><?= esc(t('paticka_nahled')) ?></a>
</footer>

<!-- Bootstrap JS (rozbalovací menu) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- přepínač motivu (světlo / tma / systém) -->
<script src="motiv.js"></script>
<?php $_UI_KUS = 'modal'; require __DIR__ . '/ui_nastaveni.php'; ?>
</body>
</html>
