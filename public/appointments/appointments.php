<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
                'mysql:host=mariadb;port=3306;dbname=app;charset=utf8mb4',
                'app',
                'secret',
                [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                ]
        );
    }
    return $pdo;
}

requireAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer', 'Student']);

$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
        header('Location: /appointments/appointments.php');
        exit;
    }

    $pdo = getDBConnection();

    if ($_POST['action'] === 'create') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['appointment_date'];
        $time = $_POST['appointment_time'];
        $appointeeId = $_POST['appointee_id'] ?? null;

        if (strlen($title) >= 3 && validateDateTime($date, $time)) {
            $id = 'apt_' . bin2hex(random_bytes(12));
            $stmt = $pdo->prepare("INSERT INTO appointments (id, created_by_id, appointee_id, title, description, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$id, $currentUser['id'], $appointeeId, $title, $description, $date, $time]);
            setFlashMessage('success', 'Appointment request sent');
        } else {
            setFlashMessage('error', 'Invalid title or date/time');
        }
    } elseif ($_POST['action'] === 'accept') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Accepted', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND appointee_id = ?");
        $stmt->execute([$id, $currentUser['id']]);
        setFlashMessage('success', 'Appointment accepted');
    } elseif ($_POST['action'] === 'decline') {
        $id = $_POST['id'];
        $declineReason = trim($_POST['decline_reason'] ?? '');
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Declined', description = CONCAT(description, '\n\n--- DECLINED REASON: ', ?), updated_at = CURRENT_TIMESTAMP WHERE id = ? AND appointee_id = ?");
        $stmt->execute([$declineReason, $id, $currentUser['id']]);
        setFlashMessage('success', 'Appointment declined');
    } elseif ($_POST['action'] === 'cancel' && hasAnyRole(['Admin', 'Principal', 'Web Designer'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Appointment cancelled');
    }
    header('Location: /appointments/appointments.php');
    exit;
}

function validateDateTime($date, $time): bool {
    return validateDate($date) && (empty($time) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time));
}

function validateDate($date, $format = 'Y-m-d'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Fetch appointments based on role
$pdo = getDBConnection();
$role = $currentUser['role'];
$appointments = [];

if (in_array($role, ['Admin', 'Principal', 'Web Designer'])) {
    // Admins see ALL appointments
    $stmt = $pdo->query("SELECT a.*, creator.name as creator_name, appointee.name as appointee_name FROM appointments a 
                        LEFT JOIN users creator ON a.created_by_id = creator.id 
                        LEFT JOIN users appointee ON a.appointee_id = appointee.id 
                        ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC");
    $appointments = $stmt->fetchAll();
} else {
    // Students/Teachers see only their own (sent OR received)
    $stmt = $pdo->query("SELECT a.*, creator.name as creator_name, appointee.name as appointee_name FROM appointments a 
                        LEFT JOIN users creator ON a.created_by_id = creator.id 
                        LEFT JOIN users appointee ON a.appointee_id = appointee.id 
                        WHERE a.created_by_id = '{$currentUser['id']}' OR a.appointee_id = '{$currentUser['id']}' 
                        ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC");
    $appointments = $stmt->fetchAll();
}

// Get possible appointees for dropdown (teachers for students, principal for teachers)
$possibleAppointees = [];
if (hasRole('Student')) {
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'Teacher' AND status = 'Active'");
    $possibleAppointees = $stmt->fetchAll();
} elseif (hasRole('Teacher')) {
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'Principal' AND status = 'Active'");
    $possibleAppointees = $stmt->fetchAll();
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
    <style>
        .appointments-container { min-height: 60vh; }
        .appointment-card { padding: 1.5rem; border: 1px solid #444; border-radius: 12px; margin-bottom: 1rem; transition: all 0.2s; }
        .appointment-card:hover { border-color: #0ea5e9; }
        .status-pending { border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.1); }
        .status-accepted { border-left: 4px solid #10b981; background: rgba(16, 185, 129, 0.1); }
        .status-declined { border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.1); }
        .status-cancelled { border-left: 4px solid #6b7280; background: rgba(107, 114, 128, 0.1); }
        .appointment-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .appointment-title { font-size: 1.3rem; font-weight: bold; color: #e2e8f0; }
        .appointment-meta { color: #94a3b8; font-size: 0.9rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-pending .status-badge { background: #f59e0b; color: white; }
        .status-accepted .status-badge { background: #10b981; color: white; }
        .status-declined .status-badge { background: #ef4444; color: white; }
        .status-cancelled .status-badge { background: #6b7280; color: white; }
        .decline-reason { background: #1e1b4b; padding: 1rem; border-radius: 8px; margin-top: 1rem; border-left: 4px solid #ef4444; }
        .action-buttons { display: flex; gap: 0.5rem; margin-top: 1rem; }
        @media (max-width: 768px) { .appointment-header { flex-direction: column; gap: 0.5rem; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/nav.php'; ?>
<main class="container">
    <?php include __DIR__ . '/../includes/back-button.php'; ?>

    <div class="page-header">
        <h1>My Appointments</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">+ Request Appointment</button>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="appointments-container">
        <?php if (empty($appointments)): ?>
            <div class="empty-state" style="text-align: center; padding: 3rem; color: #94a3b8;">
                <h3>No appointments</h3>
                <p>Request an appointment or wait for responses</p>
            </div>
        <?php else: ?>
            <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-card status-<?= strtolower($appointment['status']) ?>">
                    <div class="appointment-header">
                        <div>
                            <div class="appointment-title"><?= htmlspecialchars($appointment['title']) ?></div>
                            <div class="appointment-meta">
                                📅 <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?>
                                <?php if ($appointment['appointment_time']): ?>
                                    🕒 <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge"><?= htmlspecialchars($appointment['status']) ?></span>
                    </div>

                    <?php if ($appointment['description']): ?>
                        <div style="margin: 1rem 0; color: #cbd5e1; line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($appointment['description'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="appointment-meta" style="margin-top: 1rem;">
                        <?php if ($appointment['creator_name'] && $currentUser['id'] !== $appointment['created_by_id']): ?>
                            From: <?= htmlspecialchars($appointment['creator_name']) ?>
                        <?php endif; ?>
                        <?php if ($appointment['appointee_name'] && $currentUser['id'] !== $appointment['appointee_id']): ?>
                            To: <?= htmlspecialchars($appointment['appointee_name']) ?>
                        <?php endif; ?>
                        • <?= date('M j \a\t g:i A', strtotime($appointment['created_at'])) ?>
                    </div>

                    <?php if (strpos($appointment['description'] ?? '', '--- DECLINED REASON:') !== false): ?>
                        <div class="decline-reason">
                            <strong>Decline Reason:</strong><br>
                            <?= htmlspecialchars(substr($appointment['description'], strpos($appointment['description'], '--- DECLINED REASON:') + 19)) ?>
                        </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <?php if ($appointment['status'] === 'Pending'): ?>
                            <?php if ($appointment['appointee_id'] === $currentUser['id']): // Can accept/decline ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Accept this appointment?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($appointment['id']) ?>">
                                    <button type="submit" class="btn btn-success btn-small">Accept</button>
                                </form>
                                <button class="btn btn-danger btn-small" onclick="openDeclineModal('<?= htmlspecialchars($appointment['id']) ?>')">Decline</button>
                            <?php endif; ?>
                        <?php elseif (hasAnyRole(['Admin', 'Principal', 'Web Designer'])): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Cancel this appointment?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($appointment['id']) ?>">
                                <button type="submit" class="btn btn-secondary btn-small">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Create Appointment Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2>Request Appointment</h2>
            <button class="modal-close-btn" onclick="closeCreateModal()">&times;</button>
        </div>
        <form method="POST" id="createForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required minlength="3" maxlength="255">
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="appointment_date" required>
            </div>
            <div class="form-group">
                <label>Time *</label>
                <input type="time" name="appointment_time" required>
            </div>
            <?php if (!empty($possibleAppointees)): ?>
                <div class="form-group">
                    <label>With</label>
                    <select name="appointee_id">
                        <option value="">Anyone (open request)</option>
                        <?php foreach ($possibleAppointees as $person): ?>
                            <option value="<?= htmlspecialchars($person['id']) ?>">
                                <?= htmlspecialchars($person['name']) ?> (<?= htmlspecialchars($person['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" style="width: 100%; resize: vertical;" maxlength="1000"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Decline Modal -->
<div class="modal-overlay" id="declineModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2>Decline Appointment</h2>
            <button class="modal-close-btn" onclick="closeDeclineModal()">&times;</button>
        </div>
        <form method="POST" id="declineForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="id" id="decline_appointment_id">
            <div class="form-group">
                <label>Reason for declining (optional)</label>
                <textarea name="decline_reason" rows="4" style="width: 100%; resize: vertical;" maxlength="500" placeholder="e.g. Not available, Wrong time, etc."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeclineModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Decline Appointment</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('createModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function openDeclineModal(id) {
        document.getElementById('decline_appointment_id').value = id;
        document.getElementById('declineModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDeclineModal() {
        document.getElementById('declineModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                if (this.id === 'createModal') closeCreateModal();
                if (this.id === 'declineModal') closeDeclineModal();
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCreateModal();
            closeDeclineModal();
        }
    });
</script>
</body>
</html>
