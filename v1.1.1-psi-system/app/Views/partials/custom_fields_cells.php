<?php foreach ($customFields as $key => $def): ?><td class="text-muted"><?= htmlspecialchars($attrs[$key] ?? '—') ?></td><?php endforeach; ?>
