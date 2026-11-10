<?php
declare(strict_types=1);

/**
 * slevy.php – správa slev pro administrátory
 * ---------------------------------------------------------------
 * - seznam aktuálních slev (LEFT JOIN, platnost_do >= CURDATE())
 * - tlačítko „Načíst ukázkové slevy" – demo dataset bez nutnosti sítě
 *   (dle zadání: pro spolehlivou prezentaci na obhajobě)
 * - tlačítko „Spustit stahování ručně" – spustí tentýž kód, který
 *   v produkci oběhá CRON (update_slevy.php) – vidíte výsledek ihned
 * - návod na nastavení CRON (webový i systémový)
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

    if ($akce === 'ukazkove') {
        // DEMO podle zadání: bez sítě, garantovaný výsledek
        flash(nacti_ukazkove_slevy(getPDO()));
    } elseif ($akce === 'stahnout') {
        // ruční spuštění téhož kódu, který běží přes CRON
        flash(spustit_stahovani_slev(getPDO()));
    }

    header('Location: slevy.php');
    exit;
}

/* ==================== VÝPIS AKTUÁLNÍCH SLEV ==================== */
$pdo = getPDO();
$aktualniSlevy = $pdo->query(
    "SELECT p.nazev, a.obchod, a.akcni_cena, a.platnost_do, p.bezna_cena
     FROM akce_obchody AS a
     JOIN potraviny AS p ON p.id = a.id_potraviny
     WHERE a.platnost_do >= CURDATE()
     ORDER BY a.obchod, p.nazev"
)->fetchAll();

$casAZ = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="cs">
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

<nav class="navbar navbar-expand-lg navbar-dark bg-success no-tisk">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🥗 OptiMeal</a>
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="index.php"><?= esc(t('nav_generator')) ?></a></li>
            <li class="nav-item"><a class="nav-link" href="demo_dotaz.php"><?= esc(t('nav_slevy_prehled')) ?></a></li>
            <li class="nav-item"><a class="nav-link" href="katalog.php"><?= esc(t('nav_katalog')) ?></a></li>
            <li class="nav-item"><a class="nav-link" href="potraviny.php"><?= esc(t('nav_potraviny')) ?></a></li>
            <li class="nav-item"><a class="nav-link active" href="slevy.php"><?= esc(t('nav_slevy')) ?></a></li>
        </ul>
        <ul class="navbar-nav align-items-lg-center">
            <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('cs')) ?>" title="Čeština" lang="cs">🇨🇿</a></li>
            <li class="nav-item"><a class="nav-link px-2" href="<?= esc(jazyk_odkaz('en')) ?>" title="English" lang="en">🇬🇧</a></li>
            <?php $_UI_KUS = 'tlacitko'; require __DIR__ . '/ui_nastaveni.php'; ?>
            <li class="nav-item"><span class="navbar-text px-2">👤 <?= esc(prihlaseny_uzivatel()['jmeno'] ?? '') ?></span></li>
            <li class="nav-item"><a class="nav-link" href="odhlaseni.php"><?= esc(t('nav_odhlasit')) ?></a></li>
        </ul>
    </div>
</nav>

<main class="container my-4">
    <?= zobraz_flash() ?>

    <h1 class="h4 mb-1">🏷️ Automatizace slev</h1>
    <p class="text-muted">
        Slevy se automaticky stahují z digitálních letáků skriptem
        <code>update_slevy.php</code> (cURL + DOMDocument, spouštěný CRONem),
        rozparsují se a párují na katalog potravin.
    </p>

    <!-- ==================== AKTIVACE ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">🧪 Naplnit ukázkovými slevami</h2>
                    <p class="small text-muted mb-2">
                        Vloží pevný demo dataset (10 slev, platnost 30 dní) –
                        doporučeno před obhajobou, garantovaný stav bez sítě.
                    </p>
                    <form method="post" action="slevy.php">
                        <?= csrf_pole() ?>
                        <input type="hidden" name="akce" value="ukazkove">
                        <button type="submit" class="btn btn-success">Načíst ukázkové slevy</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">🕷️ Spustit stahování ručně</h2>
                    <p class="small text-muted mb-2">
                        Zavolá tentýž kód, který v produkci běží z CRONu
                        (cURL + DOMDocument + párování). U nás teď ukázkový letak
                        <code>letak_ukazka.html</code>.
                    </p>
                    <form method="post" action="slevy.php">
                        <?= csrf_pole() ?>
                        <input type="hidden" name="akce" value="stahnout">
                        <button type="submit" class="btn btn-outline-success">Spustit stahování</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== NASTAVENÍ CRON ==================== -->
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-white"><strong>⚙️ Automatizace – každý středu v 03:00</strong></div>
        <div class="card-body small">
            <ol class="mb-0">
                <li><strong>Systémový CRON</strong> na hostingu:
                    <code>0 3 * * 3 php <?= esc(__DIR__) ?>/update_slevy.php</code></li>
                <li><strong>Webcron zdarma (cron-job.org)</strong> bez systémového CRONu:
                    <code>https://optimeal.site.je/update_slevy.php?token=slevy_cron_2026</code>
                    &ndash; Nastavte: „jakýkoli den ve středu", 03:00.</li>
                <li>Kontrola: soubor <code>aktualizace_slev.log</code> obsahuje historii běhů.</li>
            </ol>
        </div>
    </div>

    <!-- ==================== TABULKA SLEV ==================== -->
    <h2 class="h4 mb-2">Aktuální slevy (<?= count($aktualniSlevy) ?>)</h2>
    <div class="card shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Potravina</th>
                        <th>Obchod</th>
                        <th class="text-end">Akční cena / 100 g</th>
                        <th class="text-end">Běžná cena</th>
                        <th class="text-end">Úspora</th>
                        <th class="text-end">Platnost do</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $celkemUspor = 0.0; ?>
                    <?php foreach ($aktualniSlevy as $s): ?>
                        <?php $uspora = max(0.0, (float) $s['bezna_cena'] - (float) $s['akcni_cena']); $celkemUspor += $uspora; ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($s['nazev']) ?></td>
                        <td><span class="badge bg-warning text-dark"><?= esc($s['obchod']) ?></span></td>
                        <td class="text-end"><?= esc(fmt((float) $s['akcni_cena'], 2)) ?> Kč</td>
                        <td class="text-end text-muted"><?= esc(fmt((float) $s['bezna_cena'], 2)) ?> Kč</td>
                        <td class="text-end text-success fw-semibold"><?= esc(fmt($uspora, 2)) ?> Kč</td>
                        <td class="text-end"><?= esc((string) $s['platnost_do']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($aktualniSlevy === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Žádné aktuální slevy – naplňte je tlačítkem výše.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end">Celková úspora za 100 g dohromady:</th>
                        <th class="text-end"><?= esc(fmt($celkemUspor, 2)) ?> Kč</th>
                        <th></th>
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
