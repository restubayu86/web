<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dolphin{anty} Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #111111;
            color: #e1e1e1;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .form-control {
            background-color: #222;
            border: 1px solid #333;
            color: #e1e1e1;
        }

        .form-control:focus {
            background-color: #222;
            border-color: #00a8ff;
            color: #e1e1e1;
            box-shadow: none;
        }

        .btn-login {
            background-color: #00a8ff;
            border: none;
            color: white;
            padding: 10px;
            font-weight: 500;
        }

        .btn-login:hover {
            background-color: #0088cc;
            color: white;
        }

        .logo {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
        }

        .logo span {
            color: #00a8ff;
        }

        .setup-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .setup-modal.active {
            display: flex;
        }

        .setup-content {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 30px;
            width: 100%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .setup-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin-bottom: 10px;
            background-color: #222;
            border-radius: 4px;
            border-left: 3px solid #333;
        }

        .setup-item.pass {
            border-left-color: #28a745;
            background-color: #1a2e1a;
        }

        .setup-item.fail {
            border-left-color: #dc3545;
            background-color: #2e1a1a;
        }

        .setup-item.loading {
            border-left-color: #00a8ff;
            background-color: #1a2e3a;
        }

        .setup-icon {
            width: 24px;
            height: 24px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .setup-icon.pass i {
            color: #28a745;
        }

        .setup-icon.fail i {
            color: #dc3545;
        }

        .setup-icon.loading {
            animation: spin 1s linear infinite;
        }

        .setup-icon.loading i {
            color: #00a8ff;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .setup-text {
            flex-grow: 1;
        }

        .setup-name {
            font-weight: 500;
            margin-bottom: 3px;
        }

        .setup-status {
            font-size: 12px;
            color: #999;
        }

        .progress-bar-animated {
            background-color: #00a8ff;
        }
    </style>
</head>

<body>
    <!-- Setup Modal -->
    <div class="setup-modal" id="setupModal">
        <div class="setup-content">
            <h4 style="margin-bottom: 20px;">System Setup Check</h4>
            <p style="font-size: 14px; color: #999; margin-bottom: 20px;">Verifying system dependencies and configuring database...</p>

            <div id="setupChecks"></div>

            <div class="progress" style="margin-top: 20px; display: none;" id="setupProgress">
                <div class="progress-bar progress-bar-animated" role="progressbar" style="width: 0%"></div>
            </div>

            <div id="setupMessage" style="margin-top: 15px; font-size: 13px; color: #00a8ff;"></div>
        </div>
    </div>

    <!-- Login Form -->
    <div class="login-card" id="loginCard">
        <div class="logo">Dolphin<span>{anty}</span> Monitoring</div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($needs_setup)): ?>
            <div class="alert alert-warning" style="font-size: 13px;">
                Belum ada user admin. Jalankan <code>php spark db:seed AdminSeeder</code> di terminal, lalu login.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('login') ?>" id="loginForm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-login w-100">Login</button>
        </form>
    </div>

    <script>
        // Pengecekan setup hanya dijalankan pada instalasi baru (belum ada user).
        // Setelah admin ada, endpoint setup dikunci dan form login langsung tampil.
        <?php if (!empty($needs_setup)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            checkSystemSetup();
        });
        <?php endif; ?>

        function checkSystemSetup() {
            const setupModal = document.getElementById('setupModal');
            const loginCard = document.getElementById('loginCard');
            const checksContainer = document.getElementById('setupChecks');
            const message = document.getElementById('setupMessage');

            setupModal.classList.add('active');
            loginCard.style.display = 'none';
            checksContainer.innerHTML = '';
            message.textContent = 'Checking system...';

            fetch('<?= base_url('login/checkSetup') ?>')
                .then(response => {
                    const type = response.headers.get('content-type') || '';
                    if (!response.ok || !type.includes('application/json')) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // Display all checks
                    Object.values(data.checks).forEach(check => {
                        const checkEl = createCheckElement(check);
                        checksContainer.appendChild(checkEl);
                    });

                    // If all complete, show login form
                    if (data.all_complete) {
                        message.textContent = '✓ Setup complete! Redirecting...';
                        setTimeout(() => {
                            setupModal.classList.remove('active');
                            loginCard.style.display = 'block';
                        }, 1500);
                    } else {
                        message.textContent = '✗ Setup incomplete. Please check the errors above.';
                    }
                })
                .catch(error => {
                    console.error('Setup check error:', error);
                    message.textContent = '✗ Error checking setup (' + error.message + '). Please refresh the page.';
                });
        }

        function createCheckElement(check) {
            const div = document.createElement('div');
            div.className = `setup-item ${check.status}`;

            let icon = 'bi-hourglass';
            if (check.status === 'pass') icon = 'bi-check-circle';
            if (check.status === 'fail') icon = 'bi-x-circle';

            div.innerHTML = `
                <div class="setup-icon ${check.status}">
                    <i class="bi ${icon}"></i>
                </div>
                <div class="setup-text">
                    <div class="setup-name">${check.name}</div>
                    <div class="setup-status">${check.current || check.required}</div>
                </div>
            `;

            return div;
        }
    </script>
</body>

</html>