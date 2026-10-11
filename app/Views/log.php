<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Dolphin{anty} Monitoring</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#0b0f19',
                            800: '#111827',
                            700: '#1f2937',
                            600: '#374151',
                        },
                        brand: {
                            500: '#06b6d4',
                            600: '#0891b2',
                            400: '#22d3ee',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #111827;
        }

        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
    </style>
</head>

<body class="bg-dark-900 text-gray-100 min-h-screen flex antialiased">
    <?= view('partials/sidebar_tailwind') ?>

    <main class="flex-1 ml-20 lg:ml-24 p-6 lg:p-10">
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-1">
                    <i class="bi bi-broadcast"></i> Dolphin{anty} Monitoring Suite
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    Activity Logs
                    <span class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-medium"><?= is_array($logs) ? count($logs) : 0 ?> Recorded</span>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="clearLogs()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 font-medium text-sm hover:bg-red-500/20 transition-all">
                    <i class="bi bi-trash"></i> Clear Logs
                </button>
                <button onclick="location.reload()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </header>

        <div class="bg-dark-800 border border-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-800 text-gray-400 text-xs font-semibold uppercase tracking-wider bg-dark-900/50">
                            <th class="py-4 px-6">Timestamp</th>
                            <th class="py-4 px-6">Profile ID</th>
                            <th class="py-4 px-6">Action</th>
                            <th class="py-4 px-6">Status</th>
                            <th class="py-4 px-6">Details</th>
                            <th class="py-4 px-6 text-right">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800 text-sm">
                        <?php /** @var array<int, array<string, mixed>> $logs */ ?>
                        <?php if (!empty($logs) && is_array($logs)): ?>
                            <?php foreach ((array)$logs as $log): ?>
                                <tr class="hover:bg-dark-700/30 transition-colors">
                                    <td class="py-4 px-6 text-gray-400 font-mono text-xs"><?= esc((string)($log['created_at'] ?? '')) ?></td>
                                    <td class="py-4 px-6 font-medium text-white">
                                        <?php if (!empty($log['profile_id'])): ?>
                                            <span class="px-2.5 py-1 rounded-md bg-dark-700 text-cyan-400 font-mono text-xs border border-gray-700">#<?= esc((string)$log['profile_id']) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-600">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 font-medium text-gray-200"><?= esc((string)($log['action'] ?? '')) ?></td>
                                    <td class="py-4 px-6">
                                        <?php $status = strtolower((string)($log['status'] ?? '')); ?>
                                        <?php if ($status === 'success'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> SUCCESS
                                            </span>
                                        <?php elseif ($status === 'error'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> ERROR
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> <?= esc(strtoupper($status)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-gray-300 max-w-xs truncate" title="<?= esc((string)($log['details'] ?? '-')) ?>"><?= esc((string)($log['details'] ?? '-')) ?></td>
                                    <td class="py-4 px-6 text-right font-mono text-xs text-gray-500"><?= esc((string)($log['ip_address'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-16">
                                    <div class="w-16 h-16 rounded-2xl bg-dark-700/50 flex items-center justify-center mx-auto mb-4 text-gray-600 text-2xl border border-gray-800">
                                        <i class="bi bi-journal-text"></i>
                                    </div>
                                    <h5 class="text-gray-300 font-medium mb-1">No Activity Logs</h5>
                                    <p class="text-gray-500 text-xs">Actions such as starting/stopping profiles will appear here.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function clearLogs() {
            Swal.fire({
                title: 'Clear Activity Logs?',
                text: 'All recorded activity logs will be permanently deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#374151',
                confirmButtonText: 'Yes, clear logs',
                background: '#111827',
                color: '#f3f4f6'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('<?= base_url('api/logs/clear') ?>', {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Cleared!',
                                    text: data.message,
                                    background: '#111827',
                                    color: '#f3f4f6',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed',
                                    text: data.message || 'Could not clear logs',
                                    background: '#111827',
                                    color: '#f3f4f6'
                                });
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to connect to server',
                                background: '#111827',
                                color: '#f3f4f6'
                            });
                        });
                }
            });
        }
    </script>
</body>

</html>