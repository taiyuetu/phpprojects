<?php foreach ($customFields as $key => $def): ?>
    <?php if (empty($def['filterable'])) continue; ?>
    <?php $fkey = 'cf_' . $key; $cur = $filters[$fkey] ?? ''; $type = $def['type'] ?? 'text'; ?>
    <?php if ($type === 'select'): ?>
        <select name="<?= htmlspecialchars($fkey) ?>">
            <option value=""><?= htmlspecialchars($def['label']) ?></option>
            <?php foreach ($def['options'] ?? [] as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= ($cur === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($type === 'date'): ?>
        <input type="date" name="<?= htmlspecialchars($fkey) ?>" value="<?= htmlspecialchars($cur) ?>" title="<?= htmlspecialchars($def['label']) ?>">
    <?php elseif ($type === 'textarea'): ?>
        <input type="text" name="<?= htmlspecialchars($fkey) ?>" placeholder="<?= htmlspecialchars($def['label']) ?>" value="<?= htmlspecialchars($cur) ?>">
    <?php else: ?>
        <input type="text" name="<?= htmlspecialchars($fkey) ?>" placeholder="<?= htmlspecialchars($def['label']) ?>" value="<?= htmlspecialchars($cur) ?>">
    <?php endif; ?>
<?php endforeach; ?>
