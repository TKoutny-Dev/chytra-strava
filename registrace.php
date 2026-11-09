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
        $chyby[] = 'Vypršela platnost formuláře, zkuste to prosím znovu.';
    } else {
        $jmeno = trim((string) ($_POST['jmeno'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $heslo = (string) ($_POST['heslo'] ?? '');

        // hranatá validace vstupů (serverová, ne jen v prohlížeči)
        if (mb_strlen($jmeno) < 3 || mb_strlen($jmeno) > 100) {
            $chyby[] = 'Jméno musí mít 3–100 znaků.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $chyby[] = 'Zadejte platný e-mail (max. 150 znaků).';
        }
        if (mb_strlen($heslo) < 8) {
            $chyby[] = 'Heslo musí mít alespoň 8 znaků.';
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
                flash('Registrace proběhla úspěšně. Vítejte, ' . $jmeno . '!');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                // unikátní klíč (1062) = duplicitní jméno nebo e-mail
                if ((int) $e->errorInfo[1] === 1062) {
                    $chyby[] = 'Uživatel se stejným jménem nebo e-mailem už existuje.';
                } else {
                    error_log('Chyba registrace: ' . $e->getMessage());
                    $chyby[] = 'Registraci se nepovedlo uložit, zkuste to prosím později.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrace – OptiMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styl.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container" style="max-width: 480px;">
    <div class="card shadow-sm mt-5">
        <div class="card-body p-4">
            <h1 class="h4 text-center mb-3">🥗 OptiMeal – registrace</h1>
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
                    <label class="form-label" for="jmeno">Jméno</label>
                    <input type="text" id="jmeno" name="jmeno" class="form-control" required
                           minlength="3" maxlength="100" value="<?= esc($_POST['jmeno'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" required
                           value="<?= esc($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="heslo">Heslo (min. 8 znaků)</label>
                    <input type="password" id="heslo" name="heslo" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-success w-100">Vytvořit účet</button>
            </form>

            <hr>
            <p class="mb-0 text-center">
                Už máte účet? <a href="prihlaseni.php">Přihlaste se</a> | <a href="index.php">Na generátor</a>
            </p>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
