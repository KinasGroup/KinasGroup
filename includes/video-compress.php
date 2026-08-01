<?php
/**
 * KINAS GROUP — Server-side video compression (ffmpeg)
 *
 * Used for direct video uploads (currently: the virtual tour feature on
 * property/car listings). A raw phone-recorded walkthrough clip is
 * often 100MB+ at a bitrate far higher than needed for web playback;
 * this re-encodes it to a much smaller, still-good-quality file before
 * it's handed to FileUpload/R2Upload.
 *
 * Fails OPEN, not closed: if ffmpeg isn't installed, times out, or
 * errors for any reason, the ORIGINAL uploaded file is used as-is
 * rather than blocking the upload entirely. Compression is a nice-to-
 * have; an uncompressed video is still much better than none.
 */

/**
 * Small formatting helper for compression log messages.
 */
function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . 'MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . 'KB';
    return $bytes . 'B';
}

/**
 * @return array{compressed: bool, original_size: int, new_size: ?int, error: ?string}
 */
function compressVideoIfPossible(string $tmpPath, string $mimeType): array
{
    $originalSize = @filesize($tmpPath) ?: 0;
    $result = ['compressed' => false, 'original_size' => $originalSize, 'new_size' => null, 'error' => null];

    // Only handle the video types this feature actually accepts.
    if (!in_array($mimeType, ['video/mp4', 'video/quicktime', 'video/webm'], true)) {
        return $result;
    }

    // Small clips aren't worth the CPU time / re-encode risk — only
    // compress anything over roughly 15MB, where it actually matters.
    if ($originalSize < 15 * 1024 * 1024) {
        return $result;
    }

    $ffmpegPath = trim((string)@shell_exec('command -v ffmpeg 2>/dev/null'));
    if ($ffmpegPath === '') {
        error_log('compressVideoIfPossible: ffmpeg not available on this server — uploading original file uncompressed.');
        return $result;
    }

    $outputPath = $tmpPath . '_compressed.mp4';

    // -vf scale: caps resolution at 1080p (never upscales smaller video)
    // -crf 28: quality-based compression, a well-established "much
    //          smaller file, still clearly good quality" setting for
    //          walkthrough/handheld footage (lower = higher quality/size)
    // -preset veryfast: prioritizes encoding speed over squeezing out
    //          every last byte, since this runs inline during a user's
    //          upload request, not as a background job
    // -movflags +faststart: lets the video begin playing before the
    //          whole file downloads (metadata moved to the front)
    // -an handling: keep audio but cap its bitrate too, it's rarely the
    //          bulk of the file size but still worth trimming
    $cmd = sprintf(
        '%s -y -i %s -vf "scale=\'min(1920,iw)\':\'min(1080,ih)\':force_original_aspect_ratio=decrease" ' .
        '-c:v libx264 -crf 28 -preset veryfast -c:a aac -b:a 96k -movflags +faststart %s 2>&1',
        escapeshellcmd($ffmpegPath),
        escapeshellarg($tmpPath),
        escapeshellarg($outputPath)
    );

    // Hard wall-clock cap so one large/awkward file can't hang the
    // request indefinitely — PHP's own max_execution_time (raised to
    // 300s for uploads, see docker/uploads.ini) is the outer bound
    // anyway, but this keeps ffmpeg itself from being the last thing
    // still running if something goes wrong.
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($cmd, $descriptors, $pipes);

    if (!is_resource($process)) {
        error_log('compressVideoIfPossible: failed to start ffmpeg process.');
        return $result;
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    $start = time();
    $timeoutSeconds = 240;
    while (true) {
        $status = proc_get_status($process);
        if (!$status['running']) break;
        if (time() - $start > $timeoutSeconds) {
            proc_terminate($process, 9);
            error_log('compressVideoIfPossible: ffmpeg timed out after ' . $timeoutSeconds . 's — using original file.');
            @unlink($outputPath);
            return $result;
        }
        usleep(200000); // 200ms
    }
    proc_close($process);

    if (!is_file($outputPath) || filesize($outputPath) === 0) {
        error_log('compressVideoIfPossible: ffmpeg produced no output — using original file.');
        @unlink($outputPath);
        return $result;
    }

    $newSize = filesize($outputPath);

    // Only actually use the compressed version if it's genuinely
    // smaller — an already-efficient source file re-encoded at CRF 28
    // could theoretically come out larger for unusual footage; no
    // reason to swap in a worse result.
    if ($newSize >= $originalSize) {
        @unlink($outputPath);
        return $result;
    }

    // Overwrite the ORIGINAL upload's bytes in place, then discard the
    // temp output file. is_uploaded_file() checks the PATH against
    // PHP's internal upload registry, not the file's current content —
    // so the caller can keep passing the same tmp_name it always did.
    if (!@copy($outputPath, $tmpPath)) {
        error_log('compressVideoIfPossible: failed to copy compressed output back to original path — using original file.');
        @unlink($outputPath);
        return $result;
    }
    @unlink($outputPath);

    return [
        'compressed' => true,
        'original_size' => $originalSize,
        'new_size' => $newSize,
        'error' => null,
    ];
}
