<?php if (!empty($customFields)): ?>
<div class="form-group">
    <label>Details</label>
    <div class="form-row">
        <?php foreach ($customFields as $key => $def): ?>
            <div class="form-group">
                <label><?= htmlspecialchars($def['label']) ?></label>
                <?php if (($def['type'] ?? 'text') === 'select'): ?>
                    <select name="cf_<?= htmlspecialchars($key) ?>">
                        <option value="">—</option>
                        <?php foreach ($def['options'] ?? [] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= (($attrs[$key] ?? '') === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="cf_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($attrs[$key] ?? '') ?>" placeholder="<?= htmlspecialchars($def['label']) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
