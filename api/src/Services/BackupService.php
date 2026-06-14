<?php

declare(strict_types=1);

namespace BudgetMan\Services;

use BudgetMan\Core\Config;
use BudgetMan\Core\Database;
use BudgetMan\Core\Response;
use PDO;

final class BackupService
{
    private function dbConfig(): array
    {
        $db = Config::load()['db'] ?? [];

        return [
            'host' => (string) ($db['host'] ?? 'db'),
            'port' => (int) ($db['port'] ?? 3306),
            'name' => (string) ($db['name'] ?? 'budget_manager'),
            'user' => (string) ($db['user'] ?? 'budget'),
            'pass' => (string) ($db['pass'] ?? ''),
        ];
    }

    public function download(): void
    {
        $cfg = $this->dbConfig();
        $tmp = tempnam(sys_get_temp_dir(), 'budget-backup-');
        if ($tmp === false) {
            Response::json(['error' => 'No se pudo crear el respaldo temporal'], 500);
            return;
        }

        $command = array_merge($this->mysqlBase('mysqldump', $cfg), [
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--add-drop-table',
            '--no-tablespaces',
            $cfg['name'],
        ]);

        [$code, $message] = $this->runTool($command, ['MYSQL_PWD' => $cfg['pass']], $tmp);
        if ($code !== 0) {
            @unlink($tmp);
            Response::json(['error' => 'No se pudo crear el respaldo', 'detail' => $message], 500);
            return;
        }

        $filename = 'budgetman-backup-' . date('Ymd-His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($tmp));
        header('Cache-Control: no-store');
        readfile($tmp);
        @unlink($tmp);
    }

    public function restore(): void
    {
        if (!isset($_FILES['backup']) || !is_array($_FILES['backup'])) {
            Response::json(['error' => 'Archivo .sql requerido'], 422);
            return;
        }

        $file = $_FILES['backup'];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'No se pudo subir el archivo', 'detail' => 'Código de subida: ' . $error], 422);
            return;
        }

        $name = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            Response::json(['error' => 'Archivo subido inválido'], 422);
            return;
        }
        if (!str_ends_with(strtolower($name), '.sql')) {
            Response::json(['error' => 'El archivo debe tener extensión .sql'], 422);
            return;
        }

        $this->clearCurrentDatabase();

        $cfg = $this->dbConfig();
        $command = array_merge($this->mysqlBase('mysql', $cfg), ['--binary-mode', $cfg['name']]);
        [$code, $message] = $this->runTool($command, ['MYSQL_PWD' => $cfg['pass']], null, $tmpName);
        if ($code !== 0) {
            Response::json(['error' => 'No se pudo restaurar el respaldo', 'detail' => $message], 500);
            return;
        }

        Response::json(['ok' => true]);
    }

    private function mysqlBase(string $binary, array $cfg): array
    {
        return [
            $binary,
            '--host=' . $cfg['host'],
            '--port=' . (string) $cfg['port'],
            '--user=' . $cfg['user'],
            '--default-character-set=utf8mb4',
        ];
    }

    private function runTool(array $command, array $env, ?string $stdoutFile = null, ?string $stdinFile = null): array
    {
        $descriptors = [
            0 => $stdinFile ? ['file', $stdinFile, 'r'] : ['pipe', 'r'],
            1 => $stdoutFile ? ['file', $stdoutFile, 'w'] : ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            return [1, 'No se pudo iniciar la herramienta de MySQL'];
        }

        if (!$stdinFile && isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $stdout = '';
        if (!$stdoutFile && isset($pipes[1]) && is_resource($pipes[1])) {
            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
        }

        $stderr = '';
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);
        }

        return [proc_close($process), trim($stderr !== '' ? $stderr : $stdout)];
    }

    private function clearCurrentDatabase(): void
    {
        $pdo = Database::connection();
        $rows = $pdo
            ->query("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")
            ->fetchAll();

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($rows as $row) {
                if (($row['TABLE_TYPE'] ?? '') === 'VIEW') {
                    $pdo->exec('DROP VIEW IF EXISTS ' . $this->quoteIdentifier((string) $row['TABLE_NAME']));
                }
            }
            foreach ($rows as $row) {
                if (($row['TABLE_TYPE'] ?? '') !== 'VIEW') {
                    $pdo->exec('DROP TABLE IF EXISTS ' . $this->quoteIdentifier((string) $row['TABLE_NAME']));
                }
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
