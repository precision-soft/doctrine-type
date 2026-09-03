<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Service;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use PrecisionSoft\Doctrine\Type\Example\Enum\Currency;
use PrecisionSoft\Doctrine\Type\Example\Enum\ProductStatus;
use PrecisionSoft\Doctrine\Type\Example\Enum\SalesChannel;
use PrecisionSoft\Doctrine\Type\Example\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Example\Schema\CatalogSchema;
use PrecisionSoft\Doctrine\Type\Example\Type\CurrencyType;
use PrecisionSoft\Doctrine\Type\Example\Type\ProductStatusType;
use PrecisionSoft\Doctrine\Type\Example\Type\SalesChannelSetType;

/** writes and reads the catalogue through the registered types, the way an application without an ORM does */
class CatalogRepository
{
    public function __construct(protected Connection $connection, protected CatalogSchema $catalogSchema = new CatalogSchema()) {}

    /** drops the two tables first, so a crashed run cannot leave a stale schema behind */
    public function createSchema(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        foreach ([CatalogSchema::PRODUCT_TABLE, CatalogSchema::CATEGORY_TABLE] as $tableName) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $platform->quoteSingleIdentifier($tableName));
        }

        foreach ($this->catalogSchema->build($platform)->toSql($platform) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function saveCategory(string $name, int $order): int
    {
        $platform = $this->connection->getDatabasePlatform();
        $orderColumn = $platform->quoteSingleIdentifier('order');

        /* the type in the map is what makes the range check happen here, before the server sees the value */
        $this->connection->insert(
            CatalogSchema::CATEGORY_TABLE,
            ['name' => $name, $orderColumn => $order],
            [$orderColumn => $this->catalogSchema->getUnsignedTinyintTypeName($platform)],
        );

        return (int)$this->connection->lastInsertId();
    }

    /**
     * a mutable `DateTime`: DBAL's `DateTimeType`, which the library's extends, converts that class only (the immutable one has its own type)
     *
     * @param list<SalesChannel> $channels
     */
    public function saveProduct(
        int $categoryId,
        string $name,
        ProductStatus $status,
        Currency $currency,
        array $channels,
        int $priority,
        DateTime $modified,
    ): int {
        $this->connection->insert(
            CatalogSchema::PRODUCT_TABLE,
            [
                'category_id' => $categoryId,
                'name' => $name,
                'status' => $status,
                'currency' => $currency,
                'channels' => $channels,
                'priority' => $priority,
                'modified' => $modified,
            ],
            [
                'status' => ProductStatusType::getDefaultName(),
                'currency' => CurrencyType::getDefaultName(),
                'channels' => SalesChannelSetType::getDefaultName(),
                'priority' => $this->catalogSchema->getSignedTinyintTypeName($this->connection->getDatabasePlatform()),
                'modified' => CatalogSchema::MODIFIED_TYPE_NAME,
            ],
        );

        return (int)$this->connection->lastInsertId();
    }

    public function renameProduct(int $productId, string $name): void
    {
        $this->connection->update(CatalogSchema::PRODUCT_TABLE, ['name' => $name], ['id' => $productId]);
    }

    /**
     * @return array{
     *     name: string,
     *     status: ProductStatus,
     *     currency: Currency,
     *     channels: list<SalesChannel>,
     *     priority: int,
     *     modified: DateTimeInterface,
     * }
     */
    public function findProduct(int $productId): array
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT name, status, currency, channels, priority, modified FROM %s WHERE id = ?', CatalogSchema::PRODUCT_TABLE),
            [$productId],
        );

        if (false === $row) {
            throw new Exception(\sprintf('product `%d` does not exist', $productId));
        }

        $platform = $this->connection->getDatabasePlatform();
        $status = Type::getType(ProductStatusType::getDefaultName())->convertToPHPValue($row['status'], $platform);
        $currency = Type::getType(CurrencyType::getDefaultName())->convertToPHPValue($row['currency'], $platform);
        $channels = Type::getType(SalesChannelSetType::getDefaultName())->convertToPHPValue($row['channels'], $platform);
        $modified = Type::getType(CatalogSchema::MODIFIED_TYPE_NAME)->convertToPHPValue($row['modified'], $platform);

        if (
            false === $status instanceof ProductStatus
            || false === $currency instanceof Currency
            || false === \is_array($channels)
            || false === $modified instanceof DateTimeInterface
        ) {
            throw new Exception(\sprintf('product `%d` holds a value its type cannot convert', $productId));
        }

        $salesChannels = [];

        foreach ($channels as $channel) {
            if (false === $channel instanceof SalesChannel) {
                throw new Exception(\sprintf('product `%d` holds a channel that is not a `SalesChannel`', $productId));
            }

            $salesChannels[] = $channel;
        }

        return [
            'name' => (string)$row['name'],
            'status' => $status,
            'currency' => $currency,
            'channels' => $salesChannels,
            'priority' => (int)$row['priority'],
            'modified' => $modified,
        ];
    }
}
