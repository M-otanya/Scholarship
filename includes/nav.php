<?php
require_once __DIR__ . '/auth.php';

// Retrieve the authenticated user.
$nsUser = auth_user();

// Application base URL.
$nsBaseUrl = auth_base();

// Determine account role.
$nsRole = $nsUser
    ? strtolower(trim((string) $nsUser['role_name']))
    : '';

$nsIsAdmin = in_array(
    $nsRole,
    ['administrator', 'super-admin'],
    true
);

$nsIsSuper = $nsRole === 'super-admin';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">

        <!-- Brand -->
        <a class="navbar-brand"
           href="<?= h($nsBaseUrl) ?>/index.php">
            Pathways to Scholarships
        </a>

        <!-- Mobile Menu -->
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavigation"
            aria-controls="mainNavigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse"
             id="mainNavigation">

            <!-- Public Navigation -->
            <div class="navbar-nav me-auto">

                <a class="nav-link"
                   href="<?= h($nsBaseUrl) ?>/index.php">
                    Home
                </a>

                <a class="nav-link"
                   href="<?= h($nsBaseUrl) ?>/scholarships.php">
                    Scholarships
                </a>

                <a class="nav-link"
                   href="<?= h($nsBaseUrl) ?>/scholarship_facts.php">
                    Scholarship Facts
                </a>

                <a class="nav-link"
                   href="<?= h($nsBaseUrl) ?>/about.php">
                    About
                </a>

                <a class="nav-link"
                   href="<?= h($nsBaseUrl) ?>/faq.php">
                    FAQ
                </a>

                <a class="nav-link"
                   href="<?= h($nsBaseUrl) ?>/contact.php">
                    Contact
                </a>

                <!-- Administrator Navigation -->
                <?php if ($nsIsAdmin): ?>

                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Admin
                        </a>

                        <ul class="dropdown-menu">

                            <li>
                                <a class="dropdown-item"
                                   href="<?= h($nsBaseUrl) ?>/admin/index.php">
                                    Admin Dashboard
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="<?= h($nsBaseUrl) ?>/admin/scholarships.php">
                                    Manage Scholarships
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="<?= h($nsBaseUrl) ?>/admin/import.php">
                                    Upload Scholarships
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="<?= h($nsBaseUrl) ?>/admin/import_history.php">
                                    Import History
                                </a>
                            </li>

                            <!-- Super-admin Only -->
                            <?php if ($nsIsSuper): ?>

                                <li><hr class="dropdown-divider"></li>

                                <li>
                                    <a class="dropdown-item"
                                       href="<?= h($nsBaseUrl) ?>/admin/users.php">
                                        User Management
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item"
                                       href="<?= h($nsBaseUrl) ?>/admin/reference_data.php">
                                        Reference Data
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item"
                                       href="<?= h($nsBaseUrl) ?>/admin/audit_logs.php">
                                        Audit Logs
                                    </a>
                                </li>

                            <?php endif; ?>

                        </ul>

                    </div>

                <?php endif; ?>

            </div>


            <!-- Account Navigation -->
            <div class="navbar-nav">

                <?php if ($nsUser): ?>

                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Account
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end">

                            <li>
                                <span class="dropdown-item-text small text-muted">
                                    <?= h($nsUser['email']) ?>
                                </span>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <!-- User Profile -->
                            <li>
                                <a class="dropdown-item"
                                   href="<?= h($nsBaseUrl) ?>/profile.php">
                                    My Profile
                                </a>
                            </li>

                            <!-- Change Password -->
                            <li>
                                <a class="dropdown-item"
                                   href="<?= h($nsBaseUrl) ?>/change_password.php">
                                    Change Password
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <!-- Secure Logout -->
                            <li>
                                <form
                                    method="POST"
                                    action="<?= h($nsBaseUrl) ?>/logout.php"
                                    class="m-0"
                                >
                                    <?= csrf_field() ?>

                                    <button
                                        type="submit"
                                        class="dropdown-item"
                                    >
                                        Logout
                                    </button>
                                </form>
                            </li>

                        </ul>

                    </div>

                <?php else: ?>

                    <!-- Guest Navigation -->
                    <a class="nav-link"
                       href="<?= h($nsBaseUrl) ?>/login.php">
                        Login
                    </a>

                    <a class="nav-link"
                       href="<?= h($nsBaseUrl) ?>/register.php">
                        Register
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
