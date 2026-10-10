<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dolphin{anty} Monitoring' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #111111;
            color: #e1e1e1;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            width: 70px;
            height: 100vh;
            background-color: #1a1a1a;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 20px;
            border-right: 1px solid #333;
        }
        .sidebar-item {
            margin-bottom: 25px;
            color: #888;
            font-size: 24px;
            cursor: pointer;
            transition: color 0.2s;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
        }
        .sidebar-item:hover, .sidebar-item.active {
            color: #00a8ff;
            background-color: rgba(0, 168, 255, 0.1);
        }
        .main-content {
            margin-left: 70px;
            padding: 30px 40px;
        }
        .card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            color: #e1e1e1;
            border-radius: 12px;
        }
        .card-header {
            background-color: #1a1a1a;
            border-bottom: 1px solid #333;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 16px;
        }
        .card-body {
            padding: 25px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .table {
            color: #e1e1e1;
            border-color: #333;
        }
        .table thead th {
            border-bottom: 2px solid #333;
            color: #888;
            font-weight: 500;
        }
        .table td {
            border-bottom: 1px solid #222;
            vertical-align: middle;
        }
        .btn-primary {
            background-color: #00a8ff;
            border: none;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: #0088cc;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?= view('partials/sidebar_classic') ?>

    <div class="main-content">
        <?= $content ?? '' ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
