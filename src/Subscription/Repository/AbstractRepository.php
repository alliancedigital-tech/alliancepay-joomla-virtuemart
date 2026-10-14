<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Exception\ExecutionFailureException;

/**
 * @package Alliance\Plugin\Vmpayment\Alliancepay\Subscription\Repository
 *
 * @since version 1.5.0
 */
abstract class AbstractRepository
{
    private const MYSQL_DUPLICATE_KEY = 1062;

    public function __construct(protected readonly DatabaseInterface $db)
    {
    }

    /**
     * @return string table name with the #__ prefix
     */
    abstract protected function table(): string;

    protected function now(): string
    {
        return Factory::getDate()->toSql(false, $this->db);
    }

    /**
     * Inserts a row. Null values are skipped so column defaults apply.
     *
     * @throws DuplicateKeyException when a UNIQUE index rejects the row
     */
    protected function insertRow(array $row): int
    {
        $row += ['created_at' => $this->now(), 'updated_at' => $this->now()];

        $object = (object) $row;

        try {
            $this->db->insertObject($this->table(), $object);
        } catch (ExecutionFailureException $e) {
            if ($this->isDuplicateKey($e)) {
                throw new DuplicateKeyException($e->getMessage(), 0, $e);
            }

            throw $e;
        }

        return (int) $this->db->insertid();
    }

    /**
     * Partial update. Null values are skipped (never nulls a stored column) unless $nullable names them:
     * a partial callback delivery must not wipe previously stored data (§4.3 / §5.4).
     *
     * @param string[] $nullable columns that may be explicitly set to NULL
     */
    protected function updateRow(int $id, array $fields, array $nullable = []): void
    {
        if ($fields === []) {
            return;
        }

        $fields['updated_at'] = $this->now();
        $nullColumns = array_values(array_filter(
            $nullable,
            static fn (string $column): bool => array_key_exists($column, $fields) && $fields[$column] === null
        ));

        $object = (object) ($fields + ['id' => $id]);
        $this->db->updateObject($this->table(), $object, 'id', false);

        if ($nullColumns !== []) {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName($this->table()))
                ->where($this->db->quoteName('id') . ' = ' . (int) $id);

            foreach ($nullColumns as $column) {
                $query->set($this->db->quoteName($column) . ' = NULL');
            }

            $this->db->setQuery($query)->execute();
        }
    }

    protected function fetchRow(string $column, string|int $value, bool $lock = false): ?array
    {
        if ($lock) {
            $sql = 'SELECT * FROM ' . $this->db->quoteName($this->table())
                . ' WHERE ' . $this->db->quoteName($column) . ' = ' . $this->db->quote((string) $value)
                . ' LIMIT 1 FOR UPDATE';
            $this->db->setQuery($sql);
        } else {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName($this->table()))
                ->where($this->db->quoteName($column) . ' = :value')
                ->bind(':value', $value)
                ->setLimit(1);
            $this->db->setQuery($query);
        }

        $row = $this->db->loadAssoc();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array>
     */
    protected function fetchAll(string $column, string|int $value, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->table()))
            ->where($this->db->quoteName($column) . ' = :value')
            ->bind(':value', $value)
            ->order($this->db->quoteName($orderBy) . ' ' . ($direction === 'DESC' ? 'DESC' : 'ASC'));
        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }

    private function isDuplicateKey(ExecutionFailureException $e): bool
    {
        return $e->getCode() === self::MYSQL_DUPLICATE_KEY
            || ($e->getPrevious() !== null && (int) $e->getPrevious()->getCode() === self::MYSQL_DUPLICATE_KEY)
            || str_contains($e->getMessage(), 'Duplicate entry');
    }

    /**
     * @return array<string, mixed>|null decoded JSON column or null
     */
    protected static function decodeJson(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
