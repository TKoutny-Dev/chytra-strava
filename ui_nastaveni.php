<?php
declare(strict_types=1);

/**
 * ui_nastaveni.php – kus UI pro MODÁL NASTAVENÍ (téma)
 * ------------------------------------------------------------
 * Vlož se dvakrát:
 *   $_UI_KUS = 'tlacitko';  require 'ui_nastaveni.php';  // ozubené kolečko do navigace
 *   $_UI_KUS = 'modal';     require 'ui_nastaveni.php'; // modál před </body>
 * Logika přepínání: motiv.js (světlo / tma / systém).
 */

$_UI_KUS ??= 'tlacitko';
?>
<?php if ($_UI_KUS === 'tlacitko'): ?>
<!-- ✏️ tlačítko do navigace – otevře modál nastavení -->
<li class="nav-item">
    <button type="button" class="om-pilulka" data-bs-toggle="modal" data-bs-target="#om-modal-nastaveni"
            title="<?= esc(t('nastaveni_nazev')) ?>" aria-haspopup="dialog">
        ⚙️
    </button>
</li>
<?php elseif ($_UI_KUS === 'modal'): ?>
<!-- ====================== MODÁL: NASTAVENÍ ====================== -->
<div class="modal fade" id="om-modal-nastaveni" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= esc(t('nastaveni_nazev')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(t('nastaveni_zavrit')) ?>"></button>
            </div>
            <div class="modal-body">
                <h6 class="small text-uppercase text-muted mb-2"><?= esc(t('nastaveni_tema_nazev')) ?></h6>
                <div class="om-prepinac-tematu" role="group" aria-label="<?= esc(t('nastaveni_tema_nazev')) ?>">
                    <button type="button" class="om-moznost-tematu" data-motiv="light" aria-pressed="false">
                        <span class="ikona">☀️</span>
                        <span class="popisek"><?= esc(t('nastaveni_tema_svetlo')) ?></span>
                    </button>
                    <button type="button" class="om-moznost-tematu" data-motiv="dark" aria-pressed="false">
                        <span class="ikona">🌙</span>
                        <span class="popisek"><?= esc(t('nastaveni_tema_tma')) ?></span>
                    </button>
                    <button type="button" class="om-moznost-tematu" data-motiv="system" aria-pressed="false">
                        <span class="ikona">🖥️</span>
                        <span class="popisek"><?= esc(t('nastaveni_tema_system')) ?></span>
                        <span class="os-stav" data-os-stav></span>
                    </button>
                </div>
                <p class="small text-muted mb-0 mt-3"><?= esc(t('nastaveni_tema_poznamka')) ?></p>

                <hr class="my-3">
                <h6 class="small text-uppercase text-muted mb-2"><?= esc(t('mena_nazev')) ?></h6>
                <!-- přepínač měny – mění session přes ?mena=, ceny se přepočtou na všech stránkách -->
                <div class="om-prepinac-tematu" role="group" aria-label="<?= esc(t('mena_nazev')) ?>">
                    <a class="om-moznost-tematu text-decoration-none <?= mena_aktualni() === 'czk' ? 'aktivni' : '' ?>"
                       href="<?= esc(mena_odkaz('czk')) ?>" role="button">
                        <span class="ikona">🇨🇿</span>
                        <span class="popisek">CZK</span>
                    </a>
                    <a class="om-moznost-tematu text-decoration-none <?= mena_aktualni() === 'eur' ? 'aktivni' : '' ?>"
                       href="<?= esc(mena_odkaz('eur')) ?>" role="button">
                        <span class="ikona">🇪🇺</span>
                        <span class="popisek">EUR</span>
                    </a>
                    <a class="om-moznost-tematu text-decoration-none <?= mena_aktualni() === 'usd' ? 'aktivni' : '' ?>"
                       href="<?= esc(mena_odkaz('usd')) ?>" role="button">
                        <span class="ikona">🇺🇸</span>
                        <span class="popisek">USD</span>
                    </a>
                </div>
                <p class="small text-muted mb-0 mt-3"><?= esc(t('mena_poznamka')) ?></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                    <?= esc(t('nastaveni_zavrit')) ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
