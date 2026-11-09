<?php
declare(strict_types=1);

/**
 * odhlaseni.php – odhlášení uživatele z aplikace.
 * Co se děje technicky:
 *  - pryč hodnoty z pole $_SESSION
 *  - stará cookie se smaže (setcookie s časem v minulosti)
 *  - session_destroy() – zneplatní se session data na serveru
 */

require_once __DIR__ . '/spolecne.php';

odhlasit();

// vlastní krátká potvrzená stránka – ne přesměrování, aby bylo vidět, že odhlášení proběhlo
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="3;url=index.php">
    <title>Odhlášeno – OptiMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container text-center" style="max-width: 460px;">
    <div class="card shadow-sm mt-5 p-4">
        <h1 class="h4">👋 Byli jste odhlášeni</h1>
        <p class="text-muted mb-0">Za okamžik vás přesměrujeme na hlavní stránku...</p>
        <a href="index.php" class="btn btn-success mt-3">Na hlavní stránku</a>
    </div>
</main>
</body>
</html>
