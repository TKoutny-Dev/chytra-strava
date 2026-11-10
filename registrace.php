<?php
declare(strict_types=1);

/**
 * registrace.php – vytvoření nového uživatelského účtu
 * Heslo ukládáme VŽDY jako hash (password_hash) – nikdy v čitelné podobě.
 * Kontrolujeme duplicitní e-mail i uživatelské jméno (UNIQUE v DB).
 */

require_once __DIR__ . '/spolecne.php';

$chyby = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_zkontroluj()) {
        $chyby[] = t('chyba_csrf');
    } else {
        $jmeno = trim((string) ($_POST['jmeno'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $heslo = (string) ($_POST['heslo'] ?? '');

        // hranatá validace vstupů (serverová, ne jen v prohlížeči)
        if (mb_strlen($jmeno) < 3 || mb_strlen($jmeno) > 100) {
            $chyby[] = t('chyba_jmeno');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $chyby[] = t('chyba_email');
        }
        if (mb_strlen($heslo) < 8) {
            $chyby[] = t('chyba_heslo');
        }

        $pdo = getPDO();

        // výchozí denní cíle – uživatel si je později může upravit
        $cile = ['kalorie' => 2400, 'bilkoviny' => 160, 'sacharidy' => 280, 'tuky' => 80];

        if ($chyby === []) {
            try {
                $dotaz = $pdo->prepare(
                    "INSERT INTO uzivatele
                        (jmeno, email, hash_hesla, role, cilove_kalorie, cil_bilkoviny, cil_sacharidy, cil_tuky)
                     VALUES
                        (:jmeno, :email, :hash, 'uzivatel', :kalorie, :bilkoviny, :sacharidy, :tuky)"
                );
                $dotaz->execute([
                    'jmeno'     => $jmeno,
                    'email'     => $email,
                    'hash'      => password_hash($heslo, PASSWORD_DEFAULT),
                    'kalorie'   => $cile['kalorie'],
                    'bilkoviny' => $cile['bilkoviny'],
                    'sacharidy' => $cile['sacharidy'],
                    'tuky'      => $cile['tuky'],
                ]);

                // rovnou přihlásíme (session_regenerate_id pro jistotu)
                session_regenerate_id(true);
                $_SESSION['uzivatel'] = [
                    'id'    => (int) $pdo->lastInsertId(),
                    'jmeno' => $jmeno,
                    'role'  => 'uzivatel',
                ];
                flash(t('registrace_ok', ['%jmeno%' => $jmeno]));
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                // unikátní klíč (1062) = duplicitní jméno nebo e-mail
                if ((int) $e->errorInfo[1] === 1062) {
                    $chyby[] = t('chyba_duplicita');
                } else {
                    error_log('Chyba registrace: ' . $e->getMessage());
                    $chyby[] = t('chyba_ulozeni');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= esc(aktualni_jazyk()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('registr_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="styl.css" rel="stylesheet">
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
<body style="min-height: 100vh;">
<main class="container" style="max-width: 480px;">
    <div class="card shadow-sm mt-5">
        <div class="card-body p-4">
            <h1 class="h4 text-center mb-3"><?= esc(t('registr_h1')) ?></h1>
            <?= zobraz_flash() ?>
            <?php if ($chyby !== []): ?>
                <div class="alert alert-danger mb-3">
                    <?php foreach ($chyby as $chyba): ?>
                        <?= esc($chyba) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="registrace.php">
                <?= csrf_pole() ?>
                <div class="mb-3">
                    <label class="form-label" for="jmeno"><?= esc(t('jmeno_label')) ?></label>
                    <input type="text" id="jmeno" name="jmeno" class="form-control" required
                           minlength="3" maxlength="100" value="<?= esc($_POST['jmeno'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email"><?= esc(t('email_label')) ?></label>
                    <input type="email" id="email" name="email" class="form-control" required
                           value="<?= esc($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="heslo"><?= esc(t('heslo_hint')) ?></label>
                    <input type="password" id="heslo" name="heslo" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-success w-100"><?= esc(t('tlacitko_vytvorit')) ?></button>
            </form>

            <hr>
            <p class="mb-0 text-center">
                <?= esc(t('uz_mate_ucet')) ?> <a href="prihlaseni.php"><?= esc(t('prihlaste_se')) ?></a> | <a href="index.php"><?= esc(t('na_generator')) ?></a>
            </p>
        </div>
    </div>
</main>
<!-- plovoucí přepínač nastavení (stránka nemá navigaci) -->
<div class="position-fixed top-0 end-0 m-3 no-tisk">
    <?php $_UI_KUS = 'tlacitko'; require __DIR__ . '/ui_nastaveni.php'; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="motiv.js"></script>
<?php $_UI_KUS = 'modal'; require __DIR__ . '/ui_nastaveni.php'; ?>
</body>
</html>
