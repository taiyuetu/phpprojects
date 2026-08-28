<?php foreach ($customFields as $key => $def): ?>
<?php if (($def['type'] ?? 'text') === 'upload'): ?>
    <td>
        <?php $val = $attrs[$key] ?? ''; ?>
        <?php if ($val !== ''): ?>
            <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $val)): ?>
                <img src="<?= Router::url('/' . $val) ?>" alt="" style="max-width:60px;max-height:40px;border-radius:3px;vertical-align:middle;">
            <?php else: ?>
                <a href="<?= Router::url('/' . $val) ?>" target="_blank">📎</a>
            <?php endif; ?>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
<?php else: ?>
    <td class="text-muted"><?= htmlspecialchars($attrs[$key] ?? '—') ?></td>
<?php endif; ?>
<?php endforeach; ?>
