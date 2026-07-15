<?php
/**
 * Raptor CRM — File Storage Service (Sprint 0)
 * -------------------------------------------------------------------------
 * Validated, private storage for selfies and proof uploads. Files are saved
 * OUTSIDE the web root (STORAGE_PATH) and never linked directly — they are
 * served back through an authenticated endpoint (FileController::show) so
 * RBAC applies to every download.
 *
 * Returns a relative storage KEY (e.g. "attendance/2026/07/ab12..jpg") which
 * is what you persist in the DB (e.g. attendance.login_selfie_url).
 */

class Storage {

    // Allowed image/proof mime types → canonical extension.
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Store an uploaded file from $_FILES.
     *
     * @param array  $file      One entry from $_FILES (e.g. $_FILES['selfie']).
     * @param string $category  Logical folder, e.g. 'attendance', 'proof', 'meetings'.
     * @return string           Relative storage key to persist in the DB.
     * @throws RuntimeException  On any validation failure.
     */
    public static function put(array $file, string $category): string {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid upload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed with error code ' . $file['error'] . '.');
        }
        if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) {
            throw new RuntimeException('File exceeds the maximum allowed size.');
        }

        // Verify the real mime type, not the client-supplied name/type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Unsupported file type: ' . $mime);
        }
        $ext = self::ALLOWED[$mime];

        $category = preg_replace('/[^a-z0-9_-]/', '', strtolower($category)) ?: 'misc';
        // Date-sharded path keeps directories small; PHP date() is fine on the server.
        $sub  = $category . '/' . date('Y') . '/' . date('m');
        $dir  = rtrim(STORAGE_PATH, '/\\') . '/' . $sub;

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create storage directory.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }
        @chmod($dest, 0640);

        if (defined('STORAGE_PROVIDER') && STORAGE_PROVIDER === 's3') {
            $bucket = S3_BUCKET;
            $key = $sub . '/' . $name;
            $cmd = "aws s3 cp " . escapeshellarg($dest) . " s3://" . $bucket . "/" . $key;
            $output = [];
            $retval = 0;
            exec($cmd . " 2>&1", $output, $retval);
            @unlink($dest);
            if ($retval !== 0) {
                throw new RuntimeException('Could not upload file to S3: ' . implode('; ', $output));
            }
        }

        return $sub . '/' . $name; // storage key
    }

    /**
     * Store a raw base64 data URL (used when the browser captures a selfie via
     * canvas/getUserMedia and posts a data: string instead of a file).
     */
    public static function putDataUrl(string $dataUrl, string $category): string {
        if (!preg_match('/^data:(image\/(jpeg|png|webp));base64,/', $dataUrl, $m)) {
            throw new RuntimeException('Invalid image data.');
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$m[1]];
        $data = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($data === false || strlen($data) === 0 || strlen($data) > MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Invalid or oversized image data.');
        }

        $category = preg_replace('/[^a-z0-9_-]/', '', strtolower($category)) ?: 'misc';
        $sub  = $category . '/' . date('Y') . '/' . date('m');
        $dir  = rtrim(STORAGE_PATH, '/\\') . '/' . $sub;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create storage directory.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (file_put_contents($dest, $data) === false) {
            throw new RuntimeException('Could not save the image.');
        }
        @chmod($dest, 0640);

        if (defined('STORAGE_PROVIDER') && STORAGE_PROVIDER === 's3') {
            $bucket = S3_BUCKET;
            $key = $sub . '/' . $name;
            $cmd = "aws s3 cp " . escapeshellarg($dest) . " s3://" . $bucket . "/" . $key;
            $output = [];
            $retval = 0;
            exec($cmd . " 2>&1", $output, $retval);
            @unlink($dest);
            if ($retval !== 0) {
                throw new RuntimeException('Could not upload selfie to S3: ' . implode('; ', $output));
            }
        }

        return $sub . '/' . $name;
    }

    /** Check if a storage key exists in S3 or local disk. */
    public static function exists(string $key): bool {
        if (defined('STORAGE_PROVIDER') && STORAGE_PROVIDER === 's3') {
            $bucket = S3_BUCKET;
            $cmd = "aws s3api head-object --bucket " . escapeshellarg($bucket) . " --key " . escapeshellarg($key);
            $output = [];
            $retval = 0;
            exec($cmd . " 2>&1", $output, $retval);
            return $retval === 0;
        }
        return self::path($key) !== null;
    }

    /** Generate pre-signed URL for S3 or fallback to controlled local URL. */
    public static function presign(string $key, int $expires = 3600): string {
        if (defined('STORAGE_PROVIDER') && STORAGE_PROVIDER === 's3') {
            $bucket = S3_BUCKET;
            $cmd = "aws s3 presign s3://" . $bucket . "/" . $key . " --expires-in " . $expires;
            $output = [];
            $retval = 0;
            exec($cmd, $output, $retval);
            if ($retval === 0 && !empty($output)) {
                return trim($output[0]);
            }
        }
        return URLROOT . "/index.php?route=file/show&key=" . urlencode($key);
    }

    /** Absolute filesystem path for a stored key, or null if it escapes STORAGE_PATH / is missing. */
    public static function path(string $key): ?string {
        $base = rtrim(STORAGE_PATH, '/\\');
        $full = realpath($base . '/' . $key);
        if ($full === false || strpos($full, realpath($base)) !== 0) {
            return null; // path traversal guard
        }
        return is_file($full) ? $full : null;
    }
}
