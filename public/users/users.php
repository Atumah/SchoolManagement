<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

requireRole('Admin');

$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: /users/users.php');
        exit;
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? '';
            $status = $_POST['status'] ?? 'Active';

            if (empty($firstName) || empty($lastName) || empty($password) || empty($email) || empty($role)) {
                setFlashMessage('error', 'Please fill in all required fields');
            } elseif ($password !== $confirmPassword) {
                setFlashMessage('error', 'Passwords do not match');
            } elseif (strlen($password) < 8) {
                setFlashMessage('error', 'Password must be at least 8 characters');
            } elseif (getUserByEmail($email) !== null) {
                setFlashMessage('error', 'Email already exists');
            } else {
                // Username will be generated from email in addUser function
                // Password will be hashed in addUser function
                addUser([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => $password,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status
                ]);
                setFlashMessage('success', 'User added successfully');
                header('Location: /users/users.php');
                exit;
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $user = getUserById($id);
            if ($user && $user['id'] !== $currentUser['id']) {
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $email = $_POST['email'] ?? '';
                $role = $_POST['role'] ?? '';
                $status = $_POST['status'] ?? 'Active';
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
                    setFlashMessage('error', 'Please fill in all required fields');
                } elseif (!empty($password) && $password !== $confirmPassword) {
                    setFlashMessage('error', 'Passwords do not match');
                } elseif (!empty($password) && strlen($password) < 8) {
                    setFlashMessage('error', 'Password must be at least 8 characters');
                } else {
                    // Password will be hashed in updateUser function if provided
                    $updateData = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'name' => trim($firstName . ' ' . $lastName),
                        'email' => $email,
                        'role' => $role,
                        'status' => $status
                    ];
                    if (!empty($password)) {
                        $updateData['password'] = $password; // Will be hashed in updateUser
                    }
                    updateUser($id, $updateData);
                    setFlashMessage('success', 'User updated successfully');
                    header('Location: /users/users.php');
                    exit;
                }
            } else {
                setFlashMessage('error', 'Cannot edit your own account');
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $user = getUserById($id);
            if ($user && $user['id'] !== $currentUser['id']) {
                deleteUser($id);
                setFlashMessage('success', 'User deleted successfully');
                header('Location: /users/users.php');
                exit;
            } else {
                setFlashMessage('error', 'Cannot delete your own account');
            }
        }
    }
}

// Get all users
$users = getAllUsers();

// Filtering
$filterRole = $_GET['filter_role'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';
$search = $_GET['search'] ?? '';

if ($filterRole) {
    $users = array_filter($users, fn($u) => $u['role'] === $filterRole);
}
if ($filterStatus) {
    $users = array_filter($users, fn($u) => $u['status'] === $filterStatus);
}
if ($search) {
    $users = array_filter($users, function ($u) use ($search) {
        return stripos($u['username'], $search) !== false ||
               stripos($u['name'], $search) !== false ||
               stripos($u['email'], $search) !== false;
    });
}

$editingId = $_GET['edit'] ?? null;
$editingUser = $editingId ? getUserById((int)$editingId) : null;
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>User Management</h1>
            <button class="form-toggle-btn" onclick="openAddUserModal()">+ Add User</button>
        </div>
        
        <?php if ($flash) : ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="filter_role">Filter by Role</label>
                    <select id="filter_role" name="filter_role">
                        <option value="">All Roles</option>
                        <option value="Teacher" <?= $filterRole === 'Teacher' ? 'selected' : '' ?>>Teacher</option>
                        <option value="Admin" <?= $filterRole === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Principal" <?= $filterRole === 'Principal' ? 'selected' : '' ?>>Principal</option>
                        <option value="Web Designer" <?= $filterRole === 'Web Designer' ? 'selected' : '' ?>>Web Designer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_status">Filter by Status</label>
                    <select id="filter_status" name="filter_status">
                        <option value="">All Statuses</option>
                        <option value="Active" <?= $filterStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $filterStatus === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by username, name, or email">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="/users/users.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
        
        
        <!-- Users Table -->
        <div class="table-container">
            <?php if (empty($users)) : ?>
                <div class="empty-state">
                    <p>No users found.</p>
                </div>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user) : ?>
                            <tr>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($user['role']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $user['status'] === 'Active' ? 'success' : 'danger' ?>">
                                        <?= htmlspecialchars($user['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button type="button" class="btn btn-secondary btn-small edit-user-btn" data-user='<?= htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8') ?>'>Edit</button>
                                        <?php if ($user['id'] !== $currentUser['id']) : ?>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                        <?php else : ?>
                                            <button class="btn btn-secondary btn-small" disabled>Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Add User Modal -->
    <div class="modal-overlay" id="addUserModal" onclick="closeAddUserModalOnOverlay(event)">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h2>Add User</h2>
                <button type="button" class="modal-close-btn" onclick="closeAddUserModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="" id="addUserForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="add_first_name">First Name</label>
                        <input type="text" id="add_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="add_last_name">Last Name</label>
                        <input type="text" id="add_last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="add_password">Password</label>
                        <input type="password" id="add_password" name="password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="add_confirm_password">Confirm Password</label>
                        <input type="password" id="add_confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="add_email">Email</label>
                        <input type="email" id="add_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="add_role">Role</label>
                        <select id="add_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Admin">Admin</option>
                            <option value="Principal">Principal</option>
                            <option value="Web Designer">Web Designer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_status">Status</label>
                        <select id="add_status" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editUserModal" onclick="closeEditUserModalOnOverlay(event)">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button type="button" class="modal-close-btn" onclick="closeEditUserModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_user_id">
                    <div class="form-group">
                        <label for="edit_first_name">First Name</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Last Name</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">Password (leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password" minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="edit_confirm_password">Confirm Password (if changing)</label>
                        <input type="password" id="edit_confirm_password" name="confirm_password" minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role</label>
                        <select id="edit_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Admin">Admin</option>
                            <option value="Principal">Principal</option>
                            <option value="Web Designer">Web Designer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Add User Modal Functions
    function openAddUserModal() {
        const modal = document.getElementById('addUserModal');
        if (!modal) return;
        
        // Reset form fields
        document.getElementById('add_first_name').value = '';
        document.getElementById('add_last_name').value = '';
        document.getElementById('add_password').value = '';
        document.getElementById('add_confirm_password').value = '';
        document.getElementById('add_email').value = '';
        document.getElementById('add_role').value = '';
        document.getElementById('add_status').value = 'Active';
        
        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeAddUserModal() {
        const modal = document.getElementById('addUserModal');
        if (!modal) return;
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    function closeAddUserModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeAddUserModal();
        }
    }
    
    // Edit User Modal Functions
    function openEditUserModal(user) {
        const modal = document.getElementById('editUserModal');
        if (!modal) return;
        
        // Populate form fields
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_first_name').value = user.first_name || '';
        document.getElementById('edit_last_name').value = user.last_name || '';
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_confirm_password').value = '';
        document.getElementById('edit_email').value = user.email || '';
        document.getElementById('edit_role').value = user.role || '';
        document.getElementById('edit_status').value = user.status || 'Active';
        
        // Disable role/status if editing own account
        const currentUserId = <?= $currentUser['id'] ?>;
        if (user.id == currentUserId) {
            document.getElementById('edit_role').disabled = true;
            document.getElementById('edit_status').disabled = true;
        } else {
            document.getElementById('edit_role').disabled = false;
            document.getElementById('edit_status').disabled = false;
        }
        
        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeEditUserModal() {
        const modal = document.getElementById('editUserModal');
        if (!modal) return;
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    function closeEditUserModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeEditUserModal();
        }
    }
    
    // Attach event listeners to all edit buttons
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-user-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userData = this.getAttribute('data-user');
                if (userData) {
                    try {
                        const user = JSON.parse(userData);
                        openEditUserModal(user);
                    } catch (e) {
                        console.error('Error parsing user data:', e);
                    }
                }
            });
        });
    });
    
    // Close any modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddUserModal();
            closeEditUserModal();
        }
    });
    
    <?php if ($editingUser) : ?>
    // Auto-open edit modal if editing from URL
    document.addEventListener('DOMContentLoaded', function() {
        const userData = <?= json_encode($editingUser, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        openEditUserModal(userData);
    });
    <?php endif; ?>
    </script>
</body>
</html>

