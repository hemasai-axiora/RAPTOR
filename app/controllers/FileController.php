<?php
/**
 * Raptor CRM — Authenticated File Serving (Sprint 0)
 * Streams private storage files only to logged-in users. Uploaded selfies and
 * proof images are NEVER exposed via a public path; the DB stores a storage
 * key and the UI references files as: index.php?route=file/show&key=<key>
 *
 * NOTE: This gates on authentication. Fine-grained "can THIS user see THIS
 * file" checks are layered per-feature (e.g. attendance limits keys to
 * visibleUserIds()); this endpoint is the shared, auth-required gateway.
 */

class FileController extends Controller {

    public function show() {
        $this->requireAuth();

        $key = $_GET['key'] ?? '';
        // Keys are of the form "category/YYYY/MM/hex.ext" — reject anything else.
        if (!preg_match('#^[a-z0-9_-]+/\d{4}/\d{2}/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#', $key)) {
            http_response_code(400);
            die('Invalid file key.');
        }

        if (defined('STORAGE_PROVIDER') && STORAGE_PROVIDER === 's3') {
            if (!Storage::exists($key)) {
                http_response_code(404);
                die('File not found.');
            }
            $ext = pathinfo($key, PATHINFO_EXTENSION);
            $mime = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'pdf'  => 'application/pdf',
            ][$ext] ?? 'application/octet-stream';

            header('Content-Type: ' . $mime);
            header('Cache-Control: private, max-age=0, no-store');
            header('X-Content-Type-Options: nosniff');
            $bucket = S3_BUCKET;
            passthru("aws s3 cp s3://" . $bucket . "/" . escapeshellarg($key) . " -");
            exit();
        }

        $path = Storage::path($key);
        if ($path === null) {
            http_response_code(404);
            die('File not found.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=0, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit();
    }
}
