<?php
declare(strict_types=1);

/**
 * katalog.php – hledání potravin v otevřeném katalogu Open Food Facts
 * ---------------------------------------------------------------
 * Jak to funguje (a proč je to světově škálovatelné):
 *   - VYHLEDÁVÁNÍ běží přímo v prohlížeči uživatele (fetch -> OFF API).
 *     OFF API má otevřené CORS, takže nepotřebujeme žádný serverový
 *     proxy – řeší se i rychlost, zátěž hostingu i poloha uživatele.
 *   - Uložení do naší databáze jde přes POST s CSRF tokenem,
 *     validovaná data vkládáme PDO prepared statementem.
 *   - Nutriční hodnoty (kcal, B, S, T / 100 g) bereme z OFF; cenu si
 *     doplní uživatel z místního obchodu (OFF ceny neobsahuje).
 *
 * Přístup: jen přihlášení (katalog roste od komunitě – bez účtu spam).
 */

require_once __DIR__ . '/spolecne.php';

/* ==================== ULOŽENÍ VYBRANÉ POTRAVINY (POST) ==================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_zkontroluj()) {
        flash(t('chyba_csrf'), 'warning');
        header('Location: katalog.php');
        exit;
    }

    $nazev    = trim((string) ($_POST['nazev'] ?? ''));
    $bilkoviny = (float) ($_POST['bilkoviny'] ?? 0);
    $sacharidy = (float) ($_POST['sacharidy'] ?? 0);
    $tuky      = (float) ($_POST['tuky'] ?? 0);
    $kalorie   = (float) ($_POST['kalorie'] ?? 0);
    $beznaCena = mena_na_czk((float) ($_POST['bezna_cena'] ?? 0));

    // stejný validace rozsah jako u správy potravin (100 g se pak
    // do generátoru počítá korektně; kcal>900 = nesmysl)
    $ok = $nazev !== '' && mb_strlen($nazev) <= 100;
    $ok = $ok && $bilkoviny >= 0 && $bilkoviny <= 100;
    $ok = $ok && $sacharidy >= 0 && $sacharidy <= 100;
    $ok = $ok && $tuky >= 0 && $tuky <= 100;
    $ok = $ok && $kalorie >= 0 && $kalorie <= 900;
    $ok = $ok && $beznaCena > 0 && $beznaCena <= 100000;

    if (!$ok) {
        flash(t('katalog_chyba_dat'), 'danger');
    } else {
        $dotaz = getPDO()->prepare(
            "INSERT INTO potraviny (nazev, bilkoviny, sacharidy, tuky, kalorie, bezna_cena)
             VALUES (:nazev, :bilkoviny, :sacharidy, :tuky, :kalorie, :bezna_cena)"
        );
        try {
            $dotaz->execute([
                'nazev'     => $nazev,
                'bilkoviny' => $bilkoviny,
                'sacharidy' => $sacharidy,
                'tuky'      => $tuky,
                'kalorie'   => $kalorie,
                'bezna_cena' => $beznaCena,
            ]);
            flash(t('katalog_pridano', ['%jmeno%' => $nazev]));
        } catch (PDOException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                flash(t('katalog_duplicita', ['%jmeno%' => $nazev]), 'warning');
            } else {
                error_log('Chyba ukládání z OFF katalogu: ' . $e->getMessage());
                flash(t('chyba_ulozeni'), 'danger');
            }
        }
    }

    header('Location: katalog.php');
    exit;
}

/* ==================== STRÁNKA ==================== */
require_once __DIR__ . '/db.php';
$pdoP = getPDO();
$celkem = (int) $pdoP->query("SELECT COUNT(*) FROM potraviny")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="<?= esc(aktualni_jazyk()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('katalog_title')) ?></title>
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
                <li class="nav-item"><a class="nav-link active" href="katalog.php"><?= esc(t('nav_katalog')) ?></a></li>
                <?php if (je_admin()): ?>
                    <li class="nav-item"><a class="nav-link" href="potraviny.php"><?= esc(t('nav_potraviny')) ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="slevy.php"><?= esc(t('nav_slevy')) ?></a></li>
                <?php endif; ?>
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

    <div class="om-hero text-center mb-4">
        <h1 class="om-hero-nadpis fs-1 fw-bold mb-2"><?= esc(t('katalog_h1')) ?></h1>
        <p class="text-muted mx-auto" style="max-width: 640px;">
            <?= t('katalog_podtext') /* obsahuje <strong> – vědomě bez esc */ ?>
        </p>
    </div>

    <!-- ====================== HLEDÁCÍ PANEL ====================== -->
    <div class="card shadow-sm mb-4 no-tisk">
        <div class="card-body">
            <form id="om-katalog-forma" class="row g-2" autocomplete="off">
                <div class="col-12 col-md">
                    <label class="form-label" for="om-katalog-dotaz"><?= esc(t('katalog_hledat')) ?></label>
                    <input type="text" id="om-katalog-dotaz" class="form-control form-control-lg"
                           placeholder="<?= esc(t('katalog_placeholder')) ?>">
                </div>
                <div class="col-12 col-md-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-success btn-lg"><?= esc(t('katalog_tlacitko')) ?></button>
                </div>
            </form>
            <div id="om-katalog-stav" class="mt-3 text-muted small"><?= esc(t('katalog_nic')) ?></div>
        </div>
    </div>

    <!-- ====================== VÝSLEDKY ====================== -->
    <div id="om-katalog-vysledky" class="row g-3 mb-4"></div>

    <p class="small text-muted"><?= t('katalog_zdroj') /* obsahuje <a> – vědomě bez esc */ ?> ·
        <?= esc(fmt((float) $celkem, 0)) ?> <?= esc(aktualni_jazyk() === 'cs' ? 'potravin v katalogu' : 'foods in catalog') ?>.
    </p>
</main>

<!-- ====================== MODÁL: ULOŽENÍ POTRAVINY ====================== -->
<div class="modal fade" id="om-katalog-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="katalog.php">
                <?= csrf_pole() ?>
                <input type="hidden" name="akce" value="off_vlozit">
                <div class="modal-header">
                    <h5 class="modal-title"><?= esc(t('katalog_modal_nazev')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(t('nastaveni_zavrit')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="off_nazev"><?= esc(t('jmeno_label')) ?> / názný název</label>
                        <input type="text" id="off_nazev" name="nazev" class="form-control" required maxlength="100">
                    </div>
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="off_kalorie"><?= esc(t('kcal_label')) ?></label>
                            <input type="number" id="off_kalorie" name="kalorie" class="form-control" min="0" max="900" step="0.1" required value="0">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="off_bilkoviny"><?= esc(t('bilkoviny_label')) ?></label>
                            <input type="number" id="off_bilkoviny" name="bilkoviny" class="form-control" min="0" max="100" step="0.1" required value="0">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="off_sacharidy"><?= esc(t('sacharidy_label')) ?></label>
                            <input type="number" id="off_sacharidy" name="sacharidy" class="form-control" min="0" max="100" step="0.1" required value="0">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="off_tuky"><?= esc(t('tuky_label')) ?></label>
                            <input type="number" id="off_tuky" name="tuky" class="form-control" min="0" max="100" step="0.1" required value="0">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="off_cena"><?= esc(t('katalog_cena_label', ['%mena%' => mena_znacka()])) ?></label>
                        <input type="number" id="off_cena" name="bezna_cena" class="form-control" min="0.01" max="10000" step="0.01" required value="">
                        <div class="form-text"><?= esc(t('katalog_cena_hint')) ?></div>
                    </div>
                    <small class="text-muted d-none d-block mt-3" id="off_zdroj-www"></small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><?= esc(t('nastaveni_zavrit')) ?></button>
                    <button type="submit" class="btn btn-sm btn-success"><?= esc(t('katalog_vlozit')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="motiv.js?v=<?= (int) filemtime(__DIR__ . '/motiv.js') ?>"></script>
<?php $_UI_KUS = 'modal'; require __DIR__ . '/ui_nastaveni.php'; ?>
<script>
(() => {
    // ------- klient-side hledání v Open Food Facts (CORS je otevřený) -------
    const forma    = document.getElementById('om-katalog-forma');
    const dotaz    = document.getElementById('om-katalog-dotaz');
    const stav     = document.getElementById('om-katalog-stav');
    const vysledky = document.getElementById('om-katalog-vysledky');
    const modal    = new bootstrap.Modal(document.getElementById('om-katalog-modal'));

    const BEZ = '<?= esc(aktualni_jazyk()) === 'cs' ? 'product_name' : 'product_name_en' ?>';
    const TXT = {
        hledam:     '<?= esc(t('katalog_hledam')) ?>',
        zadne:      '<?= esc(t('katalog_zadne')) ?>',
        chyba_site: '<?= esc(t('katalog_chyba_site')) ?>',
        vbot:       '<?= esc(t('katalog_vlozit')) ?>',
        nezadno:    '<?= esc(t('katalog_nezadno')) ?>',
    };

    forma.addEventListener('submit', (e) => {
        e.preventDefault();
        const q = dotaz.value.trim();
        if (q === '') return;
        stav.textContent = TXT.hledam;
        vysledky.innerHTML = '';

        // OFF API v2 – fields jen ty, které opravdu potřebujeme (menší přenos)
        const url = 'https://world.openfoodfacts.org/cgi/search.pl'
            + '?search_terms=' + encodeURIComponent(q)
            + '&search_simple=1&action=process&json=1&page_size=16'
            + '&fields=product_name,product_name_en,brands,nutriments,image_front_small_url,quantity,categories_tags_fr,categories_tags';

        fetch(url).then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(data => {
            const vybrane = (data.products || []).filter(p => {
                const n = p.nutriments || {};
                const kcal = Number(n['energy-kcal_100g'] ?? 0);
                const b = Number(n.proteins_100g ?? 0);
                // bez obou údajů je produkt pro generátor k ničemu
                return (p.product_name || p.product_name_en) && (kcal > 0 || b > 0);
            });

            if (vybrane.length === 0) {
                stav.textContent = TXT.zadne;
                return;
            }
            stav.textContent = vybrane.length + ' ' + (vybrane.length === 1 ? 'result' : 'results');
            vybrane.forEach(p => vysledky.appendChild(kartaVysledku(p)));
        }).catch(() => {
            stav.textContent = TXT.chyba_site;
        });
    });

    /* karta jednoho výsledku + otevření modálu s předvyplněnými údaji */
    function kartaVysledku(p) {
        const n = p.nutriments || {};
        const kcal = Number(n['energy-kcal_100g'] ?? 0) || Math.round(Number(n.energy_100g ?? 0) / 4.184) || 0;
        const b    = Number(n.proteins_100g ?? 0);
        const s    = Number(n.carbohydrates_100g ?? 0);
        const t    = Number(n.fat_100g ?? 0);
        const jmeno = (p[BEZ] || p.product_name || p.product_name_en || '').trim().slice(0, 100);

        const sloupec = document.createElement('div');
        sloupec.className = 'col-6 col-md-4 col-lg-3';
        sloupec.innerHTML =
            '<div class="card h-100 karta-jidla">'
            + (p.image_front_small_url ? '<img class="card-img-top p-3" style="max-height:130px;object-fit:contain" alt="' + escHtml(jmeno) + '" src="' + escHtml(p.image_front_small_url) + '">' : '')
            + '<div class="card-body">'
            + '  <p class="mb-1 fw-semibold">' + escHtml(jmeno) + '</p>'
            + '  <small class="text-muted">' + escHtml(p.brands ? p.brands.slice(0, 40) : '') + '</small>'
            + '  <div class="small mt-2">'
            + '    ' + (kcal ? '🍽️ ' + kcal + ' kcal · ' : '')
            + '    ' + 'P ' + Math.round(b * 10) / 10 + ' · C ' + Math.round(s * 10) / 10 + ' · F ' + Math.round(t * 10) / 10 + ' g'
            + '  </div>'
            + '</div>'
            + '<div class="card-footer bg-transparent border-0 no-tisk">'
            + '  <button type="button" class="btn btn-sm btn-outline-success w-100" '
            + (jmeno && (kcal > 0 || b > 0)
                ? 'data-off="1">'
                : 'disabled>')
            + escHtml(TXT.vbot) + '</button>'
            + '</div>'
            + '</div>';

        if (!kcal && !b) {
            // uklázat nelze – nutno komunikovat uživateli
            stav.textContent = TXT.nezadno;
        }
        const tlacitko = sloupec.querySelector('button[data-off]');
        if (tlacitko) {
            tlacitko.addEventListener('click', () => {
                document.getElementById('off_nazev').value = jmeno;
                document.getElementById('off_kalorie').value = kcal;
                document.getElementById('off_bilkoviny').value = b;
                document.getElementById('off_sacharidy').value = s;
                document.getElementById('off_tuky').value = t;
                document.getElementById('off_cena').value = '';
                document.getElementById('off_zdroj-www').textContent = jmeno + ' — ' + (p.brands || '');
                modal.show();
            });
        }
        return sloupec;
    }

    function escHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c =>
            ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }
})();
</script>
</body>
</html>
