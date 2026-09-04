<?php
/**
 * Copyright-header coverage test.
 *
 * The notice is a single line per file, so this is really a "did anyone add a
 * new file and forget to stamp it?" guard — cheap to run, and it keeps the
 * notice from silently rotting into "most files say this".
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function noticeMarker(): string
{
    return 'Copyright (c) ' . APP_COPY_YEAR . ' ' . APP_AUTHOR;
}

/** @return array<int,string> repo-relative paths that should carry the notice */
function stampableFiles(): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if (str_contains($path, '/.git/')) {
            continue;                        // git internals are not ours
        }
        $ext = strtolower($file->getExtension());
        if (in_array($ext, ['php', 'sql', 'css', 'md'], true)
            || in_array($file->getFilename(), ['.htaccess', '.env.example'], true)) {
            $out[] = str_replace('\\', '/', substr($path, strlen(BASE_PATH) + 1));
        }
    }
    sort($out);
    return $out;
}

function test_every_source_file_carries_the_copyright_notice(): void
{
    $files = stampableFiles();
    assertTrue(count($files) > 60, 'the scan actually found the project files (got ' . count($files) . ')');

    $missing = [];
    foreach ($files as $relative) {
        $src = file_get_contents(BASE_PATH . '/' . $relative);
        if (trim((string) $src) === '') {
            continue;                        // nothing to attach a notice to
        }
        if (!str_contains((string) $src, noticeMarker())) {
            $missing[] = $relative;
        }
    }
    assertEquals([], $missing, 'files without a copyright notice: ' . implode(', ', $missing));
}

function test_the_notice_names_the_product_and_the_rights(): void
{
    $head = (string) file_get_contents(BASE_PATH . '/app/core/helpers.php');
    assertContains('叁程 CRM (Triphase CRM)', $head, 'the notice carries the product name');
    assertContains('保留所有权利', $head, 'the notice states the rights reservation');
}

function test_the_copyright_line_reaches_the_rendered_pages(): void
{
    // Constants must agree with each other, otherwise the UI and the file
    // headers would drift into two different copyright statements.
    assertContains(APP_NAME, APP_COPYRIGHT, 'canonical notice mentions the product');
    assertContains(APP_COPY_YEAR, APP_COPYRIGHT_UI, 'UI notice carries the year');
    assertContains(APP_AUTHOR, APP_COPYRIGHT, 'canonical notice names the author');

    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1];
    // The layout builds nav active-states from REQUEST_URI; supply it like a real request.
    $_SERVER['REQUEST_URI'] = '/settings';
    $layout = renderFileToString(APP_PATH . '/views/layouts/main.php', ['content' => '']);
    assertContains(appCopyright(), $layout, 'sidebar shows the copyright line');
    assertContains('<meta name="copyright"', $layout, 'page head carries a copyright meta tag');
    assertContains('<meta name="author"', $layout, 'page head carries an author meta tag');

    $auth = renderFileToString(APP_PATH . '/views/layouts/auth.php', ['content' => '']);
    assertContains(APP_RIGHTS, $auth, 'login page states the rights reservation');
}

function renderFileToString(string $file, array $vars): string
{
    extract($vars);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

runCase();
