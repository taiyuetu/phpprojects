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
                <?php elseif (($def['type'] ?? 'text') === 'upload'): ?>
                    <?php $existing = $attrs[$key] ?? ''; ?>
                    <?php if ($existing !== ''): ?>
                        <div class="cf-upload-preview" style="margin-bottom:6px;">
                            <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $existing)): ?>
                                <img src="<?= Router::url('/' . $existing) ?>" alt="<?= htmlspecialchars($def['label']) ?>" style="max-width:120px;max-height:80px;border-radius:4px;border:1px solid #ddd;">
                            <?php else: ?>
                                <a href="<?= Router::url('/' . $existing) ?>" target="_blank">📎 <?= htmlspecialchars(basename($existing)) ?></a>
                            <?php endif; ?>
                            <label style="display:inline;margin-left:8px;font-weight:normal;font-size:0.85em;color:#c00;">
                                <input type="checkbox" name="cf_<?= htmlspecialchars($key) ?>_delete" value="1"> Remove
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="cf_<?= htmlspecialchars($key) ?>">
                <?php else: ?>
                    <input type="text" name="cf_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($attrs[$key] ?? '') ?>" placeholder="<?= htmlspecialchars($def['label']) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<!-- Ensure parent form has multipart encoding for file uploads -->
<script>
(function(){
    var hasUpload = document.querySelector('input[type="file"][name^="cf_"]');
    if (hasUpload) {
        var form = hasUpload.closest('form');
        if (form) form.enctype = 'multipart/form-data';
    }
})();
</script>
