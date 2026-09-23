<?php

// Load .env values (JASPER_STARTER_PATH, REPORTS_TEMPLATE_PATH, etc.) into $_ENV.
require_once __DIR__ . '/../config/env.php';

class JasperCompiler {
    private string $jasperStarterPath;
    private string $reportsDir;
    private ?string $reportName = null;

    /**
     * @param string|null $jasperStarterPath Path to jasperstarter executable.
     *   Defaults to JASPER_STARTER_PATH from .env, falling back to 'jasperstarter'.
     * @param string|null $reportsDir Default directory where your jrxml files are stored.
     *   Defaults to REPORTS_TEMPLATE_PATH from .env, resolved relative to the project root.
     */
    public function __construct(?string $jasperStarterPath = null, ?string $reportsDir = null) {
        if ($jasperStarterPath === null && isset($_ENV['JASPER_STARTER_PATH'])) {
            $jasperStarterPath = $this->resolveProjectPath($_ENV['JASPER_STARTER_PATH']);
        }
        $this->jasperStarterPath = $jasperStarterPath ?? 'jasperstarter';

        if ($reportsDir === null) {
            $reportsDir = isset($_ENV['REPORTS_TEMPLATE_PATH'])
                ? $this->resolveProjectPath($_ENV['REPORTS_TEMPLATE_PATH'])
                : '';
        }
        $this->reportsDir = rtrim($reportsDir, '/\\');
    }

    /**
     * Resolve a path from .env relative to the project root, unless it is
     * already absolute (so exec() finds it regardless of the caller's CWD).
     */
    private function resolveProjectPath(string $path): string {
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
        return $isAbsolute ? $path : __DIR__ . '/../' . $path;
    }

    /**
     * Set the name of the report file (with or without .jrxml extension).
     *
     * @param string $reportName
     * @return self
     */
    public function setReportName(string $reportName): self {
        $this->reportName = $reportName;
        return $this;
    }

    /**
     * Compiles the set report.
     * 
     * @param string|null $reportName Optional override for the report name
     * @return bool True on success
     * @throws Exception If file is missing or compilation fails
     */
    public function compile(?string $reportName = null): bool {
        // Use passed name, or fall back to the class variable
        $name = $reportName ?? $this->reportName;

        if (empty($name)) {
            throw new Exception("No report filename has been set.");
        }

        // Construct full path
        $jrxmlPath = $this->reportsDir ? "{$this->reportsDir}/{$name}" : $name;

        // Automatically append .jrxml if it was omitted
        if (!str_ends_with(strtolower($jrxmlPath), '.jrxml')) {
            $jrxmlPath .= '.jrxml';
        }

        if (!file_exists($jrxmlPath)) {
            throw new Exception("JRXML file not found: {$jrxmlPath}");
        }

        // Build the compilation command (outputs .jasper in the same directory by default)
        $cmd = escapeshellcmd($this->jasperStarterPath) . ' cp ' . escapeshellarg($jrxmlPath);

        $output = [];
        $exitCode = 0;
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $errorDetails = implode("\n", $output);
            throw new Exception("JasperStarter failed with code {$exitCode}:\n{$errorDetails}");
        }

        return true;
    }
}