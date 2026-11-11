<?php
declare(strict_types=1);

/**
 * plany.php – mé uložené plány (historie jídelníčků)
 * ---------------------------------------------------------------
 * - seznam plánů PŘIHLÁŠENÉHO uživatele (každý vidí jen své,
 *   admin smí otevřít kterýkoliv – ukázky na obhajobě)
 * - detail plánu: data jsou uložena jako JSON (viz index.php,
 *   akce uloz_plan), výstup generátoru je deterministický
 * - mazání plánu: POST + CSRF, id vždy ověřeno proti vlastníku
 */

require_once __DIR__ . '/spolecne.php';
require_once __DIR__ . '/generator_funkce.php';   // sestav_nakupni_seznam()

vyzaduj_prihlaseni();

$uzivatel = prihlaseny_uzivatel();
$idUzivatele = (int) $uzivatel['id'];
$pdo = getPDO();

/* ==================== POST: smazání plánu ==================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_zkontroluj()) {
        flash(t('chyba_csrf'), 'warning');
        header('Location: plany.php');
        exit;
    }

    if ((string) ($_POST['akce'] ?? '') === 'smazat_plan') {
        // WHERE obsahuje i id_uzivatele – nelze smazat cizí plán
        $smaz = $pdo->prepare("DELETE FROM plany WHERE id = :id AND id_uzivatele = :u");
        $smaz->execute(['id' => (int) ($_POST['plan'] ?? 0), 'u' => $idUzivatele]);

        flash($smaz->rowCount() > 0 ? t('plany_smazano') : t('plan_nenalezen'));
    }

    header('Location: plany.php');
    exit;
}

/* ==================== DETAIL PLÁNU (?plan=N) ==================== */

$planDetail = null;
if (isset($_GET['plan']) && ctype_digit($_GET['plan'])) {
    // admin smí otevřít kterýkoliv plán, běžný uživatel jen svůj
    $sql = je_admin()
        ? "SELECT * FROM plany WHERE id = :id"
        : "SELECT * FROM plany WHERE id = :id AND id_uzivatele = :u";
    $dotaz = $pdo->prepare($sql);
    $dotaz->execute(['id' => (int) $_GET['plan']] + (je_admin() ? [] : ['u' => $idUzivatele]));
    $radek = $dotaz->fetch();

    if ($radek === false) {
        flash(t('plan_nenalezen'), 'warning');
        header('Location: plany.php');
        exit;
    }

    $planDetail = [
        'radek'    => $radek,
        'cile'     => json_decode((string) $radek['data'], true)['cile'] ?? null,
        'vysledek' => json_decode((string) $radek['data'], true)['vysledek'] ?? null,
    ];
}

/* ==================== SEZNAM PLÁNŮ ==================== */

$plany = $pdo->prepare(
    "SELECT id, vytvoreno, typ, cil_kalorie, cil_bilkoviny, cil_sacharidy, cil_tuky
     FROM plany
     WHERE id_uzivatele = :u
     ORDER BY vytvoreno DESC
     LIMIT 50"
);
$plany->execute(['u' => $idUzivatele]);
$planySeznam = $plany->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= esc(aktualni_jazyk()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('plany_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="styl.css?v=<?= (int) filemtime(__DIR__ . '/styl.css') ?>" rel="stylesheet">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('optimeal-motiv') || 'dark';
                var dark = t === 'dark'
                    || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
                document.documentElement.setAttribute('data-motiv', t);
            } catch (e) { /* localStorage blokován -> necháme světlo */ }
        })();
    </script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success no-tisk">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🥗 OptiMeal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php"><?= esc(t('nav_generator')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="demo_dotaz.php"><?= esc(t('nav_slevy_prehled')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="profil.php"><?= esc(t('nav_profil')) ?></a></li>
                <li class="nav-item"><a class="nav-link active" href="plany.php"><?= esc(t('nav_plany')) ?></a></li>
                <?php if (prihlaseny_uzivatel() !== null): ?>
                    <li class="nav-item"><a class="nav-link" href="katalog.php"><?= esc(t('nav_katalog')) ?></a></li>
                <?php endif; ?>
                <?php if (je_admin()): ?>
                    <li class="nav-item"><a class="nav-link" href="potraviny.php"><?= esc(t('nav_potraviny')) ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="slevy.php"><?= esc(t('nav_slevy')) ?></a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('cs')) ?>" title="Čeština" lang="cs">🇨🇿</a></li>
                <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('en')) ?>" title="English" lang="en">🇬🇧</a></li>
                <?php $_UI_KUS = 'tlacitko'; require __DIR__ . '/ui_nastaveni.php'; ?>
                <li class="nav-item"><span class="navbar-text px-2">👤 <?= esc($uzivatel['jmeno']) ?></span></li>
                <li class="nav-item"><a class="nav-link" href="odhlaseni.php"><?= esc(t('nav_odhlasit')) ?></a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?= zobraz_flash() ?>

    <?php if ($planDetail === null): ?>
        <!-- ==================== SEZNAM PLÁNŮ ==================== -->
        <div class="om-hero text-center mb-4">
            <h1 class="om-hero-nadpis fs-2 fw-bold mb-1"><?= esc(t('plany_h1')) ?></h1>
            <p class="text-muted mb-0"><?= esc(t('plany_podtext')) ?></p>
        </div>

        <?php if ($planySeznam === []): ?>
            <div class="alert alert-info no-tisk">
                <?= t('plany_prazdno') /* obsahuje vlastní <strong> – vědomě bez esc */ ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= esc(t('plany_th_datum')) ?></th>
                                <th><?= esc(t('plany_th_typ')) ?></th>
                                <th><?= esc(t('plany_th_cile')) ?></th>
                                <th class="text-end"><?= esc(t('th_akce')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($planySeznam as $p): ?>
                                <tr>
                                    <td><?= esc(date('j. n. Y H:i', strtotime((string) $p['vytvoreno']))) ?></td>
                                    <td>
                                        <?= esc($p['typ'] === 'tyden' ? t('plany_typ_tyden') : t('plany_typ_den')) ?>
                                    </td>
                                    <td class="small">
                                        <?= esc(fmt((float) $p['cil_kalorie'], 0)) ?> kcal
                                        · B <?= esc(fmt((float) $p['cil_bilkoviny'], 0)) ?> g
                                        · S <?= esc(fmt((float) $p['cil_sacharidy'], 0)) ?> g
                                        · T <?= esc(fmt((float) $p['cil_tuky'], 0)) ?> g
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-success me-1"
                                           href="plany.php?plan=<?= (int) $p['id'] ?>"><?= esc(t('plany_otevrit')) ?></a>
                                        <form method="post" action="plany.php" class="d-inline"
                                              onsubmit="return confirm('<?= esc(t('plany_potvrdit_smazani')) ?>');">
                                            <?= csrf_pole() ?>
                                            <input type="hidden" name="akce" value="smazat_plan">
                                            <input type="hidden" name="plan" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">🗑</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <a class="btn btn-success" href="index.php"><?= esc(t('plany_novy')) ?> →</a>

    <?php else: ?>
        <!-- ==================== DETAIL PLÁNU ==================== -->
        <?php
        $planRadek   = $planDetail['radek'];
        $planCile    = $planDetail['cile'];
        $planVysledek = $planDetail['vysledek'];
        $planJeTyden = (string) $planRadek['typ'] === 'tyden';
        ?>

        <div class="om-hero text-center mb-4">
            <h1 class="om-hero-nadpis fs-2 fw-bold mb-1">
                <?= esc($planJeTyden ? t('plany_typ_tyden') : t('plany_typ_den')) ?>
            </h1>
            <p class="text-muted mb-0">
                <?= esc(t('plany_zalozeno')) ?>:
                <?= esc(date('j. n. Y H:i', strtotime((string) $planRadek['vytvoreno']))) ?>
                · <?= esc(fmt((float) ($planCile['kalorie'] ?? 0), 0)) ?> kcal
                · B <?= esc(fmt((float) ($planCile['bilkoviny'] ?? 0), 0)) ?> g
                · S <?= esc(fmt((float) ($planCile['sacharidy'] ?? 0), 0)) ?> g
                · T <?= esc(fmt((float) ($planCile['tuky'] ?? 0), 0)) ?> g
            </p>
        </div>

        <?php if ($planVysledek === null): ?>
            <div class="alert alert-warning"><?= esc(t('plan_nenalezen')) ?></div>
        <?php else: ?>
            <?php if ($planJeTyden): ?>
                <?php
                /* -------- TÝDENNÍ plán z JSON -------- */
                $tydenData = $planVysledek['tyden'] ?? [];
                $dnyPlan   = $planVysledek['dny'] ?? [];
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card h-100 shadow-sm"><div class="card-body">
                            <div class="small text-muted"><?= esc(t('tyden_metrika_kalorie')) ?></div>
                            <div class="fs-5 fw-bold"><?= esc(fmt((float) ($tydenData['kalorie'] ?? 0), 0)) ?> kcal</div>
                        </div></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card h-100 shadow-sm"><div class="card-body">
                            <div class="small text-muted"><?= esc(t('tyden_metrika_cena')) ?></div>
                            <div class="fs-5 fw-bold"><?= esc(mena_zobraz((float) ($tydenData['cena'] ?? 0))) ?></div>
                        </div></div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 karta-tisk">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><?= esc(t('tyden_souhrn_den')) ?></th>
                                    <?php foreach (array_keys(PODILY_JIDEL) as $nazevJidla): ?>
                                        <th><?= esc(t('jidlo_' . $nazevJidla)) ?></th>
                                    <?php endforeach; ?>
                                    <th class="text-end"><?= esc(t('kcal_label')) ?></th>
                                    <th class="text-end"><?= esc(t('th_cena_celkem')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dnyPlan as $den => $vysledekDne):
                                    $denData = $vysledekDne['celkem'];
                                ?>
                                    <tr>
                                        <td class="fw-semibold"><?= esc(t('den_' . (int) $den)) ?></td>
                                        <?php foreach (array_keys(PODILY_JIDEL) as $nazevJidla): ?>
                                            <td class="small">
                                                <?php foreach ($vysledekDne['jidelnicek'][$nazevJidla]['jidla'] ?? [] as $jidlo): ?>
                                                    <div>
                                                        <?= esc($jidlo['nazev']) ?>
                                                        <span class="text-muted">· <?= esc(fmt((float) $jidlo['gramy'], 0)) ?> g</span>
                                                        <?php if (!empty($jidlo['sleva'])): ?>
                                                            <span class="badge bg-warning text-dark ms-1"><?= esc(t('sleva_kratky')) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-end"><?= esc(fmt((float) $denData['kalorie'], 0)) ?></td>
                                        <td class="text-end"><?= esc(mena_zobraz((float) $denData['cena'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="5" class="text-end"><?= esc(t('celkem_tyden')) ?></th>
                                    <th class="text-end"><?= esc(fmt((float) ($tydenData['kalorie'] ?? 0), 0)) ?></th>
                                    <th class="text-end"><?= esc(mena_zobraz((float) ($tydenData['cena'] ?? 0))) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <?php $nakupTyden = $planVysledek['nakup'] ?? []; ?>
                <h2 class="h4 mb-3"><?= esc(t('nakup_nadpis')) ?></h2>
                <?php
                $nakupPolozky = $nakupTyden;
                $nakupSoucetPopisek = t('celkem_tyden');
                require __DIR__ . '/nakup_tabulka.php';
                ?>

            <?php else: ?>
                <?php
                /* -------- DENNÍ plán z JSON -------- */
                $celkem = $planVysledek['celkem'];
                $jidelnicek = $planVysledek['jidelnicek'];
                $cileCile = $planCile;
                require __DIR__ . '/plan_den_tabulka.php';

                $nakupPolozky = sestav_nakupni_seznam($jidelnicek);
                $nakupSoucetPopisek = t('celkem_den');
                ?>
                <h2 class="h4 mb-3"><?= esc(t('nakup_nadpis')) ?></h2>
                <?php require __DIR__ . '/nakup_tabulka.php'; ?>

            <?php endif; ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-5 no-tisk">
            <a class="btn btn-outline-secondary" href="plany.php"><?= esc(t('plany_zpet')) ?></a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <?= esc(t('tlacitko_tisk')) ?>
            </button>
        </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="motiv.js?v=<?= (int) filemtime(__DIR__ . '/motiv.js') ?>"></script>
<?php $_UI_KUS = 'modal'; require __DIR__ . '/ui_nastaveni.php'; ?>
</body>
</html>
