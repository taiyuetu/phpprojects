<?php
/**
 * Expects $deleteUrl and $this (controller) in scope for csrfField().
 * Usage: $deleteUrl = Router::url('/categories/5/delete'); include partial.
 */
use App\Core\Router;
?>
<form method="post" action="<?= $deleteUrl ?>" onsubmit="return confirm('Are you sure you want to delete this? This cannot be undone.');" style="display:inline;">
    <?= $this->csrfField() ?>
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
