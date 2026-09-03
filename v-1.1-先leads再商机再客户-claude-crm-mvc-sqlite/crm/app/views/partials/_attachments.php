<?php
/**
 * Attachment upload and list component.
 * Required variables: $relatedType ('deal'|'order'), $relatedId (int), $csrf (string)
 * Optional: $attachments (array, loaded by controller)
 */
$attachments = $attachments ?? [];
$attachmentModel = new Attachment();
if (empty($attachments)) {
    $attachments = $attachmentModel->byRelated($relatedType, $relatedId);
}
?>

<div class="card card-table p-3 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-muted mb-0">
            <i class="bi bi-paperclip me-1"></i>附件
            <span class="badge bg-secondary ms-1"><?= count($attachments) ?></span>
        </h6>
    </div>

    <!-- Upload form -->
    <form method="POST" action="<?= url("/{$relatedType}s/{$relatedId}/attachments") ?>" enctype="multipart/form-data" class="mb-3" id="attachment-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <div class="input-group">
            <input type="file" name="attachment" class="form-control form-control-sm" 
                   accept="<?= Attachment::acceptAttribute() ?>" required
                   id="attachment-input">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-upload me-1"></i>上传
            </button>
        </div>
        <div class="form-text">支持图片、PDF、Excel、CSV、压缩包，最大20MB</div>
        
        <!-- Preview area -->
        <div id="file-preview" class="mt-2" style="display:none;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark fs-4 text-primary"></i>
                <div>
                    <div class="small fw-semibold" id="preview-name"></div>
                    <div class="small text-muted" id="preview-size"></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="clearPreview()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </form>

    <!-- Attachments list -->
    <?php if (empty($attachments)): ?>
        <p class="text-muted small mb-0">暂无附件。</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th width="5%">类型</th>
                        <th width="40%">文件名</th>
                        <th width="10%">大小</th>
                        <th width="15%">上传者</th>
                        <th width="15%">上传时间</th>
                        <th width="15%" class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attachments as $att): ?>
                        <tr>
                            <td>
                                <i class="bi <?= Attachment::fileIcon($att['mime_type']) ?> text-primary"></i>
                            </td>
                            <td>
                                <?php if (Attachment::isImage($att['mime_type'])): ?>
                                    <a href="<?= Attachment::uploadUrl() . '/' . $att['filename'] ?>" 
                                       target="_blank" class="text-decoration-none"
                                       data-bs-toggle="tooltip" data-bs-html="true"
                                       title="<img src='<?= Attachment::uploadUrl() . '/' . $att['filename'] ?>' style='max-width:200px;max-height:150px;'>">
                                        <?= e($att['original_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= Attachment::uploadUrl() . '/' . $att['filename'] ?>" 
                                       target="_blank" class="text-decoration-none">
                                        <?= e($att['original_name']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= Attachment::formatSize($att['file_size']) ?></td>
                            <td class="small"><?= e($att['uploader_name'] ?? '—') ?></td>
                            <td class="small text-muted"><?= formatDate($att['created_at'], 'm-d H:i') ?></td>
                            <td class="text-end">
                                <a href="<?= Attachment::uploadUrl() . '/' . $att['filename'] ?>" 
                                   download="<?= e($att['original_name']) ?>"
                                   class="btn btn-sm btn-outline-primary py-0 px-1" title="下载">
                                    <i class="bi bi-download"></i>
                                </a>
                                <?php if (Attachment::isImage($att['mime_type'])): ?>
                                    <a href="<?= Attachment::uploadUrl() . '/' . $att['filename'] ?>" 
                                       target="_blank"
                                       class="btn btn-sm btn-outline-info py-0 px-1" title="预览">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                <?php endif; ?>
                                <form method="POST" action="<?= url("/{$relatedType}s/{$relatedId}/attachments/{$att['id']}/delete") ?>" 
                                      class="d-inline" onsubmit="return confirm('确定删除此附件？');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="删除">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// File preview
const attachmentInput = document.getElementById('attachment-input');
const previewArea = document.getElementById('file-preview');
const previewName = document.getElementById('preview-name');
const previewSize = document.getElementById('preview-size');

if (attachmentInput) {
    attachmentInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const file = this.files[0];
            previewName.textContent = file.name;
            previewSize.textContent = formatFileSize(file.size);
            previewArea.style.display = 'block';
        } else {
            previewArea.style.display = 'none';
        }
    });
}

function clearPreview() {
    attachmentInput.value = '';
    previewArea.style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Initialize tooltips for image previews
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) {
        return new bootstrap.Tooltip(el, {
            html: true,
            sanitize: false
        });
    });
});
</script>
