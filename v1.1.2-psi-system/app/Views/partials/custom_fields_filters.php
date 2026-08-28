<?php foreach ($customFields as $key => $def): ?>
    <?php if (empty($def['filterable'])) continue; ?>
    <?php $fkey = 'cf_' . $key; $cur = $filters[$fkey] ?? ''; ?>
    <?php if (($def['type'] ?? 'text') === 'select'): ?>
        <select name="<?= htmlspecialchars($fkey) ?>">
            <option value=""><?= htmlspecialchars($def['label']) ?></option>
            <?php foreach ($def['options'] ?? [] as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= ($cur === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="text" name="<?= htmlspecialchars($fkey) ?>" placeholder="<?= htmlspecialchars($def['label']) ?>" value="<?= htmlspecialchars($cur) ?>">
    <?php endif; ?>
<?php endforeach; ?>
