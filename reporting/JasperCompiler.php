<?php

require_once __DIR__ . '/../config/report_paths.php';

final class JasperCompiler
{
    private string $jasperStarterPath;
    private string $reportsDirectory;
    private ?string $reportName = null;

    public function __construct(?string $jasperStarterPath = null, ?string $reportsDirectory = null)
    {
        $this->jasperStarterPath = $jasperStarterPath
            ?? reportPath('JASPER_STARTER_PATH');
        $this->reportsDirectory = rtrim($reportsDirectory ?? reportPath('REPORTS_TEMPLATE_PATH'), '/\\');
    }

    public function setReportName(string $reportName): self
    {
        $this->reportName = $reportName;
        return $this;
    }

    public function compile(?string $reportName = null): bool
    {
        $name = $reportName ?? $this->reportName;
        if ($name === null || $name === '') {
            throw new RuntimeException('No report filename has been set.');
        }

        if (!is_file($this->jasperStarterPath) || !is_executable($this->jasperStarterPath)) {
            throw new RuntimeException("JasperStarter executable not found or not executable: {$this->jasperStarterPath}");
        }

        $jrxmlPath = $this->reportsDirectory . '/' . $name;
        if (!str_ends_with(strtolower($jrxmlPath), '.jrxml')) {
            $jrxmlPath .= '.jrxml';
        }

        if (!is_file($jrxmlPath)) {
            throw new RuntimeException("JRXML file not found: {$jrxmlPath}");
        }

        $command = escapeshellarg($this->jasperStarterPath) . ' cp ' . escapeshellarg($jrxmlPath);
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $details = implode("\n", $output);
            throw new RuntimeException("JasperStarter failed with code {$exitCode}:\n{$details}");
        }

        $jasperPath = substr($jrxmlPath, 0, -strlen('.jrxml')) . '.jasper';
        if (!is_file($jasperPath)) {
            throw new RuntimeException('JasperStarter completed without creating the compiled report.');
        }

        return true;
    }
}
