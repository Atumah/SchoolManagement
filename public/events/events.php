<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

function getDBConnection(): PDO {
    return \App\Database\Database::connection();
}

// Create upload directory
$uploadDir = __DIR__ . '/../assets/events/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

requireAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer', 'Student']);

$currentUser = getCurrentUser();
$flash = getFlashMessage();
$selectedEventId = $_GET['view'] ?? null;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
        header('Location: /events/events.php');
        exit;
    }

    $pdo = getDBConnection();

    if ($_POST['action'] === 'create' && hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $eventDate = $_POST['event_date'];
        $eventTime = $_POST['event_time'] ?? null;
        $location = trim($_POST['location'] ?? '');
        $imagePath = null;

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = uploadEventImage($_FILES['image'], $uploadDir);
            if (!$imagePath) {
                setFlashMessage('error', 'Invalid image. PNG only, max 2.5MB.');
                header('Location: /events/events.php');
                exit;
            }
        }

        if (strlen($title) >= 3 && validateDate($eventDate)) {
            $id = 'evt_' . bin2hex(random_bytes(12));
            $stmt = $pdo->prepare("INSERT INTO events (id, title, description, event_date, event_time, location, author_id, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $title, $description, $eventDate, $eventTime, $location, $currentUser['id'], $imagePath]);
            setFlashMessage('success', 'Event created successfully');
        } else {
            setFlashMessage('error', 'Invalid title or date');
        }
    } elseif ($_POST['action'] === 'delete' && hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])) {
        $id = $_POST['id'];
        // Get event to delete image
        $stmt = $pdo->prepare("SELECT image_path FROM events WHERE id = ? AND author_id = ?");
        $stmt->execute([$id, $currentUser['id']]);
        $event = $stmt->fetch();

        if ($event && $event['image_path']) {
            unlink($uploadDir . basename($event['image_path']));
        }

        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND author_id = ?");
        $stmt->execute([$id, $currentUser['id']]);
        setFlashMessage('success', 'Event deleted');
    }
    header('Location: /events/events.php' . ($selectedEventId ? '?view=' . urlencode($selectedEventId) : ''));
    exit;
}

function uploadEventImage(array $file, string $uploadDir): ?string {
    $maxSize = 2.5 * 1024 * 1024; // 2.5MB

    // Check size
    if ($file['size'] > $maxSize) {
        return null;
    }

    // Check PNG magic bytes
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime !== 'image/png') {
        return null;
    }

    // Additional magic bytes check
    $fp = fopen($file['tmp_name'], 'rb');
    $header = fread($fp, 8);
    fclose($fp);
    if (bin2hex($header) !== '89504e470d0a1a0a') {
        return null;
    }

    // Generate filename
    $extension = 'png';
    $filename = 'evt_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return '/assets/events/' . $filename;
    }

    return null;
}

function validateDate($date, $format = 'Y-m-d'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Fetch events
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT e.*, u.name as author_name FROM events e JOIN users u ON e.author_id = u.id ORDER BY e.event_date ASC, e.event_time ASC");
$events = $stmt->fetchAll();

$selectedEvent = null;
if ($selectedEventId) {
    $stmt = $pdo->prepare("SELECT e.*, u.name as author_name FROM events e JOIN users u ON e.author_id = u.id WHERE e.id = ?");
    $stmt->execute([$selectedEventId]);
    $selectedEvent = $stmt->fetch();
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
    <style>
        .events-container { display: flex; gap: 2rem; min-height: 60vh; }
        .events-list { flex: 1; max-width: 400px; }
        .event-card { cursor: pointer; padding: 1rem; border: 1px solid #444; border-radius: 8px; margin-bottom: 1rem; background: #1a1a2e; transition: all 0.2s; }
        .event-card:hover { background: #16213e; }
        .event-card.active { border-color: #10b981; background: #16213e; }
        .event-image { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-bottom: 0.5rem; background: #333; }
        .no-image { width: 60px; height: 60px; background: linear-gradient(45deg, #10b981, #059669); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.8rem; }
        .event-details { flex: 2; }
        .event-details.empty { display: flex; align-items: center; justify-content: center; color: #888; font-style: italic; }
        .event-title { font-size: 1.5rem; margin-bottom: 0.5rem; color: #10b981; }
        .event-meta { color: #888; font-size: 0.9rem; margin-bottom: 1rem; }
        .event-date { font-weight: bold; color: #10b981; margin-bottom: 0.5rem; }
        .event-content { line-height: 1.6; }
        .event-featured-image { max-width: 100%; max-height: 300px; object-fit: cover; border-radius: 12px; margin: 1rem 0; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        @media (max-width: 768px) { .events-container { flex-direction: column; } .events-list { max-width: none; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/nav.php'; ?>
<main class="container">
    <?php include __DIR__ . '/../includes/back-button.php'; ?>

    <div class="page-header">
        <h1>Events</h1>
        <?php if (hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">+ New Event</button>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="events-container">
        <div class="events-list">
            <h3>Upcoming Events</h3>
            <?php if (empty($events)): ?>
                <div class="empty-state">No events scheduled.</div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-card <?= $selectedEventId === $event['id'] ? 'active' : '' ?>"
                         onclick="selectEvent('<?= htmlspecialchars($event['id']) ?>')"
                         data-id="<?= htmlspecialchars($event['id']) ?>">
                        <?php if ($event['image_path']): ?>
                            <img src="<?= htmlspecialchars($event['image_path']) ?>" alt="<?= htmlspecialchars($event['title']) ?>" class="event-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="no-image" style="display: none;"><?= htmlspecialchars(substr($event['title'], 0, 2)) ?></div>
                        <?php else: ?>
                            <div class="no-image"><?= htmlspecialchars(substr($event['title'], 0, 2)) ?></div>
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($event['title']) ?></h4>
                        <div class="event-date">
                            <?= date('M j, Y', strtotime($event['event_date'])) ?>
                            <?php if ($event['event_time']): ?>
                                at <?= date('g:i A', strtotime($event['event_time'])) ?>
                            <?php endif; ?>
                        </div>
                        <p class="event-meta">
                            <?= htmlspecialchars($event['location'] ?: 'TBD') ?> •
                            By <?= htmlspecialchars($event['author_name']) ?>
                        </p>
                        <?php if ($event['description'] && strlen($event['description']) > 80): ?>
                            <p><?= htmlspecialchars(substr($event['description'], 0, 80)) ?>...</p>
                        <?php endif; ?>
                        <?php if (!hasRole('Student') && $event['author_id'] === $currentUser['id']): ?>
                            <div style="margin-top: 0.5rem;">
                                <button class="btn btn-danger btn-small" onclick="event.stopPropagation(); deleteEvent('<?= htmlspecialchars($event['id']) ?>')">Delete</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="event-details <?= !$selectedEvent ? 'empty' : '' ?>">
            <?php if (!$selectedEvent): ?>
                <div>Click an event to view details</div>
            <?php else: ?>
                <div>
                    <?php if ($selectedEvent['image_path']): ?>
                        <img src="<?= htmlspecialchars($selectedEvent['image_path']) ?>" alt="<?= htmlspecialchars($selectedEvent['title']) ?>" class="event-featured-image">
                    <?php endif; ?>
                    <h2 class="event-title"><?= htmlspecialchars($selectedEvent['title']) ?></h2>
                    <div class="event-date">
                        📅 <?= date('l, F j, Y', strtotime($selectedEvent['event_date'])) ?>
                        <?php if ($selectedEvent['event_time']): ?>
                            🕒 <?= date('g:i A', strtotime($selectedEvent['event_time'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($selectedEvent['location']): ?>
                        <div class="event-meta">📍 <?= htmlspecialchars($selectedEvent['location']) ?></div>
                    <?php endif; ?>
                    <div class="event-meta">
                        By <strong><?= htmlspecialchars($selectedEvent['author_name']) ?></strong> •
                        Posted <?= date('M j, Y', strtotime($selectedEvent['created_at'])) ?>
                    </div>
                    <?php if ($selectedEvent['description']): ?>
                        <div class="event-content"><?= nl2br(htmlspecialchars($selectedEvent['description'])) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Create Event Modal -->
<?php if (hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])): ?>
    <div class="modal-overlay" id="createModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h2>New Event</h2>
                <button class="modal-close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST" id="createForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required minlength="3" maxlength="255">
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="event_date" required>
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" name="event_time">
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" maxlength="255">
                </div>
                <div class="form-group">
                    <label>Image (PNG, max 2.5MB)</label>
                    <input type="file" name="image" accept="image/png" style="width: 100%;">
                    <small>Optional - PNG only, max 2.5MB</small>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="6" style="width: 100%; resize: vertical;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function selectEvent(id) {
        window.location.href = `/events/events.php?view=${encodeURIComponent(id)}`;
    }

    function deleteEvent(id) {
        if (confirm('Delete this event and its image?')) {
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

    document.getElementById('createModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeCreateModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCreateModal();
    });
</script>
</body>
</html>
