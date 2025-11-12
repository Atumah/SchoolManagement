<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use App\Database\Migrator;
use App\Repository\SampleRepository;
use App\Support\Env;

$appName = Env::string('APP_NAME', 'SchoolManagement');
$appEnv = Env::string('APP_ENV', 'local');
$appUrl = Env::string('APP_URL', 'http://localhost:8080');
$phpMyAdminPort = Env::string('HOST_PHPMYADMIN_PORT', '49201');
$phpMyAdminUrl = sprintf('http://localhost:%s', $phpMyAdminPort);

$migrator = Migrator::forDefaultConnection();
$migrator->ensureSchema();

$repository = SampleRepository::forDefaultConnection();
$messages = $repository->latestMessages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
    <header class="hero">
        <div class="container">
            <h1><?= htmlspecialchars($appName) ?></h1>
            <p>Your PHP + MariaDB workspace is ready. Environment: <strong><?= htmlspecialchars($appEnv) ?></strong></p>
            <p>Base URL: <a href="<?= htmlspecialchars($appUrl) ?>"><?= htmlspecialchars($appUrl) ?></a></p>
            <p>
                Database console:
                <a href="<?= htmlspecialchars($phpMyAdminUrl) ?>" target="_blank"
                   rel="noopener noreferrer">phpMyAdmin</a>
            </p>
        </div>
    </header>
    <main class="container">
        <section>
            <h2>Starter Checklist</h2>
            <ol>
                <li>Copy <code>.env.example</code> to <code>.env</code> and adjust credentials.</li>
                <li>Run <code>docker compose up --build</code> to start the stack.</li>
                <li>Execute <code>composer check</code> before every push.</li>
            </ol>
        </section>
        <section>
            <h2>Latest Messages</h2>
            <?php if ($messages === []) : ?>
                <p>No messages found. Add your own by inserting rows into the <code>samples</code> table.</p>
            <?php else : ?>
                <ul>
                    <?php foreach ($messages as $message) : ?>
                        <li>
                            <span class="message"><?= htmlspecialchars($message['message']) ?></span>
                            <span class="meta">#<?= htmlspecialchars((string) $message['id']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
    <footer class="container">
        <small>
            &copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>.
            Powered by Docker, Composer, and GitHub Actions.
        </small>
    </footer>
</body>
</html>

