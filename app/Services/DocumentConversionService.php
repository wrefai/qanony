<?php

namespace App\Services;

/**
 * Document Conversion Service.
 *
 * Handles conversion between document formats:
 * - .doc -> .docx via LibreOffice headless (when available)
 * - .doc files read directly by DocumentPreviewService when LibreOffice is not available
 *
 * Caching: converted files are stored in writable/uploads/documents/converted/
 * keyed by content hash. If a converted file already exists and is valid, it is reused.
 *
 * LibreOffice detection: checks known installation paths on Windows and Linux.
 * When not available, .doc files are read directly by PhpWord's MsDoc reader
 * in DocumentPreviewService with Arabic post-processing to fix fragmentation.
 *
 * ## LibreOffice Installation (recommended for best .doc rendering)
 *
 * ### Windows (XAMPP)
 * 1. Download LibreOffice from: https://www.libreoffice.org/download/download/
 *    Choose "Windows x86_64" (64-bit) — get the .msi installer.
 * 2. Run the installer. Use the default install path:
 *    C:\Program Files\LibreOffice\
 * 3. No additional configuration is needed — the service auto-detects the binary at:
 *    C:\Program Files\LibreOffice\program\soffice.exe
 * 4. Restart Apache after installing:
 *    powershell -Command "Start-Process powershell -ArgumentList '-Command', 'Restart-Service Apache2.4' -Verb RunAs -Wait"
 * 5. Verify: visit any .doc document's render page — it should now show
 *    "method: libreoffice" in the CI4 log instead of "method: phpword".
 *
 * ### Linux (Ubuntu/Debian)
 *   sudo apt-get update
 *   sudo apt-get install -y libreoffice-core libreoffice-writer
 *   # Verify: which soffice → /usr/bin/soffice
 *
 * ### Linux (CentOS/RHEL)
 *   sudo yum install -y libreoffice-core libreoffice-writer
 *
 * ### Docker
 *   RUN apt-get update && apt-get install -y libreoffice-core libreoffice-writer && rm -rf /var/lib/apt/lists/*
 *
 * ### Why LibreOffice?
 * LibreOffice uses Microsoft's OLE2 document engine to properly parse .doc files,
 * producing well-structured .docx output with correct paragraph grouping. Without it,
 * PhpWord's MsDoc reader fragments Arabic text into individual character runs, which
 * the DocumentPreviewService's mergeAdjacentParagraphs() post-processor fixes
 * reasonably well — but LibreOffice produces objectively better results.
 */
class DocumentConversionService
{
    /** Known LibreOffice binary paths (Windows + Linux) */
    private const LIBREOFFICE_PATHS = [
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        '/usr/bin/soffice',
        '/usr/local/bin/soffice',
        '/usr/bin/libreoffice',
    ];

    /** Converted files output directory (relative to WRITEPATH) */
    private const CONVERTED_DIR = 'uploads/documents/converted/';

    /**
     * Check whether LibreOffice is installed on this system.
     *
     * @return string|null Full path to soffice binary, or null if not found
     */
    public static function findLibreOffice(): ?string
    {
        foreach (self::LIBREOFFICE_PATHS as $path) {
            if (file_exists($path)) {
                // On Windows, is_executable() is unreliable; file_exists() is sufficient
                // for known executable paths. On Linux, also check is_executable().
                if (PHP_OS_FAMILY === 'Windows' || is_executable($path)) {
                    return $path;
                }
            }
        }

        // Try PATH-based detection
        if (PHP_OS_FAMILY === 'Windows') {
            $result = shell_exec('where soffice.exe 2>NUL');
        } else {
            $result = shell_exec('which soffice 2>/dev/null');
        }

        if ($result) {
            $binary = trim($result);
            if ($binary !== '' && file_exists($binary)) {
                return $binary;
            }
        }

        return null;
    }

    /**
     * Check if LibreOffice is available.
     */
    public static function isLibreOfficeAvailable(): bool
    {
        return self::findLibreOffice() !== null;
    }

    /**
     * Get the converted directory path (absolute).
     */
    public static function getConvertedDir(): string
    {
        $dir = WRITEPATH . self::CONVERTED_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Convert a .doc file to .docx format.
     *
     * Strategy:
     * 1. Check cache: if converted/{hash}.docx exists, return it
     * 2. If LibreOffice available: soffice --headless --convert-to docx
     * 3. Fallback: use PhpWord MsDoc reader to load, then save as Word2007 (.docx)
     *
     * @param string $filePath    Absolute path to the source .doc file
     * @param string $contentHash SHA-256 hash of the source file (for cache key)
     * @return array{success: bool, converted_path: string, method: string, error: string}
     */
    public static function convertDocToDocx(string $filePath, string $contentHash): array
    {
        $result = [
            'success'        => false,
            'converted_path' => '',
            'method'         => '',
            'error'          => '',
        ];

        // Validate source file
        if (!file_exists($filePath)) {
            $result['error'] = 'Source file not found: ' . $filePath;
            log_message('error', '[DocConversion] ' . $result['error']);
            return $result;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension !== 'doc') {
            $result['error'] = 'File is not a .doc file: ' . $extension;
            log_message('error', '[DocConversion] ' . $result['error']);
            return $result;
        }

        // Check cache
        $convertedDir = self::getConvertedDir();
        $cachedPath = $convertedDir . $contentHash . '.docx';
        if (file_exists($cachedPath) && filesize($cachedPath) > 0) {
            log_message('info', "[DocConversion] Cache hit for {$contentHash}.docx");
            $result['success'] = true;
            $result['converted_path'] = $cachedPath;
            $result['method'] = 'cache';
            return $result;
        }

        // Try LibreOffice first
        $soffice = self::findLibreOffice();
        if ($soffice !== null) {
            $loResult = self::convertViaLibreOffice($filePath, $soffice, $cachedPath);
            if ($loResult['success']) {
                log_message('info', "[DocConversion] LibreOffice conversion successful for {$contentHash}");
                return $loResult;
            }
            log_message('warning', "[DocConversion] LibreOffice failed: {$loResult['error']}, trying PhpWord fallback");
        }

        // Fallback: PhpWord MsDoc reader -> Word2007 writer
        $phpWordResult = self::convertViaPhpWord($filePath, $cachedPath);
        if ($phpWordResult['success']) {
            log_message('info', "[DocConversion] PhpWord conversion successful for {$contentHash}");
        } else {
            log_message('error', "[DocConversion] PhpWord conversion also failed for {$contentHash}: {$phpWordResult['error']}");
        }

        return $phpWordResult;
    }

    /**
     * Convert .doc to .docx via LibreOffice headless.
     *
     * @param string $sourcePath Absolute path to source .doc file
     * @param string $soffice    Path to soffice binary
     * @param string $targetPath Desired output path for .docx
     * @return array{success: bool, converted_path: string, method: string, error: string}
     */
    private static function convertViaLibreOffice(string $sourcePath, string $soffice, string $targetPath): array
    {
        $result = [
            'success'        => false,
            'converted_path' => '',
            'method'         => 'libreoffice',
            'error'          => '',
        ];

        $outputDir = dirname($targetPath);
        $escapedSource = escapeshellarg($sourcePath);
        $escapedDir = escapeshellarg($outputDir);
        $escapedSoffice = escapeshellarg($soffice);

        // LibreOffice converts to a file named after the source with .docx extension
        $sourceBasename = pathinfo($sourcePath, PATHINFO_FILENAME);

        $cmd = "{$escapedSoffice} --headless --convert-to docx --outdir {$escapedDir} {$escapedSource} 2>&1";

        log_message('debug', "[DocConversion] Running: {$cmd}");
        $output = shell_exec($cmd);
        $loOutputPath = $outputDir . DIRECTORY_SEPARATOR . $sourceBasename . '.docx';

        if (!file_exists($loOutputPath) || filesize($loOutputPath) === 0) {
            $result['error'] = 'LibreOffice conversion produced no output. Command output: ' . ($output ?: 'empty');
            @unlink($loOutputPath);
            return $result;
        }

        // Rename to our cache key name
        if ($loOutputPath !== $targetPath) {
            if (!rename($loOutputPath, $targetPath)) {
                // If rename fails, try copy+delete
                if (copy($loOutputPath, $targetPath)) {
                    @unlink($loOutputPath);
                } else {
                    $result['error'] = 'Failed to move converted file to cache location';
                    return $result;
                }
            }
        }

        $result['success'] = true;
        $result['converted_path'] = $targetPath;
        return $result;
    }

    /**
     * Convert .doc to .docx via PhpWord (MsDoc reader -> Word2007 writer).
     *
     * This is the fallback when LibreOffice is not available.
     * Quality depends on PhpWord's MsDoc reader capabilities.
     *
     * @param string $sourcePath Absolute path to source .doc file
     * @param string $targetPath Desired output path for .docx
     * @return array{success: bool, converted_path: string, method: string, error: string}
     */
    private static function convertViaPhpWord(string $sourcePath, string $targetPath): array
    {
        $result = [
            'success'        => false,
            'converted_path' => '',
            'method'         => 'phpword',
            'error'          => '',
        ];

        try {
            $reader = \PhpOffice\PhpWord\IOFactory::createReader('MsDoc');
            $phpWord = $reader->load($sourcePath);

            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($targetPath);

            if (file_exists($targetPath) && filesize($targetPath) > 0) {
                $result['success'] = true;
                $result['converted_path'] = $targetPath;
            } else {
                $result['error'] = 'PhpWord wrote an empty .docx file';
                @unlink($targetPath);
            }
        } catch (\Throwable $e) {
            $result['error'] = 'PhpWord conversion failed: ' . $e->getMessage();
            @unlink($targetPath);
        }

        return $result;
    }

    /**
     * Convert a document to PDF via LibreOffice headless.
     *
     * @param string $sourcePath Absolute path to source .doc or .docx file
     * @param string $contentHash SHA-256 hash for cache key
     * @return array{success: bool, pdf_path: string, error: string}
     */
    public static function convertToPdf(string $sourcePath, string $contentHash): array
    {
        $result = [
            'success'  => false,
            'pdf_path' => '',
            'error'    => '',
        ];

        $soffice = self::findLibreOffice();
        if ($soffice === null) {
            $result['error'] = 'LibreOffice is not installed. PDF conversion requires LibreOffice.';
            return $result;
        }

        $previewDir = WRITEPATH . 'uploads/documents/preview/';
        if (!is_dir($previewDir)) {
            mkdir($previewDir, 0755, true);
        }

        $cachedPdf = $previewDir . $contentHash . '.pdf';
        if (file_exists($cachedPdf) && filesize($cachedPdf) > 0) {
            $result['success'] = true;
            $result['pdf_path'] = $cachedPdf;
            return $result;
        }

        $escapedSource = escapeshellarg($sourcePath);
        $escapedDir = escapeshellarg($previewDir);
        $escapedSoffice = escapeshellarg($soffice);

        $sourceBasename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $cmd = "{$escapedSoffice} --headless --convert-to pdf --outdir {$escapedDir} {$escapedSource} 2>&1";

        log_message('debug', "[DocConversion] PDF conversion: {$cmd}");
        $output = shell_exec($cmd);
        $loOutputPath = $previewDir . $sourceBasename . '.pdf';

        if (!file_exists($loOutputPath) || filesize($loOutputPath) === 0) {
            $result['error'] = 'LibreOffice PDF conversion produced no output: ' . ($output ?: 'empty');
            @unlink($loOutputPath);
            return $result;
        }

        if ($loOutputPath !== $cachedPdf) {
            if (!rename($loOutputPath, $cachedPdf)) {
                if (copy($loOutputPath, $cachedPdf)) {
                    @unlink($loOutputPath);
                } else {
                    $result['error'] = 'Failed to move PDF to cache location';
                    return $result;
                }
            }
        }

        $result['success'] = true;
        $result['pdf_path'] = $cachedPdf;
        return $result;
    }

    /**
     * Resolve the best file path for processing a document.
     *
     * For .docx files: returns the original path directly.
     * For .doc files: returns a converted .docx path if available, otherwise the original .doc path.
     *
     * This method supports the dual-path lookup for backward compatibility:
     * checks both the stored file_path and the original/ subdirectory.
     *
     * @param string $storedPath  Relative path from DB (e.g. 'uploads/documents/xyz.doc')
     * @param string $contentHash SHA-256 hash for cache lookup
     * @return array{file_path: string, is_converted: bool, method: string, exists: bool}
     */
    public static function resolveDocumentPath(string $storedPath, string $contentHash): array
    {
        $result = [
            'file_path'    => '',
            'is_converted' => false,
            'method'       => 'original',
            'exists'       => false,
        ];

        // Try stored path first (writable/ prefix)
        $fullPath = WRITEPATH . $storedPath;
        if (!file_exists($fullPath)) {
            // Try original/ subdirectory (new upload location)
            $basename = basename($storedPath);
            $altPath = WRITEPATH . 'uploads/documents/original/' . $basename;
            if (file_exists($altPath)) {
                $fullPath = $altPath;
            } else {
                return $result;
            }
        }

        $result['exists'] = true;
        $result['file_path'] = $fullPath;

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // For .docx files, use as-is
        if ($extension === 'docx') {
            return $result;
        }

        // For .doc files, try to get a converted .docx version.
        // IMPORTANT: Only convert via LibreOffice. The PhpWord MsDoc→Word2007 fallback
        // produces a degraded .docx with the same character-run fragmentation as the
        // original .doc, making it pointless for preview. The DocumentPreviewService
        // can read .doc files directly via MsDoc reader and apply post-processing
        // to fix the fragmentation, which produces far better results.
        if ($extension === 'doc') {
            // Check if LibreOffice is available — only then is conversion worthwhile
            if (self::isLibreOfficeAvailable()) {
                $conversion = self::convertDocToDocx($fullPath, $contentHash);
                if ($conversion['success']) {
                    $result['file_path'] = $conversion['converted_path'];
                    $result['is_converted'] = true;
                    $result['method'] = $conversion['method'];
                }
            } else {
                // Check if a previously LibreOffice-converted .docx exists in cache
                // (from when LO was available, or copied manually)
                $cachedPath = self::getConvertedDir() . $contentHash . '.docx';
                if (file_exists($cachedPath) && filesize($cachedPath) > 0) {
                    $result['file_path'] = $cachedPath;
                    $result['is_converted'] = true;
                    $result['method'] = 'cache';
                }
                // Otherwise file_path stays as original .doc — preview service reads it directly
            }
        }

        return $result;
    }

    /**
     * Clear cached conversion for a specific document hash.
     *
     * @param string $contentHash SHA-256 hash
     */
    public static function clearCache(string $contentHash): void
    {
        $convertedDir = self::getConvertedDir();
        $previewDir = WRITEPATH . 'uploads/documents/preview/';
        $extractedDir = WRITEPATH . 'uploads/documents/extracted/';

        $files = [
            $convertedDir . $contentHash . '.docx',
            $previewDir . $contentHash . '.pdf',
            $previewDir . $contentHash . '.html',
            $extractedDir . $contentHash . '.txt',
            $extractedDir . $contentHash . '.normalized.txt',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
                log_message('info', "[DocConversion] Cleared cached file: {$file}");
            }
        }
    }

    /**
     * Get LibreOffice status information (useful for admin dashboard).
     *
     * @return array{installed: bool, path: string|null, instructions: string}
     */
    public static function getLibreOfficeStatus(): array
    {
        $path = self::findLibreOffice();

        $instructions = PHP_OS_FAMILY === 'Windows'
            ? "Download LibreOffice from https://www.libreoffice.org/download/download/ (Windows x86_64 .msi).\n"
              . "Install to C:\\Program Files\\LibreOffice\\ (default path).\n"
              . "Restart Apache after installing."
            : "Ubuntu/Debian: sudo apt-get install -y libreoffice-core libreoffice-writer\n"
              . "CentOS/RHEL: sudo yum install -y libreoffice-core libreoffice-writer";

        return [
            'installed'    => $path !== null,
            'path'         => $path,
            'instructions' => $instructions,
        ];
    }
}
