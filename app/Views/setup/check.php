<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup Check - BayzBot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .setup-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            padding: 40px;
            margin: 20px;
        }

        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .setup-header h1 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .setup-header p {
            color: #666;
            font-size: 14px;
        }

        .progress-section {
            margin-bottom: 30px;
        }

        .progress {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 10px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }

        .check-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            background-color: #f8f9fa;
            border-left: 4px solid #ddd;
            animation: slideIn 0.3s ease;
        }

        .check-item.pass {
            border-left-color: #28a745;
            background-color: #f0f7f4;
        }

        .check-item.fail {
            border-left-color: #dc3545;
            background-color: #fdf5f5;
        }

        .check-item.warning {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }

        .check-item.loading {
            border-left-color: #667eea;
            background-color: #f0f4ff;
        }

        .check-icon {
            min-width: 32px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 16px;
        }

        .check-item.pass .check-icon {
            background-color: #28a745;
        }

        .check-item.fail .check-icon {
            background-color: #dc3545;
        }

        .check-item.warning .check-icon {
            background-color: #ffc107;
            color: #333;
        }

        .check-item.loading .check-icon {
            background-color: #667eea;
        }

        .spinner-border-sm {
            width: 16px;
            height: 16px;
            border-width: 2px;
        }

        .check-content {
            flex: 1;
        }

        .check-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .check-message {
            font-size: 12px;
            color: #666;
        }

        .check-item.fail .check-message {
            color: #dc3545;
        }

        .check-item.warning .check-message {
            color: #ffc107;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: white;
            transition: transform 0.2s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .status-badge.pass {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.fail {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-badge.warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #0c5aa0;
        }
    </style>
</head>

<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1><i class="bi bi-gear"></i> System Setup Check</h1>
            <p>Verifying your system configuration and dependencies</p>
        </div>

        <div class="info-box">
            <i class="bi bi-info-circle"></i>
            This setup wizard will verify all dependencies and configure your system automatically.
        </div>

        <div class="progress-section">
            <div class="progress">
                <div class="progress-bar" id="progressBar" style="width: 0%"></div>
            </div>
            <div class="progress-text">
                <span>System Check Progress</span>
                <span id="progressText">0 / <?= $totalChecks ?></span>
            </div>
        </div>

        <div id="checksContainer">
            <!-- Checks will be loaded here -->
        </div>

        <div class="action-buttons">
            <button id="retryBtn" class="btn-secondary" onclick="location.reload()" style="display: none;">
                <i class="bi bi-arrow-clockwise"></i> Retry
            </button>
            <button id="migrateBtn" class="btn-primary btn-disabled" onclick="runMigrations()" style="display: none;">
                <i class="bi bi-database"></i> Run Migrations
            </button>
            <button id="continueBtn" class="btn-primary" onclick="continueToLogin()" style="display: none;">
                <i class="bi bi-check-lg"></i> Continue to Login
            </button>
        </div>
    </div>

    <script>
        const baseUrl = '<?= base_url() ?>';
        let checks = [];
        let checkResults = {};
        let completedChecks = 0;

        async function runSetupChecks() {
            try {
                const response = await fetch(`${baseUrl}setup/runChecks`);
                checkResults = await response.json();

                displayChecks();
                updateProgress();
                handleResults();
            } catch (error) {
                console.error('Error running checks:', error);
                showError('Failed to run system checks');
            }
        }

        function displayChecks() {
            const container = document.getElementById('checksContainer');
            container.innerHTML = '';

            Object.entries(checkResults).forEach(([key, check], index) => {
                const checkEl = document.createElement('div');
                checkEl.className = `check-item ${check.status}`;
                checkEl.style.animationDelay = `${index * 0.1}s`;

                const icon = getCheckIcon(check.status);
                checkEl.innerHTML = `
                    <div class="check-icon">${icon}</div>
                    <div class="check-content">
                        <div class="check-name">
                            ${check.name}
                            <span class="status-badge ${check.status}">${check.current}</span>
                        </div>
                        <div class="check-message">${check.message}</div>
                    </div>
                `;

                container.appendChild(checkEl);
                completedChecks++;
            });
        }

        function getCheckIcon(status) {
            switch (status) {
                case 'pass':
                    return '<i class="bi bi-check-lg"></i>';
                case 'fail':
                    return '<i class="bi bi-x-lg"></i>';
                case 'warning':
                    return '<i class="bi bi-exclamation-lg"></i>';
                default:
                    return '<i class="bi bi-hourglass-split"></i>';
            }
        }

        function updateProgress() {
            const total = Object.keys(checkResults).length;
            const progress = (completedChecks / total) * 100;

            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('progressText').textContent = `${completedChecks} / ${total}`;
        }

        function handleResults() {
            const allPass = Object.values(checkResults).every(c => c.status === 'pass');
            const hasWarning = Object.values(checkResults).some(c => c.status === 'warning');
            const hasFail = Object.values(checkResults).some(c => c.status === 'fail');

            const migrateBtn = document.getElementById('migrateBtn');
            const continueBtn = document.getElementById('continueBtn');
            const retryBtn = document.getElementById('retryBtn');

            if (allPass) {
                continueBtn.style.display = 'inline-block';
                migrateBtn.style.display = 'none';
            } else if (hasWarning && !hasFail) {
                migrateBtn.style.display = 'inline-block';
                migrateBtn.classList.remove('btn-disabled');
            } else if (hasFail) {
                retryBtn.style.display = 'inline-block';
                showError('System check failed. Please fix the issues and retry.');
            }
        }

        async function runMigrations() {
            const btn = document.getElementById('migrateBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split spinner-border-sm"></i> Running Migrations...';
            btn.classList.add('btn-disabled');

            try {
                const response = await fetch(`${baseUrl}setup/runMigrations`, {
                    method: 'POST'
                });
                const result = await response.json();

                if (result.status === 'success') {
                    // Re-run checks to verify migrations
                    completedChecks = 0;
                    await runSetupChecks();
                } else {
                    showError('Migration failed: ' + result.message);
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-disabled');
                }
            } catch (error) {
                showError('Error running migrations: ' + error.message);
                btn.innerHTML = originalText;
                btn.classList.remove('btn-disabled');
            }
        }

        function continueToLogin() {
            window.location.href = `${baseUrl}login`;
        }

        function showError(message) {
            const container = document.getElementById('checksContainer');
            const errorEl = document.createElement('div');
            errorEl.className = 'alert alert-danger';
            errorEl.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${message}`;
            container.prepend(errorEl);
        }

        // Run checks on page load
        document.addEventListener('DOMContentLoaded', runSetupChecks);
    </script>
</body>

</html>