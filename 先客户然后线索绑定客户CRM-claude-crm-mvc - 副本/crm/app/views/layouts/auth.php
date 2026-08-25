<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-wrap">
        <div class="auth-brand">
            <i class="bi bi-diagram-3-fill"></i> <?= e(APP_NAME) ?>
        </div>
        <div class="card auth-card shadow-sm">
            <div class="card-body p-4">
                <?= $content ?>
            </div>
        </div>
    </div>
</body>
</html>
