<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';

auth_start_session();
require_admin_role();

$userRepository = new UserRepository();
$settingsRepository = new SettingsRepository();
$settings = $settingsRepository->all();

$schoolName = (string) ($settings['school']['name'] ?? APP_SCHOOL_NAME);
$platformName = (string) ($settings['platform']['name'] ?? APP_DISPLAY_NAME);
$logo = (string) ($settings['school']['logo'] ?? '');
$favicon = (string) ($settings['platform']['favicon'] ?? '');

$currentUserId = auth_user_id();
$errors = [];
$success = null;
$editingUser = null;

$editId = get_string('edit', '');

if ($editId !== '') {
    $editingUser = $userRepository->findById($editId);

    if ($editingUser === null) {
        abort_not_found('Không tìm thấy tài khoản.');
    }
}

if (is_post()) {
    try {
        require_csrf_token();
        $action = post_string('action', '');

        // ============================================================
        // CREATE USER
        // ============================================================
        if ($action === 'create') {
            $username = trim(post_string('username', ''));
            $displayName = trim(post_string('display_name', ''));
            $email = trim(post_string('email', ''));
            $password = post_string('password', '');
            $role = post_string('role', 'admin');
            $status = post_string('status', 'active');

            // Username
            if ($username === '') {
                $errors[] = 'Tên đăng nhập không được để trống.';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
                $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái, số, dấu chấm, gạch dưới hoặc dấu gạch ngang.';
            } elseif ($userRepository->findByUsername($username) !== null) {
                $errors[] = 'Tên đăng nhập đã tồn tại.';
            }

            // Display name
            if ($displayName === '') {
                $errors[] = 'Tên hiển thị không được để trống.';
            }

            // Email
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Địa chỉ email không hợp lệ.';
            }

            // Password
            if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'Mật khẩu phải có ít nhất ' . PASSWORD_MIN_LENGTH . ' ký tự.';
            }

            // Role
            if (!in_array($role, ['admin'], true)) {
                $errors[] = 'Vai trò không hợp lệ.';
            }

            // Status
            if (!in_array($status, ['active', 'disabled'], true)) {
                $errors[] = 'Trạng thái tài khoản không hợp lệ.';
            }

            // Repository tự password_hash().
            if ($errors === []) {
                $now = now_datetime();

                $userRepository->create([
                    'id' => 'usr_' . bin2hex(random_bytes(8)),
                    'username' => $username,
                    'password' => $password,
                    'display_name' => $displayName,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'last_login_at' => '',
                ]);

                redirect(app_url('admin/users.php?created=1'));
            }
        }

        // ============================================================
        // UPDATE USER
        // ============================================================
        if ($action === 'update') {
            $userId = post_string('user_id', '');

            if ($userId === '') {
                $errors[] = 'ID tài khoản không hợp lệ.';
            }

            $user = $userId !== '' ? $userRepository->findById($userId) : null;

            if ($user === null) {
                abort_not_found('Không tìm thấy tài khoản.');
            }

            $displayName = trim(post_string('display_name', ''));
            $email = trim(post_string('email', ''));
            $role = post_string('role', 'admin');
            $status = post_string('status', 'active');
            $password = post_string('password', '');

            if ($displayName === '') {
                $errors[] = 'Tên hiển thị không được để trống.';
            }

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Địa chỉ email không hợp lệ.';
            }

            if (!in_array($role, ['admin'], true)) {
                $errors[] = 'Vai trò không hợp lệ.';
            }

            if (!in_array($status, ['active', 'disabled'], true)) {
                $errors[] = 'Trạng thái tài khoản không hợp lệ.';
            }

            if ($password !== '' && mb_strlen($password) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'Mật khẩu mới phải có ít nhất ' . PASSWORD_MIN_LENGTH . ' ký tự.';
            }

            if ($userId === $currentUserId && $status !== 'active') {
                $errors[] = 'Không thể vô hiệu hóa tài khoản quản trị đang đăng nhập.';
            }

            if ($errors === []) {
                // update(string $id, array $data)
                $userRepository->update($userId, [
                    'display_name' => $displayName,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status,
                    'updated_at' => now_datetime(),
                ]);

                // updatePassword() tự hash.
                if ($password !== '') {
                    $userRepository->updatePassword($userId, $password);
                }

                redirect(app_url('admin/users.php?updated=1'));
            }

            $editingUser = $user;
        }

        // ============================================================
        // TOGGLE STATUS
        // ============================================================
        if ($action === 'toggle_status') {
            $userId = post_string('user_id', '');

            if ($userId === '') {
                $errors[] = 'ID tài khoản không hợp lệ.';
            } elseif ($userId === $currentUserId) {
                $errors[] = 'Không thể khóa tài khoản quản trị đang đăng nhập.';
            } else {
                $user = $userRepository->findById($userId);

                if ($user === null) {
                    abort_not_found('Không tìm thấy tài khoản.');
                }

                $currentStatus = (string) ($user['status'] ?? 'active');
                $newStatus = $currentStatus === 'active' ? 'disabled' : 'active';

                $userRepository->update($userId, [
                    'status' => $newStatus,
                    'updated_at' => now_datetime(),
                ]);

                redirect(app_url('admin/users.php?status_changed=1'));
            }
        }
    } catch (ValidationException $exception) {
        $errors = array_merge($errors, $exception->errors());
    }
}

// Flash / query state
$created = get_string('created', '') === '1';
$updated = get_string('updated', '') === '1';
$statusChanged = get_string('status_changed', '') === '1';

// Load users
$users = $userRepository->all();

usort($users, static function (array $a, array $b): int {
    return strcmp((string) ($a['username'] ?? ''), (string) ($b['username'] ?? ''));
});

// Page variables
$csrfField = csrf_field();
$pageTitle = 'Quản lý tài khoản';
$showCreateForm = get_string('create', '') === '1' || ($editingUser === null && $errors !== []);

// Asset URLs
$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('admin/assets/css/users.css');
$scriptUrl = app_url('assets/js/admin.js');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle) ?> · <?= e($platformName) ?></title>
    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">
</head>

<body class="admin-page">

<header class="admin-header">
    <div class="admin-header__inner">

        <!-- BRAND -->
        <div class="admin-brand">
            <a href="<?= e(app_url('admin/index.php')) ?>" class="admin-brand__link" aria-label="<?= e($schoolName) ?>">
                <img class="admin-brand__logo" src="<?= e($logoUrl) ?>" alt="<?= e($schoolName) ?>">
                <span class="admin-brand__text">
                    <strong><?= e($platformName) ?></strong>
                    <span><?= e($schoolName) ?></span>
                </span>
            </a>
        </div>

        <!-- NAVIGATION -->
        <nav class="admin-nav" aria-label="Quản trị">
            <a href="<?= e(app_url('admin/index.php')) ?>">Tổng quan</a>
            <a href="<?= e(app_url('admin/publications.php')) ?>">Ấn phẩm</a>
            <a href="<?= e(app_url('admin/users.php')) ?>" aria-current="page">Tài khoản</a>
            <a href="<?= e(app_url('admin/settings.php')) ?>">Cài đặt</a>
        </nav>

        <!-- LOGOUT -->
        <form method="post" action="<?= e(app_url('admin/logout.php')) ?>" class="admin-logout-form">
            <?= $csrfField ?>
            <button type="submit">Đăng xuất</button>
        </form>

    </div>
</header>

<main class="admin-main">
    <div class="admin-container">

        <!-- PAGE HEADING -->
        <div class="admin-page-heading">
            <div>
                <p class="admin-eyebrow">Quản trị hệ thống</p>
                <h1><?= e($pageTitle) ?></h1>
                <p>Quản lý các tài khoản có quyền truy cập khu vực quản trị.</p>
            </div>

            <?php if ($editingUser === null): ?>
                <a href="<?= e(app_url('admin/users.php?create=1')) ?>" class="admin-button admin-button--primary">
                    + Thêm tài khoản
                </a>
            <?php else: ?>
                <a href="<?= e(app_url('admin/users.php')) ?>" class="admin-button">
                    Hủy chỉnh sửa
                </a>
            <?php endif; ?>
        </div>

        <!-- FLASH MESSAGES -->
        <?php if ($created): ?>
            <div class="admin-alert admin-alert--success">Đã tạo tài khoản thành công.</div>
        <?php endif; ?>

        <?php if ($updated): ?>
            <div class="admin-alert admin-alert--success">Đã cập nhật tài khoản thành công.</div>
        <?php endif; ?>

        <?php if ($statusChanged): ?>
            <div class="admin-alert admin-alert--success">Đã cập nhật trạng thái tài khoản.</div>
        <?php endif; ?>

        <!-- ERRORS -->
        <?php if ($errors !== []): ?>
            <div class="admin-alert admin-alert--error">
                <strong>Không thể hoàn tất thao tác:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ========================================================
             EDIT USER FORM
             ======================================================== -->
        <?php if ($editingUser !== null): ?>

            <section class="admin-card">

                <div class="admin-card__header">
                    <div>
                        <h2>Chỉnh sửa tài khoản</h2>
                        <p>Cập nhật thông tin và quyền truy cập.</p>
                    </div>
                </div>

                <form method="post" action="<?= e(app_url('admin/users.php')) ?>" class="admin-form" autocomplete="off">
                    <?= $csrfField ?>

                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" value="<?= e($editingUser['id'] ?? '') ?>">

                    <div class="admin-form-grid">

                        <div class="admin-form-field">
                            <label for="username">Tên đăng nhập</label>
                            <input id="username" type="text" value="<?= e($editingUser['username'] ?? '') ?>" disabled>
                            <small>Tên đăng nhập không thể thay đổi tại đây.</small>
                        </div>

                        <div class="admin-form-field">
                            <label for="display_name">Tên hiển thị</label>
                            <input id="display_name" name="display_name" type="text" maxlength="120" required
                                   value="<?= e($editingUser['display_name'] ?? '') ?>">
                        </div>

                        <div class="admin-form-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" maxlength="190"
                                   value="<?= e($editingUser['email'] ?? '') ?>">
                        </div>

                        <div class="admin-form-field">
                            <label for="role">Vai trò</label>
                            <select id="role" name="role">
                                <option value="admin" selected>Quản trị viên</option>
                            </select>
                        </div>

                        <div class="admin-form-field">
                            <label for="status">Trạng thái</label>
                            <select id="status" name="status">
                                <option value="active" <?= ($editingUser['status'] ?? '') === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                                <option value="disabled" <?= ($editingUser['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Đã khóa</option>
                            </select>
                        </div>

                        <div class="admin-form-field">
                            <label for="password">Mật khẩu mới</label>
                            <input id="password" name="password" type="password"
                                   minlength="<?= e((string) PASSWORD_MIN_LENGTH) ?>"
                                   autocomplete="new-password">
                            <small>Để trống nếu không muốn đổi mật khẩu. Tối thiểu <?= e((string) PASSWORD_MIN_LENGTH) ?> ký tự.</small>
                        </div>

                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="admin-button admin-button--primary">Lưu thay đổi</button>
                        <a href="<?= e(app_url('admin/users.php')) ?>" class="admin-button">Hủy</a>
                    </div>
                </form>

            </section>

        <!-- ========================================================
             CREATE USER FORM
             ======================================================== -->
        <?php elseif ($showCreateForm): ?>

            <section class="admin-card">

                <div class="admin-card__header">
                    <div>
                        <h2>Thêm tài khoản</h2>
                        <p>Tạo tài khoản quản trị mới.</p>
                    </div>
                </div>

                <form method="post" action="<?= e(app_url('admin/users.php')) ?>" class="admin-form" autocomplete="off">
                    <?= $csrfField ?>

                    <input type="hidden" name="action" value="create">

                    <div class="admin-form-grid">

                        <div class="admin-form-field">
                            <label for="username">Tên đăng nhập</label>
                            <input id="username" name="username" type="text" minlength="3" maxlength="50"
                                   required autocomplete="username">
                            <small>3–50 ký tự: chữ cái, số, ., _, -.</small>
                        </div>

                        <div class="admin-form-field">
                            <label for="display_name">Tên hiển thị</label>
                            <input id="display_name" name="display_name" type="text" maxlength="120" required>
                        </div>

                        <div class="admin-form-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" maxlength="190">
                        </div>

                        <div class="admin-form-field">
                            <label for="password">Mật khẩu</label>
                            <input id="password" name="password" type="password"
                                   minlength="<?= e((string) PASSWORD_MIN_LENGTH) ?>"
                                   required autocomplete="new-password">
                            <small>Tối thiểu <?= e((string) PASSWORD_MIN_LENGTH) ?> ký tự.</small>
                        </div>

                        <div class="admin-form-field">
                            <label for="role">Vai trò</label>
                            <select id="role" name="role">
                                <option value="admin" selected>Quản trị viên</option>
                            </select>
                        </div>

                        <div class="admin-form-field">
                            <label for="status">Trạng thái</label>
                            <select id="status" name="status">
                                <option value="active" selected>Đang hoạt động</option>
                                <option value="disabled">Đã khóa</option>
                            </select>
                        </div>

                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="admin-button admin-button--primary">Tạo tài khoản</button>
                        <a href="<?= e(app_url('admin/users.php')) ?>" class="admin-button">Hủy</a>
                    </div>
                </form>

            </section>

        <?php endif; ?>

        <!-- ========================================================
             USER LIST
             ======================================================== -->
        <section class="admin-card">

            <div class="admin-card__header">
                <div>
                    <h2>Danh sách tài khoản</h2>
                    <p><?= e((string) count($users)) ?> tài khoản trong hệ thống.</p>
                </div>
            </div>

            <?php if ($users === []): ?>

                <div class="admin-empty-state">
                    <h3>Chưa có tài khoản</h3>
                    <p>Hãy tạo tài khoản quản trị đầu tiên.</p>
                </div>

            <?php else: ?>

                <div class="admin-table-wrap">
                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Tài khoản</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th>Đăng nhập gần nhất</th>
                                <th>Cập nhật</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $userId = (string) ($user['id'] ?? '');
                            $userStatus = (string) ($user['status'] ?? 'disabled');
                            $isCurrentUser = $userId === $currentUserId;

                            $editUserUrl = app_url('admin/users.php?edit=' . rawurlencode($userId));

                            $toggleConfirm = $userStatus === 'active'
                                ? 'Khóa tài khoản này?'
                                : 'Mở khóa tài khoản này?';
                            ?>

                            <tr>
                                <!-- ACCOUNT -->
                                <td>
                                    <strong><?= e($user['display_name'] ?? '') ?></strong>
                                    <div>@<?= e($user['username'] ?? '') ?></div>

                                    <?php if (($user['email'] ?? '') !== ''): ?>
                                        <small><?= e($user['email']) ?></small>
                                    <?php endif; ?>

                                    <?php if ($isCurrentUser): ?>
                                        <span class="admin-badge">Bạn</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ROLE -->
                                <td>Quản trị viên</td>

                                <!-- STATUS -->
                                <td>
                                    <?php if ($userStatus === 'active'): ?>
                                        <span class="admin-badge admin-badge--success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge--muted">Đã khóa</span>
                                    <?php endif; ?>
                                </td>

                                <!-- LAST LOGIN -->
                                <td>
                                    <?php if (($user['last_login_at'] ?? '') !== ''): ?>
                                        <?= e(format_datetime($user['last_login_at'])) ?>
                                    <?php else: ?>
                                        Chưa đăng nhập
                                    <?php endif; ?>
                                </td>

                                <!-- UPDATED -->
                                <td><?= e(format_datetime($user['updated_at'] ?? '')) ?></td>

                                <!-- ACTIONS -->
                                <td>
                                    <div class="admin-table-actions">

                                        <a href="<?= e($editUserUrl) ?>" class="admin-button admin-button--small">Sửa</a>

                                        <?php if (!$isCurrentUser): ?>
                                            <form method="post" action="<?= e(app_url('admin/users.php')) ?>">
                                                <?= $csrfField ?>

                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= e($userId) ?>">

                                                <button type="submit"
                                                        class="admin-button admin-button--small"
                                                        data-confirm="<?= e($toggleConfirm) ?>">
                                                    <?= $userStatus === 'active' ? 'Khóa' : 'Mở khóa' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            <?php endif; ?>

        </section>

        <!-- SECURITY NOTE -->
        <section class="admin-note">
            <strong>Lưu ý bảo mật:</strong>
            mật khẩu được lưu bằng <code>password_hash()</code>;
            giao diện này không đọc hoặc hiển thị mật khẩu
            hay <code>password_hash</code> của tài khoản.
        </section>

    </div>
</main>

<script src="<?= e($scriptUrl) ?>" defer></script>

</body>
</html>