<?php foreach ($customFields as $key => $def): ?>
<?php $type = $def['type'] ?? 'text'; ?>
<?php if ($type === 'upload'): ?>
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
<?php elseif ($type === 'textarea'): ?>
    <td class="text-muted" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($attrs[$key] ?? '') ?>"><?= htmlspecialchars(function_exists('mb_strimwidth') ? mb_strimwidth($attrs[$key] ?? '—', 0, 50, '…') : (strlen($attrs[$key] ?? '—') > 50 ? substr($attrs[$key] ?? '—', 0, 50) . '…' : ($attrs[$key] ?? '—'))) ?></td>
<?php else: ?>
    <td class="text-muted"><?= htmlspecialchars($attrs[$key] ?? '—') ?></td>
<?php endif; ?>
<?php endforeach; ?>
