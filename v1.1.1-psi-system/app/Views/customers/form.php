<?php
use App\Core\Router;
$attrs = json_decode($customer['attributes'] ?? '{}', true) ?: [];
?>
<div class="card" style="max-width:600px;">
    <h2><?= $customer ? 'Edit Customer' : 'Add Customer' ?></h2>
    <form method="post" action="<?= $customer ? Router::url('/customers/' . $customer['id']) : Router::url('/customers') ?>">
        <?= $this->csrfField() ?>
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($customer['name'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="address" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
        </div>
        <?php include __DIR__ . '/../partials/custom_fields_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="<?= Router::url('/customers') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
