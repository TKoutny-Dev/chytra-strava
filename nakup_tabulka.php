<?php
/**
 * nakup_tabulka.php – společná tabulka nákupního seznamu
 * ---------------------------------------------------------------
 * Očekává proměnné:
 *   $nakupPolozky        – pole položek ze sestav_nakupni_seznam()
 *   $nakupSoucetPopisek  – popisek součtu (výchozí „Celkem za den")
 *
 * Vykreslí kartu s tabulkou (potravina, množství, cena/100 g,
 * obchod, cena celkem + součet). Používá index.php i plany.php.
 */

$nakupSoucetPopisek = $nakupSoucetPopisek ?? t('celkem_den');
?>
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
                <?php $nakupCelkovaCena = 0.0; ?>
                <?php foreach ($nakupPolozky as $polozka): ?>
                    <?php $nakupCelkovaCena += (float) $polozka['cena_celkem']; ?>
                    <tr>
                        <td>
                            <?= esc($polozka['nazev']) ?>
                            <?php if (!empty($polozka['sleva'])): ?>
                                <span class="badge bg-warning text-dark ms-1"><?= esc(t('sleva_kratky')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= esc(fmt((float) $polozka['gramy'], 0)) ?> g</td>
                        <td class="text-end"><?= esc(mena_zobraz((float) $polozka['cena_za_100g'])) ?></td>
                        <td>
                            <?= $polozka['obchod'] !== null ? esc($polozka['obchod']) : '—' ?>
                        </td>
                        <td class="text-end"><?= esc(mena_zobraz((float) $polozka['cena_celkem'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end"><?= esc($nakupSoucetPopisek) ?></th>
                    <th class="text-end"><?= esc(mena_zobraz($nakupCelkovaCena)) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
