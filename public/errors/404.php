<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <h1 style="font-size: 4rem; margin: 0; color: #dc2626;">404</h1>
            <h2>Page Not Found</h2>
            <p>The page you are looking for does not exist.</p>
            <a href="/index.php" class="btn btn-primary">Return to Home</a>
        </div>
    </main>
</body>
</html>

