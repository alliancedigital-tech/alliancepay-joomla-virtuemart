<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 *
 * Installer script: brings the database schema in line with sql/install.mysql.utf8.sql on every
 * install / update / re-install.
 *
 * Why it is needed: Joomla keeps the plugin tables on uninstall and runs "CREATE TABLE IF NOT EXISTS" on
 * re-install, so a table left over from an older version keeps its old structure, and sql/updates/*.sql
 * are skipped when the stored schema version is already current. This script compares every table of the
 * install file with the real database and adds whatever is missing (tables, columns, indexes). It never
 * drops or modifies existing data, and works on both MySQL and MariaDB.
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface {
    private const INSTALL_SQL = '/sql/install.mysql.utf8.sql';
    private const LOG_CATEGORY = 'alliance_payment';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        $app = Factory::getApplication();
        $errors = $this->syncSchema($adapter);

        foreach ($errors as $error) {
            $app->enqueueMessage('AlliancePay schema: ' . $error, 'warning');
            Log::add('Schema sync: ' . $error, Log::ERROR, self::LOG_CATEGORY);
        }

        return true;
    }

    /**
     * @return string[] problems that could not be fixed (empty when the schema is complete)
     */
    private function syncSchema(InstallerAdapter $adapter): array
    {
        $errors = [];
        $sql = $this->readInstallSql($adapter);

        if ($sql === null) {
            return ['sql/install.mysql.utf8.sql not found'];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach (self::parseTables($sql) as $table) {
            try {
                $this->syncTable($db, $table);
            } catch (\Throwable $e) {
                $errors[] = $table['name'] . ': ' . $e->getMessage();
            }
        }

        return $errors;
    }

    private function readInstallSql(InstallerAdapter $adapter): ?string
    {
        $candidates = [JPATH_PLUGINS . '/vmpayment/alliancepay' . self::INSTALL_SQL];

        try {
            $candidates[] = $adapter->getParent()->getPath('source') . self::INSTALL_SQL;
        } catch (\Throwable $e) {
            // The installed copy is enough.
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        return null;
    }

    /**
     * @param array{name: string, create: string, columns: array<string, string>, indexes: array<string, string>} $table
     */
    private function syncTable(DatabaseInterface $db, array $table): void
    {
        $existing = array_change_key_case($db->getTableColumns($table['name']) ?: [], CASE_LOWER);

        if ($existing === []) {
            // Missing table: create it from the install file.
            $db->setQuery($table['create'])->execute();

            return;
        }

        foreach ($table['columns'] as $column => $definition) {
            if (!isset($existing[strtolower($column)])) {
                $db->setQuery('ALTER TABLE ' . $db->quoteName($table['name']) . ' ADD COLUMN ' . $definition)->execute();
            }
        }

        $db->setQuery('SHOW INDEX FROM ' . $db->quoteName($table['name']));
        $haveIndexes = [];

        foreach ($db->loadAssocList() ?: [] as $row) {
            $haveIndexes[strtolower((string) $row['Key_name'])] = true;
        }

        foreach ($table['indexes'] as $index => $definition) {
            if (!isset($haveIndexes[strtolower($index)])) {
                $db->setQuery('ALTER TABLE ' . $db->quoteName($table['name']) . ' ADD ' . $definition)->execute();
            }
        }
    }

    /**
     * Parses the CREATE TABLE statements of the install file. Column / index definitions are taken
     * verbatim, so adding a column to the install file is enough for it to be added to existing sites.
     *
     * @return array<int, array{name: string, create: string, columns: array<string, string>, indexes: array<string, string>}>
     */
    public static function parseTables(string $sql): array
    {
        $tables = [];

        foreach (self::splitStatements($sql) as $statement) {
            if (!preg_match('/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`\s*\((.*)\)\s*(?:ENGINE|DEFAULT|COLLATE|CHARSET)[^)]*$/is', $statement, $m)) {
                continue;
            }

            $columns = [];
            $indexes = [];

            foreach (self::splitDefinitions($m[2]) as $definition) {
                if (preg_match('/^`([^`]+)`\s/', $definition, $c)) {
                    // AUTO_INCREMENT keys are part of the primary key and exist with the table.
                    if (stripos($definition, 'AUTO_INCREMENT') === false) {
                        $columns[$c[1]] = $definition;
                    }
                } elseif (preg_match('/^(?:UNIQUE\s+)?(?:INDEX|KEY)\s+`([^`]+)`/i', $definition, $i)) {
                    $indexes[$i[1]] = $definition;
                }
            }

            $tables[] = ['name' => $m[1], 'create' => $statement, 'columns' => $columns, 'indexes' => $indexes];
        }

        return $tables;
    }

    /**
     * @return string[]
     */
    private static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*(--|#).*$/m', '', $sql);

        return array_values(array_filter(array_map('trim', explode(';', (string) $sql)), static fn (string $s): bool => $s !== ''));
    }

    /**
     * Splits the body of CREATE TABLE on top-level commas (commas inside parentheses stay).
     *
     * @return string[]
     */
    private static function splitDefinitions(string $body): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($body) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }
};
