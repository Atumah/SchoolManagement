<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

function getDBConnection(): PDO {
    return \App\Database\Database::connection();
}


requireAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer', 'Student']);

$currentUser = getCurrentUser();
$flash = getFlashMessage();
$selectedAnnouncementId = $_GET['view'] ?? null;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
        header('Location: /announcements/announcements.php');
        exit;
    }

    if (isset($_POST['action'])) {
        $pdo = getDBConnection();  // Use our function

        if ($_POST['action'] === 'create' && hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])) {
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);

            if (strlen($title) >= 3 && strlen($content) >= 10) {
                $id = 'ann_' . bin2hex(random_bytes(12)); // VARCHAR(30) ID
                $stmt = $pdo->prepare("INSERT INTO announcements (id, title, content, author_id, is_published) VALUES (?, ?, ?, ?, TRUE)");
                $stmt->execute([$id, $title, $content, $currentUser['id']]);
                setFlashMessage('success', 'Announcement created successfully');
            } else {
                setFlashMessage('error', 'Title must be 3+ chars, content 10+ chars');
            }
        } elseif ($_POST['action'] === 'delete' && hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND author_id = ?");
            $stmt->execute([$id, $currentUser['id']]);
            setFlashMessage('success', 'Announcement deleted');
        }
    }
    header('Location: /announcements/announcements.php' . ($selectedAnnouncementId ? '?view=' . urlencode($selectedAnnouncementId) : ''));
    exit;
}

// Fetch announcements
$pdo = getDBConnection();  // Use our function
if (hasRole('Student')) {
    $stmt = $pdo->query("SELECT a.*, u.name as author_name FROM announcements a JOIN users u ON a.author_id = u.id WHERE is_published = TRUE ORDER BY created_at DESC");
} else {
    $stmt = $pdo->query("SELECT a.*, u.name as author_name FROM announcements a JOIN users u ON a.author_id = u.id ORDER BY created_at DESC");
}
$announcements = $stmt->fetchAll();

$selectedAnnouncement = null;
if ($selectedAnnouncementId) {
    $stmt = $pdo->prepare("SELECT a.*, u.name as author_name FROM announcements a JOIN users u ON a.author_id = u.id WHERE a.id = ?");
    $stmt->execute([$selectedAnnouncementId]);
    $selectedAnnouncement = $stmt->fetch();
}

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
    <style>
        .announcements-container { display: flex; gap: 2rem; min-height: 60vh; }
        .announcements-list { flex: 1; max-width: 400px; }
        .announcement-card {
            cursor: pointer; padding: 1rem; border: 1px solid #444; border-radius: 8px;
            margin-bottom: 1rem; background: #1a1a2e; transition: all 0.2s;
        }
        .announcement-card:hover { background: #16213e; }
        .announcement-card.active { border-color: #0ea5e9; background: #16213e; }
        .announcement-details { flex: 2; }
        .announcement-details.empty { display: flex; align-items: center; justify-content: center; color: #888; font-style: italic; }
        .announcement-title { font-size: 1.5rem; margin-bottom: 0.5rem; color: #0ea5e9; }
        .announcement-meta { color: #888; font-size: 0.9rem; margin-bottom: 1rem; }
        .announcement-content { line-height: 1.6; white-space: pre-wrap; }
        @media (max-width: 768px) {
            .announcements-container { flex-direction: column; }
            .announcements-list { max-width: none; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/nav.php'; ?>
<main class="container">
    <?php include __DIR__ . '/../includes/back-button.php'; ?>

    <div class="page-header">
        <h1>Announcements</h1>
        <?php if (hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">+ New Announcement</button>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="announcements-container">
        <div class="announcements-list">
            <h3>All Announcements</h3>
            <?php if (empty($announcements)): ?>
                <div class="empty-state">No announcements available.</div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card <?= $selectedAnnouncementId === $announcement['id'] ? 'active' : '' ?>"
                         onclick="selectAnnouncement('<?= htmlspecialchars($announcement['id']) ?>')"
                         data-id="<?= htmlspecialchars($announcement['id']) ?>">
                        <h4><?= htmlspecialchars($announcement['title']) ?></h4>
                        <p class="announcement-meta">
                            By <?= htmlspecialchars($announcement['author_name']) ?> •
                            <?= date('M j, Y', strtotime($announcement['created_at'])) ?>
                        </p>
                        <?php if (strlen($announcement['content']) > 100): ?>
                            <p><?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>...</p>
                        <?php else: ?>
                            <p><?= htmlspecialchars($announcement['content']) ?></p>
                        <?php endif; ?>
                        <?php if (!hasRole('Student') && $announcement['author_id'] === $currentUser['id']): ?>
                            <div style="margin-top: 0.5rem;">
                                <button class="btn btn-danger btn-small" onclick="event.stopPropagation(); deleteAnnouncement('<?= htmlspecialchars($announcement['id']) ?>')">Delete</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="announcement-details <?= !$selectedAnnouncement ? 'empty' : '' ?>" id="announcementDetails">
            <?php if (!$selectedAnnouncement): ?>
                <div>Click an announcement to view details</div>
            <?php else: ?>
                <div>
                    <h2 class="announcement-title"><?= htmlspecialchars($selectedAnnouncement['title']) ?></h2>
                    <div class="announcement-meta">
                        By <strong><?= htmlspecialchars($selectedAnnouncement['author_name']) ?></strong> •
                        Posted <?= date('F j, Y \a\t g:i A', strtotime($selectedAnnouncement['created_at'])) ?>
                        <?php if ($selectedAnnouncement['updated_at'] !== $selectedAnnouncement['created_at']): ?>
                            • Updated <?= date('F j, Y \a\t g:i A', strtotime($selectedAnnouncement['updated_at'])) ?>
                        <?php endif; ?>
                    </div>
                    <div class="announcement-content"><?= nl2br(htmlspecialchars($selectedAnnouncement['content'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Create Announcement Modal -->
<?php if (hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])): ?>
    <div class="modal-overlay" id="createModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h2>New Announcement</h2>
                <button class="modal-close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST" id="createForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required minlength="3" maxlength="255">
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" required rows="8" style="width: 100%; resize: vertical;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    let currentAnnouncementId = '<?= htmlspecialchars($selectedAnnouncementId ?? '') ?>';

    function selectAnnouncement(id) {
        window.location.href = `/announcements/announcements.php?view=${encodeURIComponent(id)}`;
    }

    function deleteAnnouncement(id) {
        if (confirm('Delete this announcement?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close modal on overlay click
    document.getElementById('createModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeCreateModal();
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCreateModal();
    });
</script>
</body>
</html>
