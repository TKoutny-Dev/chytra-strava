<?php
declare(strict_types=1);

/**
 * prihlaseni.php – přihlášení uživatele
 * Heslo porovnáváme pomocí password_verify() proti HASHI v databázi.
 * Po úspěchu děláme session_regenerate_id() – ochrana proti
 * „session fixation" (úpěsné přihlášení = nový ID session).
 */

require_once __DIR__ . '/spolecne.php';

// již přihlášený uživatel nemá co dělat na přihlášení
if (prihlaseny_uzivatel() !== null) {
    header('Location: index.php');
    exit;
}

$chyba = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_zkontroluj()) {
        $chyba = t('chyba_csrf');
    } elseif (($_SESSION['prihlas_zamk_do'] ?? 0) > time()) {
        // OCHRANA PROTI HRUBÉ SÍLE: po 5 neúspěšných pokusech je
        // přihlášení na 15 minut uzamčeno (zámek žije v session)
        $zbyvaMinut = max(1, (int) ceil(($_SESSION['prihlas_zamk_do'] - time()) / 60));
        $chyba = t('chyba_zamknuto', ['%minuty%' => (string) $zbyvaMinut]);
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $heslo = (string) ($_POST['heslo'] ?? '');

        $dotaz = getPDO()->prepare(
            "SELECT id, jmeno, role, hash_hesla
             FROM uzivatele
             WHERE email = :email
             LIMIT 1"
        );
        $dotaz->execute(['email' => $email]);
        $uzivatel = $dotaz->fetch();

        if ($uzivatel === false || $uzivatel['hash_hesla'] === null
            || !password_verify($heslo, $uzivatel['hash_hesla'])) {
            // Říkáme JEN obecnou chybu – neodhalíme, že e-mail existuje (prevence profilování)
            $pokusy = ($_SESSION['prihlas_pokusy'] ?? 0) + 1;
            if ($pokusy >= 5) {
                // 5. špatný pokus -> 15minutový zámek
                $_SESSION['prihlas_zamk_do'] = time() + 15 * 60;
                $_SESSION['prihlas_pokusy'] = 0;
            } else {
                $_SESSION['prihlas_pokusy'] = $pokusy;
                // drobné náhodné zpomalení = drahší útok hrubou silou
                usleep(random_int(200000, 500000));
            }
            $chyba = t('chyba_prihlaseni');
        } else {
            // úspěch – počítadla pokusů vynulujeme
            unset($_SESSION['prihlas_pokusy'], $_SESSION['prihlas_zamk_do']);
            session_regenerate_id(true);
            $_SESSION['uzivatel'] = [
                'id'    => (int) $uzivatel['id'],
                'jmeno' => $uzivatel['jmeno'],
                'role'  => $uzivatel['role'],
            ];
            flash(t('vitej_zpet', ['%jmeno%' => $uzivatel['jmeno']]));

            // vracíme tam, kam chtěl původně (viz vyzaduj_prihlaseni)
            $cil = $_SESSION['cil_presmerovani'] ?? 'index.php';
            unset($_SESSION['cil_presmerovani']);
            header('Location: ' . $cil);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= esc(aktualni_jazyk()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('prihl_title')) ?></title>
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
<body style="min-height: 100vh;">
<main class="container" style="max-width: 430px;">
    <div class="card shadow-sm mt-5">
        <div class="card-body p-4">
            <h1 class="h4 text-center mb-3"><?= esc(t('prihl_h1')) ?></h1>
            <?= zobraz_flash() ?>
            <?php if ($chyba !== null): ?>
                <div class="alert alert-danger"><?= esc($chyba) ?></div>
            <?php endif; ?>

            <form method="post" action="prihlaseni.php">
                <?= csrf_pole() ?>
                <div class="mb-3">
                    <label class="form-label" for="email"><?= esc(t('email_label')) ?></label>
                    <input type="email" id="email" name="email" class="form-control"
                           required autofocus value="<?= esc($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="heslo"><?= esc(t('heslo_label')) ?></label>
                    <input type="password" id="heslo" name="heslo" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100"><?= esc(t('tlacitko_prihlasit')) ?></button>
            </form>

            <hr>
            <p class="mb-1 text-center">
                <?= esc(t('nemate_ucet')) ?> <a href="registrace.php"><?= esc(t('zaregistrujte_se')) ?></a> | <a href="index.php"><?= esc(t('na_generator')) ?></a>
            </p>
        </div>
    </div>
</main>
<!-- plovoucí přepínač nastavení (stránka nemá navigaci) -->
<div class="position-fixed top-0 end-0 m-3 no-tisk">
    <?php $_UI_KUS = 'tlacitko'; require __DIR__ . '/ui_nastaveni.php'; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="motiv.js?v=<?= (int) filemtime(__DIR__ . '/motiv.js') ?>"></script>
<?php $_UI_KUS = 'modal'; require __DIR__ . '/ui_nastaveni.php'; ?>
</body>
</html>
