<?php
declare(strict_types=1);

/**
 * profil.php – můj profil: tělesné údaje, výpočet cílů, změna hesla
 * ---------------------------------------------------------------
 * 1) TĚLO A CÍLE: uživatel zadá váhu/výšku/věk/pohlaví/aktivitu/cíl,
 *    skript spočítá denní příjem metodou Mifflin-St Jeor
 *    (BMR × faktor aktivity × korekce cíle) a rozdělí makronutrienty
 *    30/40/30 %. Výsledné cíle se uloží do tabulky uzivatele
 *    – generátor je pak nabízí na jediný klik na hlavní stránce.
 *
 *    BMR (Mifflin-St Jeor, 1990):
 *      muž:    10·kg + 6,25·cm − 5·rok + 5
 *      žena:   10·kg + 6,25·cm − 5·rok − 161
 *    Faktory aktivity: 1,2 / 1,375 / 1,55 / 1,725
 *    Korekce: hubnutí ×0,85, udržení ×1,0, nárůst ×1,1
 *
 * 2) ZMĚNA HESLA: ověření dosavadního hesla (password_verify),
 *    nové heslo hasíme password_hash – žádná hesla v čitelné podobě.
 *
 * Přístup: jen přihlášený uživatel, každý mění JEN svůj řádek
 * (id vždy z session, nikdy z formuláře – nelze cizí účet).
 */

require_once __DIR__ . '/spolecne.php';
require_once __DIR__ . '/cile_funkce.php';   // spocitej_cile() – Mifflin-St Jeor

vyzaduj_prihlaseni();

$uzivatel = prihlaseny_uzivatel();
$idUzivatele = (int) $uzivatel['id'];
$pdo = getPDO();

/* ==================== ZPRACOVÁNÍ FORMULÁŘŮ (POST) ==================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_zkontroluj()) {
        flash(t('chyba_csrf'), 'warning');
        header('Location: profil.php');
        exit;
    }

    $akce = (string) ($_POST['akce'] ?? '');

    /* ---------- 1) uložení těla + přepočet cílů ---------- */
    if ($akce === 'ulozit_profil') {
        $vaha     = (float) ($_POST['vaha'] ?? 0);
        $vyska    = (int) ($_POST['vyska'] ?? 0);
        $vek      = (int) ($_POST['vek'] ?? 0);
        $pohlavi  = (string) ($_POST['pohlavi'] ?? 'm');
        $aktivita = (string) ($_POST['aktivita'] ?? 'mirna');
        $cilTelo  = (string) ($_POST['cil_telo'] ?? 'udrzet');

        $ok = $vaha >= 30.0 && $vaha <= 300.0
            && $vyska >= 100 && $vyska <= 250
            && $vek >= 10 && $vek <= 120
            && isset(AKTIVITA_FAKTORY[$aktivita])
            && isset(CILE_KOREKCE[$cilTelo])
            && ($pohlavi === 'm' || $pohlavi === 'z');

        if (!$ok) {
            flash(t('profil_chyba_dat'), 'danger');
        } else {
            $cile = spocitej_cile($vaha, $vyska, $vek, $pohlavi, $aktivita, $cilTelo);

            $uloz = $pdo->prepare(
                "UPDATE uzivatele
                 SET vaha = :vaha, vyska = :vyska, vek = :vek,
                     pohlavi = :pohlavi, aktivita = :aktivita, cil_telo = :cil_telo,
                     cilove_kalorie = :kcal, cil_bilkoviny = :b,
                     cil_sacharidy = :s, cil_tuky = :t
                 WHERE id = :id"   // id Z SESSION – nikdy nelze měnit cizí profil
            );
            $uloz->execute([
                'vaha' => $vaha, 'vyska' => $vyska, 'vek' => $vek,
                'pohlavi' => $pohlavi, 'aktivita' => $aktivita, 'cil_telo' => $cilTelo,
                'kcal' => $cile['kalorie'], 'b' => $cile['bilkoviny'],
                's' => $cile['sacharidy'], 't' => $cile['tuky'],
                'id' => $idUzivatele,
            ]);

            flash(t('profil_ulozeno', [
                '%kcal%' => (string) $cile['kalorie'],
                '%b%'    => (string) $cile['bilkoviny'],
                '%s%'    => (string) $cile['sacharidy'],
                '%t%'    => (string) $cile['tuky'],
            ]));
        }

        header('Location: profil.php');
        exit;
    }

    /* ---------- 2) změna hesla ---------- */
    if ($akce === 'zmena_hesla') {
        $stareHeslo = (string) ($_POST['heslo_stare'] ?? '');
        $noveHeslo  = (string) ($_POST['heslo_nove'] ?? '');
        $potvrzeni  = (string) ($_POST['heslo_potvrzeni'] ?? '');

        $dotaz = $pdo->prepare("SELECT hash_hesla FROM uzivatele WHERE id = :id");
        $dotaz->execute(['id' => $idUzivatele]);
        $hash = $dotaz->fetchColumn();

        if ($hash === false || $hash === null || !password_verify($stareHeslo, (string) $hash)) {
            flash(t('profil_heslo_spatne'), 'danger');
        } elseif (mb_strlen($noveHeslo) < 8) {
            flash(t('profil_heslo_kratke'), 'danger');
        } elseif ($noveHeslo !== $potvrzeni) {
            flash(t('profil_heslo_rozdel'), 'danger');
        } else {
            $zmena = $pdo->prepare("UPDATE uzivatele SET hash_hesla = :hash WHERE id = :id");
            $zmena->execute(['hash' => password_hash($noveHeslo, PASSWORD_DEFAULT), 'id' => $idUzivatele]);
            flash(t('profil_heslo_ok'));
        }

        header('Location: profil.php');
        exit;
    }

    header('Location: profil.php');
    exit;
}

/* ==================== NAČTENÍ STAVU UŽIVATELE ==================== */

$dotaz = $pdo->prepare(
    "SELECT jmeno, email, role, cilove_kalorie, cil_bilkoviny, cil_sacharidy, cil_tuky,
            vaha, vyska, vek, pohlavi, aktivita, cil_telo
     FROM uzivatele WHERE id = :id"
);
$dotaz->execute(['id' => $idUzivatele]);
$muj = $dotaz->fetch();

if ($muj === false) {
    flash(t('chyba_prihlaseni'), 'danger');
    header('Location: odhlaseni.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= esc(aktualni_jazyk()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('profil_title')) ?></title>
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
                <li class="nav-item"><a class="nav-link active" href="profil.php"><?= esc(t('nav_profil')) ?></a></li>
                <?php if (je_admin()): ?>
                    <li class="nav-item"><a class="nav-link" href="potraviny.php"><?= esc(t('nav_potraviny')) ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="slevy.php"><?= esc(t('nav_slevy')) ?></a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('cs')) ?>" title="Čeština" lang="cs">🇨🇿</a></li>
                <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('en')) ?>" title="English" lang="en">🇬🇧</a></li>
                <?php $_UI_KUS = 'tlacitko'; require __DIR__ . '/ui_nastaveni.php'; ?>
                <li class="nav-item"><span class="navbar-text px-2">👤 <?= esc($muj['jmeno']) ?></span></li>
                <li class="nav-item"><a class="nav-link" href="odhlaseni.php"><?= esc(t('nav_odhlasit')) ?></a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-4" style="max-width: 760px;">
    <?= zobraz_flash() ?>

    <div class="om-hero text-center mb-4">
        <h1 class="om-hero-nadpis fs-2 fw-bold mb-1"><?= esc(t('profil_h1')) ?></h1>
        <p class="text-muted mb-0"><?= esc($muj['email']) ?></p>
    </div>

    <!-- ==================== MOJE CÍLE (souhrn) ==================== -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><strong><?= esc(t('profil_moje_cile')) ?></strong></div>
        <div class="card-body">
            <div class="row text-center g-3">
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold"><?= esc(fmt((float) $muj['cilove_kalorie'], 0)) ?></div>
                    <div class="text-muted small"><?= esc(t('kcal_label')) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold"><?= esc(fmt((float) $muj['cil_bilkoviny'], 0)) ?> g</div>
                    <div class="text-muted small"><?= esc(t('bilkoviny_label')) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold"><?= esc(fmt((float) $muj['cil_sacharidy'], 0)) ?> g</div>
                    <div class="text-muted small"><?= esc(t('sacharidy_label')) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-bold"><?= esc(fmt((float) $muj['cil_tuky'], 0)) ?> g</div>
                    <div class="text-muted small"><?= esc(t('tuky_label')) ?></div>
                </div>
            </div>
            <div class="text-center mt-3">
                <a class="btn btn-success" href="index.php?uzivatel=<?= $idUzivatele ?>">
                    <?= esc(t('profil_pouzit')) ?> →
                </a>
            </div>
        </div>
    </div>

    <!-- ==================== TĚLO A VÝPOČET CÍLŮ ==================== -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><strong><?= esc(t('profil_telo_nadpis')) ?></strong></div>
        <div class="card-body">
            <form method="post" action="profil.php">
                <?= csrf_pole() ?>
                <input type="hidden" name="akce" value="ulozit_profil">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="p_vaha"><?= esc(t('profil_vaha')) ?> (kg)</label>
                        <input type="number" id="p_vaha" name="vaha" class="form-control" min="30" max="300"
                               step="0.1" required value="<?= esc($muj['vaha'] !== null ? (string) $muj['vaha'] : '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="p_vyska"><?= esc(t('profil_vyska')) ?> (cm)</label>
                        <input type="number" id="p_vyska" name="vyska" class="form-control" min="100" max="250"
                               required value="<?= esc($muj['vyska'] !== null ? (string) $muj['vyska'] : '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="p_vek"><?= esc(t('profil_vek')) ?></label>
                        <input type="number" id="p_vek" name="vek" class="form-control" min="10" max="120"
                               required value="<?= esc($muj['vek'] !== null ? (string) $muj['vek'] : '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="p_pohlavi"><?= esc(t('profil_pohlavi')) ?></label>
                        <select id="p_pohlavi" name="pohlavi" class="form-select">
                            <option value="m" <?= $muj['pohlavi'] === 'm' ? 'selected' : '' ?>><?= esc(t('profil_pohlavi_m')) ?></option>
                            <option value="z" <?= $muj['pohlavi'] === 'z' ? 'selected' : '' ?>><?= esc(t('profil_pohlavi_z')) ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="p_aktivita"><?= esc(t('profil_aktivita')) ?></label>
                        <select id="p_aktivita" name="aktivita" class="form-select">
                            <option value="sedava"  <?= $muj['aktivita'] === 'sedava' ? 'selected' : '' ?>><?= esc(t('aktivita_sedava')) ?></option>
                            <option value="mirna"   <?= $muj['aktivita'] === 'mirna' ? 'selected' : '' ?>><?= esc(t('aktivita_mirna')) ?></option>
                            <option value="aktivni" <?= $muj['aktivita'] === 'aktivni' ? 'selected' : '' ?>><?= esc(t('aktivita_aktivni')) ?></option>
                            <option value="velmi"   <?= $muj['aktivita'] === 'velmi' ? 'selected' : '' ?>><?= esc(t('aktivita_velmi')) ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="p_cil"><?= esc(t('profil_cil')) ?></label>
                        <select id="p_cil" name="cil_telo" class="form-select">
                            <option value="zhubnout"  <?= $muj['cil_telo'] === 'zhubnout' ? 'selected' : '' ?>><?= esc(t('cil_zhubnout')) ?></option>
                            <option value="udrzet"    <?= $muj['cil_telo'] === 'udrzet' ? 'selected' : '' ?>><?= esc(t('cil_udrzet')) ?></option>
                            <option value="zhodnotit" <?= $muj['cil_telo'] === 'zhodnotit' ? 'selected' : '' ?>><?= esc(t('cil_zhodnotit')) ?></option>
                        </select>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-success"><?= esc(t('profil_vypocitat')) ?></button>
                </div>
                <p class="small text-muted mt-3 mb-0"><?= esc(t('profil_vysvetleni')) ?></p>
            </form>
        </div>
    </div>

    <!-- ==================== ZMĚNA HESLA ==================== -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><strong>🔒 <?= esc(t('profil_heslo_nadpis')) ?></strong></div>
        <div class="card-body">
            <form method="post" action="profil.php">
                <?= csrf_pole() ?>
                <input type="hidden" name="akce" value="zmena_hesla">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="h_stare"><?= esc(t('profil_heslo_stare')) ?></label>
                        <input type="password" id="h_stare" name="heslo_stare" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="h_nove"><?= esc(t('profil_heslo_nove')) ?></label>
                        <input type="password" id="h_nove" name="heslo_nove" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="h_potvrzeni"><?= esc(t('profil_heslo_potvrzeni')) ?></label>
                        <input type="password" id="h_potvrzeni" name="heslo_potvrzeni" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-outline-success"><?= esc(t('profil_heslo_zmenit')) ?></button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="motiv.js?v=<?= (int) filemtime(__DIR__ . '/motiv.js') ?>"></script>
<?php $_UI_KUS = 'modal'; require __DIR__ . '/ui_nastaveni.php'; ?>
</body>
</html>
