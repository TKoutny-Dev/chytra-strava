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
        $chyba = 'Vypršela platnost formuláře, zkuste to prosím znovu.';
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
            $chyba = 'Neplatný e-mail nebo heslo.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uzivatel'] = [
                'id'    => (int) $uzivatel['id'],
                'jmeno' => $uzivatel['jmeno'],
                'role'  => $uzivatel['role'],
            ];
            flash('Vítejte zpět, ' . $uzivatel['jmeno'] . '!');

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
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přihlášení – OptiMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styl.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container" style="max-width: 430px;">
    <div class="card shadow-sm mt-5">
        <div class="card-body p-4">
            <h1 class="h4 text-center mb-3">🥗 OptiMeal – přihlášení</h1>
            <?= zobraz_flash() ?>
            <?php if ($chyba !== null): ?>
                <div class="alert alert-danger"><?= esc($chyba) ?></div>
            <?php endif; ?>

            <form method="post" action="prihlaseni.php">
                <?= csrf_pole() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control"
                           required autofocus value="<?= esc($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="heslo">Heslo</label>
                    <input type="password" id="heslo" name="heslo" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Přihlásit se</button>
            </form>

            <hr>
            <p class="mb-1 text-center">
                Nemáte účet? <a href="registrace.php">Zaregistrujte se</a> | <a href="index.php">Na generátor</a>
            </p>
        </div>
        <div class="card-footer bg-white text-muted small text-center">
            Ukázkové účty na obhajobu:<br>
            admin@optimeal.cz / <strong>Admin123!</strong> (administrace)<br>
            petr@optimeal.cz / <strong>Petr123!</strong>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
