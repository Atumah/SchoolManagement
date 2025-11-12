<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <h1 style="font-size: 4rem; margin: 0; color: #dc2626;">403</h1>
            <h2>Access Denied</h2>
            <p>You do not have permission to access this page.</p>
            <a href="/index.php" class="btn btn-primary">Return to Home</a>
        </div>
    </main>
</body>
</html>

