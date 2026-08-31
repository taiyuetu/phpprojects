<?php
namespace App\Core;

/**
 * Base Controller.
 * Handles view rendering (inside the shared layout), redirects,
 * flash messages and simple input access — everything a child
 * controller needs so it can stay focused on business logic.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        extract($data);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            die("View not found: {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout) {
            require __DIR__ . '/../Views/' . $layout . '.php';
        } else {
            echo $content;
        }
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . Router::url($path));
        exit;
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function all(): array
    {
        return $_POST;
    }

    /** Collect custom-field filter params (cf_*) from the request. */
    protected function customFieldFilters(array $customFields): array
    {
        $filters = [];
        foreach ($customFields as $key => $def) {
            if (!empty($def['filterable'])) {
                $filters['cf_' . $key] = trim($this->input('cf_' . $key, ''));
            }
        }
        return $filters;
    }

    /** Collect custom-field values (cf_*) from the request into an assoc array.
     *  Upload-type fields are skipped here — they are handled by HasCustomFields::handleCustomFieldUploads(). */
    protected function customFieldValues(array $customFields): array
    {
        $attrs = [];
        foreach ($customFields as $key => $def) {
            // Upload fields are processed separately via handleCustomFieldUploads()
            if (($def['type'] ?? 'text') === 'upload') continue;
            $v = trim($this->input('cf_' . $key, ''));
            if ($v !== '') $attrs[$key] = $v;
        }
        return $attrs;
    }

    /**
     * Validate custom-field values against their definitions.
     * Returns an array of error messages (empty = valid).
     *
     * @param string $modelClass  Fully-qualified model class (e.g. Sale::class).
     * @param array  $values      Collected custom-field values from customFieldValues().
     * @param array  $existing    Existing custom-field values (for update; used for upload required checks).
     * @return string[]           Error messages.
     */
    protected function validateCustomFields(string $modelClass, array $values, array $existing = []): array
    {
        // Merge existing for upload-required checks
        $merged = array_merge($existing, $values);

        $errors = [];
        $customFields = $modelClass::customFields();

        foreach ($customFields as $key => $def) {
            $type = $def['type'] ?? 'text';
            $label = $def['label'] ?? $key;

            // Required check (skip upload fields — checked below)
            if (!empty($def['required']) && $type !== 'upload') {
                $v = $merged[$key] ?? '';
                if (is_string($v) && trim($v) === '') {
                    $errors[] = "{$label} is required.";
                }
            }

            // Date format check
            if ($type === 'date' && !empty($merged[$key])) {
                $v = trim((string) $merged[$key]);
                if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                    $errors[] = "{$label} must be a valid date (YYYY-MM-DD).";
                }
            }

            // Select: must be one of the options
            if ($type === 'select' && !empty($merged[$key]) && !empty($def['options'])) {
                if (!in_array($merged[$key], $def['options'], true)) {
                    $errors[] = "{$label} must be one of: " . implode(', ', $def['options']) . '.';
                }
            }
        }

        // Upload required check
        foreach ($customFields as $key => $def) {
            if (($def['type'] ?? 'text') === 'upload' && !empty($def['required'])) {
                $hasExisting = !empty($existing[$key]);
                $hasUpload = !empty($_FILES['cf_' . $key]) && $_FILES['cf_' . $key]['error'] === UPLOAD_ERR_OK;
                $deleting = !empty($_POST['cf_' . $key . '_delete']);
                if (!$hasExisting && !$hasUpload && !$deleting) {
                    $errors[] = ($def['label'] ?? $key) . ' is required.';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate custom fields and redirect back with errors on failure.
     * Call this from store()/update() before saving.
     *
     * @param string $modelClass  Fully-qualified model class.
     * @param array  $values      Collected custom-field values.
     * @param string $redirect    URL to redirect to on failure.
     * @param array  $existing    Existing custom-field values (for update).
     */
    protected function validateCustomFieldsOrFail(string $modelClass, array $values, string $redirect, array $existing = []): void
    {
        $errors = $this->validateCustomFields($modelClass, $values, $existing);
        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect($redirect);
        }
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /** CSRF token helpers — every state-changing form should include this. */
    protected function csrfField(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
        }
    }
}
