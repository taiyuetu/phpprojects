<?php

class Attachment extends Model
{
    protected string $table = 'attachments';

    /** Allowed MIME types and their extensions. */
    private const ALLOWED_TYPES = [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/gif'        => 'gif',
        'image/webp'       => 'webp',
        'application/pdf'  => 'pdf',
        'application/vnd.ms-excel'                                                              => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'                     => 'xlsx',
        'application/vnd.oasis.opendocument.spreadsheet'                                        => 'ods',
        'text/csv'          => 'csv',
        'application/zip'   => 'zip',
        'application/x-rar-compressed'                                                          => 'rar',
    ];

    /** Extension to MIME type mapping (for fallback detection). */
    private const EXT_TO_MIME = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'csv'  => 'text/csv',
        'zip'  => 'application/zip',
        'rar'  => 'application/x-rar-compressed',
    ];

    /** Max file size: 20 MB. */
    private const MAX_SIZE = 20 * 1024 * 1024;

    /**
     * Get upload directory (absolute path).
     * Creates the directory if it doesn't exist.
     */
    public static function uploadDir(): string
    {
        $dir = BASE_PATH . '/public/uploads/attachments';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Get the web-accessible base URL for attachments.
     */
    public static function uploadUrl(): string
    {
        return url('/uploads/attachments');
    }

    /**
     * Detect MIME type from file content or extension.
     * Uses finfo when available, falls back to extension-based detection.
     */
    private function detectMimeType(string $filePath, string $originalName): string
    {
        // Try finfo first if available
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($filePath);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        // Fallback: detect from file extension
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return self::EXT_TO_MIME[$ext] ?? 'application/octet-stream';
    }

    /**
     * Process and store an uploaded file.
     *
     * @param array $file $_FILES entry
     * @param string $relatedType 'deal' or 'order'
     * @param int $relatedId The deal/order ID
     * @param int $userId Uploader user ID
     * @return array{success: bool, error?: string, attachment?: array}
     */
    public function upload(array $file, string $relatedType, int $relatedId, int $userId): array
    {
        // Validate upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => '文件超过了服务器限制（最大2MB）。',
                UPLOAD_ERR_FORM_SIZE  => '文件超过了表单限制。',
                UPLOAD_ERR_PARTIAL    => '文件只有部分被上传。',
                UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录缺失。',
                UPLOAD_ERR_CANT_WRITE => '文件写入磁盘失败。',
                UPLOAD_ERR_EXTENSION  => 'PHP扩展停止了文件上传。',
            ];
            $msg = $errorMessages[$file['error']] ?? '文件上传失败，错误码：' . $file['error'];
            return ['success' => false, 'error' => $msg];
        }

        // Validate file size
        if ($file['size'] > self::MAX_SIZE) {
            return ['success' => false, 'error' => '文件大小不能超过20MB。'];
        }

        // Validate MIME type (from content or extension)
        $mimeType = $this->detectMimeType($file['tmp_name'], $file['name']);

        if (!isset(self::ALLOWED_TYPES[$mimeType])) {
            return ['success' => false, 'error' => '不支持的文件类型。允许：图片、PDF、Excel、CSV、压缩包。'];
        }

        // Generate unique filename
        $ext = self::ALLOWED_TYPES[$mimeType];
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = self::uploadDir() . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'error' => '文件保存失败，请检查服务器权限。'];
        }

        // Save to database
        $data = [
            'related_type'  => $relatedType,
            'related_id'    => $relatedId,
            'filename'      => $filename,
            'original_name' => $file['name'],
            'mime_type'     => $mimeType,
            'file_size'     => $file['size'],
            'uploaded_by'   => $userId,
        ];

        $id = $this->create($data);
        $attachment = $this->find($id);

        return ['success' => true, 'attachment' => $attachment];
    }

    /**
     * Get all attachments for a related entity.
     */
    public function byRelated(string $relatedType, int $relatedId): array
    {
        return $this->db()->query(
            "SELECT a.*, u.name AS uploader_name
             FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.related_type = :type AND a.related_id = :id
             ORDER BY a.created_at DESC"
        )->bind(':type', $relatedType)->bind(':id', $relatedId)->resultSet();
    }

    /**
     * Copy all attachments from one related entity to another.
     * Used when converting a deal to an order.
     *
     * @param string $fromType Source type (e.g. 'deal')
     * @param int $fromId Source ID
     * @param string $toType Target type (e.g. 'order')
     * @param int $toId Target ID
     * @param int $userId User performing the copy
     * @return int Number of attachments copied
     */
    public function copyTo(string $fromType, int $fromId, string $toType, int $toId, int $userId): int
    {
        $attachments = $this->byRelated($fromType, $fromId);
        $count = 0;

        foreach ($attachments as $att) {
            $this->create([
                'related_type'  => $toType,
                'related_id'    => $toId,
                'filename'      => $att['filename'],
                'original_name' => $att['original_name'],
                'mime_type'     => $att['mime_type'],
                'file_size'     => $att['file_size'],
                'uploaded_by'   => $userId,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Delete an attachment (removes file and database record).
     */
    public function remove(int $id): bool
    {
        $attachment = $this->find($id);
        if (!$attachment) {
            return false;
        }

        // Delete physical file
        $filePath = self::uploadDir() . '/' . $attachment['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete database record
        return $this->delete($id);
    }

    /**
     * Format file size for display.
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
    }

    /**
     * Get icon class for file type.
     */
    public static function fileIcon(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'bi-file-earmark-image';
        } elseif ($mimeType === 'application/pdf') {
            return 'bi-file-earmark-pdf';
        } elseif (str_contains($mimeType, 'excel') || str_contains($mimeType, 'spreadsheet') || $mimeType === 'text/csv') {
            return 'bi-file-earmark-excel';
        } elseif (str_contains($mimeType, 'zip') || str_contains($mimeType, 'rar')) {
            return 'bi-file-earmark-zip';
        }
        return 'bi-file-earmark';
    }

    /**
     * Check if file is an image (for preview).
     */
    public static function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Get allowed types as a string for the accept attribute.
     */
    public static function acceptAttribute(): string
    {
        return '.jpg,.jpeg,.png,.gif,.webp,.pdf,.xls,.xlsx,.ods,.csv,.zip,.rar';
    }
}
