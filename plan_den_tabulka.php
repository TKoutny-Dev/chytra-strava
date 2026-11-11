<?php
/**
 * plan_den_tabulka.php – denní jídelníček (metriky + 4 jídla)
 * ---------------------------------------------------------------
 * Očekává proměnné:
 *   $jidelnicek – ['Snídaně' => [...], ...] ze generátoru/JSON
 *   $celkem     – denní souhrn (kalorie, makra, cena)
 *   $cileCile   – cíle uživatele ['kalorie', 'bilkoviny', ...]
 *
 * Používá index.php i plany.php (panelTik() je v generator_funkce.php).
 */
?>
<!-- ====================== SOUHRN CÍLŮ ====================== -->
<h2 class="h4 mb-3"><?= esc(t('skutecnost_cile')) ?></h2>
<div class="row g-3 mb-5">
    <?php
    $metry = [
        'metrika_kalorie'   => [(float) $celkem['kalorie'],   (float) $cileCile['kalorie']],
        'metrika_bilkoviny' => [(float) $celkem['bilkoviny'], (float) $cileCile['bilkoviny']],
        'metrika_sacharidy' => [(float) $celkem['sacharidy'], (float) $cileCile['sacharidy']],
        'metrika_tuky'      => [(float) $celkem['tuky'],      (float) $cileCile['tuky']],
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
    <?php foreach ($jidelnicek as $nazevJidla => $jidloDenne): ?>
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
                        <?php if (!empty($jidlo['sleva'])): ?>
                            <span class="badge bg-warning text-dark"><?= esc(t('sleva_badge')) ?> <?= esc($jidlo['obchod']) ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer bg-white small">
                <?= esc(t('denni_cena_jidla')) ?> <strong><?= esc(mena_zobraz((float) $jidloDenne['cena'])) ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
