<?php
declare(strict_types=1);

/**
 * slevy.php – správa slev pro administrátory
 * ---------------------------------------------------------------
 * - seznam aktuálních slev (LEFT JOIN, platnost_do >= CURDATE())
 * - RUČNÍ ZÁPIS SLEVY – admin má hned vidět slevu v generátoru
 *   (formulář počítá s aktivní měnou – vstup převedeme na CZK)
 * - MAZÁNÍ SLEV – každý řádek tabulky má tlačítko Smazat
 * - tlačítko „Načíst ukázkové slevy" – demo dataset bez nutnosti sítě
 * - tlačítko „Spustit stahování ručně" – tentýž kód, který oběhá CRON
 */

require_once __DIR__ . '/spolecne.php';
require_once __DIR__ . '/slevy_funkce.php';

vyzaduj_prihlaseni();

if (!je_admin()) {
    flash('Správa slev je určena jen administrátorům.', 'danger');
    header('Location: index.php');
    exit;
}

/* ==================== ZPRACOVÁNÍ TLAČÍTEK (POST) ==================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_zkontroluj()) {
        flash('Vypršela platnost formuláře, zkuste operaci opakovat.', 'warning');
        header('Location: slevy.php');
        exit;
    }

    $akce = $_POST['akce'] ?? '';
    $pdo_ = getPDO();

    if ($akce === 'ukazkove') {
        // DEMO podle zadání: bez sítě, garantovaný výsledek
        flash(nacti_ukazkove_slevy($pdo_));
    } elseif ($akce === 'stahnout') {
        // ruční spuštění téhož kódu, který běží přes CRON
        flash(spustit_stahovani_slev($pdo_));
    } elseif ($akce === 'pridat_slevu') {
        // ---------- RUČNÍ ZÁPIS SLEVY ----------
        $idPotraviny = ctype_digit((string) ($_POST['id_potraviny'] ?? '')) ? (int) $_POST['id_potraviny'] : 0;
        $obchod      = trim((string) ($_POST['obchod'] ?? ''));
        // akční cena přijde v AKTIVNÍ MĚNĚ – přepočteme na CZK (základ DB)
        $akcniCena   = mena_na_czk((float) ($_POST['akcni_cena'] ?? 0));
        $platnostDo  = trim((string) ($_POST['platnost_do'] ?? ''));

        $ok = $idPotraviny > 0
            && $obchod !== '' && mb_strlen($obchod) <= 60
            && $akcniCena > 0 && $akcniCena <= 100000
            && (bool) preg_match('~^\d{4}-\d{2}-\d{2}$~', $platnostDo)
            && $platnostDo >= date('Y-m-d');

        if (!$ok) {
            flash(t('slevy_chyba_dat'), 'danger');
        } else {
            try {
                // potravina musí existovat (pojistka proti cizímu ID)
                $kontrola = $pdo_->prepare("SELECT COUNT(*) FROM potraviny WHERE id = :id");
                $kontrola->execute(['id' => $idPotraviny]);
                if ((int) $kontrola->fetchColumn() === 0) {
                    flash(t('slevy_chyba_dat'), 'danger');
                } else {
                    uloz_slevu($pdo_, $idPotraviny, $obchod, $akcniCena, $platnostDo);
                    flash(t('slevy_pridano'));
                }
            } catch (PDOException $e) {
                error_log('Chyba při ručním zápisu slevy: ' . $e->getMessage());
                flash(t('slevy_chyba_dat'), 'danger');
            }
        }
    } elseif ($akce === 'smazat_slevu') {
        // ---------- MAZÁNÍ SLEVY ----------
        $idPotraviny = ctype_digit((string) ($_POST['id_potraviny'] ?? '')) ? (int) $_POST['id_potraviny'] : 0;
        $obchod      = trim((string) ($_POST['obchod'] ?? ''));

        if ($idPotraviny > 0 && $obchod !== '') {
            $mazani = $pdo_->prepare(
                "DELETE FROM akce_obchody WHERE id_potraviny = :id AND obchod = :obchod"
            );
            $mazani->execute(['id' => $idPotraviny, 'obchod' => $obchod]);
            flash($mazani->rowCount() > 0 ? t('slevy_smazano') : t('slevy_chyba_dat'), $mazani->rowCount() > 0 ? 'success' : 'danger');
        } else {
            flash(t('slevy_chyba_dat'), 'danger');
        }
    }

    header('Location: slevy.php');
    exit;
}

/* ==================== VÝPIS AKTUÁLNÍCH SLEV ==================== */
$pdo = getPDO();
$aktualniSlevy = $pdo->query(
    "SELECT a.id_potraviny, a.obchod, a.akcni_cena, a.platnost_do, p.nazev, p.bezna_cena
     FROM akce_obchody AS a
     JOIN potraviny AS p ON p.id = a.id_potraviny
     WHERE a.platnost_do >= CURDATE()
     ORDER BY a.obchod, p.nazev"
)->fetchAll();

$seznamPotravin = $pdo->query("SELECT id, nazev FROM potraviny ORDER BY nazev")->fetchAll();

$casAZ = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="<?= esc(aktualni_jazyk()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('nav_slevy')) ?> – OptiMeal</title>
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
                <li class="nav-item"><a class="nav-link" href="katalog.php"><?= esc(t('nav_katalog')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="potraviny.php"><?= esc(t('nav_potraviny')) ?></a></li>
                <li class="nav-item"><a class="nav-link active" href="slevy.php"><?= esc(t('nav_slevy')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="profil.php"><?= esc(t('nav_profil')) ?></a></li>
            </ul>
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('cs')) ?>" title="Čeština" lang="cs">🇨🇿</a></li>
                <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('en')) ?>" title="English" lang="en">🇬🇧</a></li>
                <?php $_UI_KUS = 'tlacitko'; require __DIR__ . '/ui_nastaveni.php'; ?>
                <li class="nav-item"><span class="navbar-text px-2">👤 <?= esc(prihlaseny_uzivatel()['jmeno'] ?? '') ?></span></li>
                <li class="nav-item"><a class="nav-link" href="odhlaseni.php"><?= esc(t('nav_odhlasit')) ?></a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?= zobraz_flash() ?>

    <h1 class="h4 mb-1">🏷️ <?= esc(t('nav_slevy')) ?></h1>
    <p class="text-muted">
        <?= esc(aktualni_jazyk() === 'cs'
            ? 'Slevy se automaticky stahují z digitálních letáků skriptem update_slevy.php (GitHub Actions CRON), rozparsují se a párují na katalog potravin.'
            : 'Discounts are automatically pulled from digital leaflets by update_slevy.php (GitHub Actions CRON), parsed and matched against the food catalog.') ?>
    </p>

    <!-- ==================== RUČNÍ ZÁPIS SLEVY ==================== -->
    <div class="card shadow-sm mb-4 no-tisk">
        <div class="card-header bg-white"><strong><?= esc(t('slevy_pridat_nazev')) ?></strong></div>
        <div class="card-body">
            <form method="post" action="slevy.php" class="row g-2 align-items-end">
                <?= csrf_pole() ?>
                <input type="hidden" name="akce" value="pridat_slevu">
                <div class="col-12 col-md-3">
                    <label class="form-label small" for="s_id_potraviny"><?= esc(t('slevy_form_potravina')) ?></label>
                    <select id="s_id_potraviny" name="id_potraviny" class="form-select" required>
                        <?php foreach ($seznamPotravin as $potravina): ?>
                            <option value="<?= (int) $potravina['id'] ?>"><?= esc($potravina['nazev']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small" for="s_obchod"><?= esc(t('slevy_form_obchod')) ?></label>
                    <input type="text" id="s_obchod" name="obchod" class="form-control" maxlength="60" required
                           placeholder="Billa / Tesco / …">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small" for="s_cena"><?= esc(t('slevy_form_akcni_cena', ['%mena%' => mena_znacka()])) ?></label>
                    <input type="number" id="s_cena" name="akcni_cena" class="form-control" step="0.01" min="0.01"
                           max="10000" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small" for="s_platnost"><?= esc(t('slevy_form_platnost_do')) ?></label>
                    <input type="date" id="s_platnost" name="platnost_do" class="form-control" required
                           value="<?= esc(date('Y-m-d', strtotime('+14 days'))) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <button type="submit" class="btn btn-success w-100"><?= esc(t('slevy_form_vlozit')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== AKTIVACE ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">🧪 <?= esc(aktualni_jazyk() === 'cs' ? 'Naplnit ukázkovými slevami' : 'Load sample discounts') ?></h2>
                    <p class="small text-muted mb-2">
                        <?= esc(aktualni_jazyk() === 'cs'
                            ? 'Vloží pevný demo dataset (10 slev, platnost 30 dní) – doporučeno před obhajobou, garantovaný stav bez sítě.'
                            : 'Inserts a fixed demo dataset (10 discounts, valid 30 days) – recommended before your defense, guaranteed state without network.') ?>
                    </p>
                    <form method="post" action="slevy.php">
                        <?= csrf_pole() ?>
                        <input type="hidden" name="akce" value="ukazkove">
                        <button type="submit" class="btn btn-success"><?= esc(aktualni_jazyk() === 'cs' ? 'Načíst ukázkové slevy' : 'Load sample discounts') ?></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">🕷️ <?= esc(aktualni_jazyk() === 'cs' ? 'Spustit stahování ručně' : 'Run scraping manually') ?></h2>
                    <p class="small text-muted mb-2">
                        <?= esc(aktualni_jazyk() === 'cs'
                            ? 'Zavolá tentýž kód, který v produkci běží z CRONu (cURL + DOMDocument + párování).'
                            : 'Calls the same code that runs from CRON in production (cURL + DOMDocument + matching).') ?>
                    </p>
                    <form method="post" action="slevy.php">
                        <?= csrf_pole() ?>
                        <input type="hidden" name="akce" value="stahnout">
                        <button type="submit" class="btn btn-outline-success"><?= esc(aktualni_jazyk() === 'cs' ? 'Spustit stahování' : 'Run scraping') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TABULKA SLEV ==================== -->
    <h2 class="h4 mb-2">
        <?= esc(aktualni_jazyk() === 'cs' ? 'Aktuální slevy' : 'Current discounts') ?> (<?= esc(fmt((float) count($aktualniSlevy), 0)) ?>)
    </h2>
    <div class="card shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= esc(t('th_potravina')) ?></th>
                        <th><?= esc(t('th_obchod')) ?></th>
                        <th class="text-end"><?= esc(aktualni_jazyk() === 'cs' ? 'Akční cena / 100 g' : 'Sale price / 100 g') ?></th>
                        <th class="text-end"><?= esc(aktualni_jazyk() === 'cs' ? 'Běžná cena' : 'Regular price') ?></th>
                        <th class="text-end"><?= esc(aktualni_jazyk() === 'cs' ? 'Úspora' : 'Saving') ?></th>
                        <th class="text-end"><?= esc(t('slevy_form_platnost_do')) ?></th>
                        <th class="no-tisk"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $celkemUspor = 0.0; ?>
                    <?php foreach ($aktualniSlevy as $s): ?>
                        <?php $uspora = max(0.0, (float) $s['bezna_cena'] - (float) $s['akcni_cena']); $celkemUspor += $uspora; ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($s['nazev']) ?></td>
                        <td><span class="badge bg-warning text-dark"><?= esc($s['obchod']) ?></span></td>
                        <td class="text-end"><?= esc(mena_zobraz((float) $s['akcni_cena'])) ?></td>
                        <td class="text-end text-muted"><?= esc(mena_zobraz((float) $s['bezna_cena'])) ?></td>
                        <td class="text-end text-success fw-semibold"><?= esc(mena_zobraz($uspora)) ?></td>
                        <td class="text-end"><?= esc((string) $s['platnost_do']) ?></td>
                        <td class="no-tisk text-end">
                            <!-- mazání: id_potraviny + obchod = unikátní klíč slevy -->
                            <form method="post" action="slevy.php" onsubmit="return confirm('<?= esc(aktualni_jazyk() === 'cs' ? 'Opravdu smazat tuto slevu?' : 'Really delete this discount?') ?>');">
                                <?= csrf_pole() ?>
                                <input type="hidden" name="akce" value="smazat_slevu">
                                <input type="hidden" name="id_potraviny" value="<?= (int) $s['id_potraviny'] ?>">
                                <input type="hidden" name="obchod" value="<?= esc($s['obchod']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">🗑️ <?= esc(t('slevy_smazat')) ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($aktualniSlevy === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        <?= esc(aktualni_jazyk() === 'cs' ? 'Žádné aktuální slevy – napište je ručně nebo načtěte ukázku.' : 'No current discounts – enter one manually or load the sample.') ?>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end"><?= esc(aktualni_jazyk() === 'cs' ? 'Celková úspora za 100 g dohromady:' : 'Total saving per 100 g combined:') ?></th>
                        <th class="text-end"><?= esc(mena_zobraz($celkemUspor)) ?></th>
                        <th></th>
                        <th class="no-tisk"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="motiv.js?v=<?= (int) filemtime(__DIR__ . '/motiv.js') ?>"></script>
<?php $_UI_KUS = 'modal'; require __DIR__ . '/ui_nastaveni.php'; ?>
</body>
</html>
