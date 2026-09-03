<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Test\Functional;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Example\Enum\Currency;
use PrecisionSoft\Doctrine\Type\Example\Enum\ProductStatus;
use PrecisionSoft\Doctrine\Type\Example\Enum\SalesChannel;
use PrecisionSoft\Doctrine\Type\Example\Schema\CatalogSchema;
use PrecisionSoft\Doctrine\Type\Example\Service\CatalogRepository;
use PrecisionSoft\Doctrine\Type\Example\Test\Utility\CatalogDatabase;
use PrecisionSoft\Doctrine\Type\Example\Test\Utility\SkipException;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;

/**
 * the catalogue written and read through every type of the library, on the real engines
 *
 * @internal
 */
final class CatalogRoundTripTest extends TestCase
{
    private ?Connection $connection = null;

    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderEveryEngine')]
    public function testTheCatalogueRoundTripsThroughTheServer(string $environmentVariable): void
    {
        $catalogRepository = $this->createCatalog($this->boot($environmentVariable));
        $modified = new DateTime('2026-09-02 10:15:00');

        $categoryId = $catalogRepository->saveCategory('shoes', 3);
        $productId = $catalogRepository->saveProduct(
            $categoryId,
            'sneaker',
            ProductStatus::active,
            Currency::EUR,
            [SalesChannel::shop, SalesChannel::marketplace],
            -5,
            $modified,
        );

        $product = $catalogRepository->findProduct($productId);

        static::assertSame('sneaker', $product['name']);
        static::assertSame(ProductStatus::active, $product['status']);
        static::assertSame(Currency::EUR, $product['currency']);
        static::assertSame([SalesChannel::shop, SalesChannel::marketplace], $product['channels']);
        static::assertSame(-5, $product['priority']);
        static::assertSame('2026-09-02 10:15:00', $product['modified']->format('Y-m-d H:i:s'));
    }

    /** the portable enum: a native ENUM on MySQL and MariaDB, a CHECK on PostgreSQL - the server refuses in both cases */
    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderEveryEngine')]
    public function testTheServerRefusesAStatusOutsideTheEnum(string $environmentVariable): void
    {
        $this->assertTheServerRefusesAStatusOutsideTheEnum($this->boot($environmentVariable));
    }

    public function testSqliteRefusesAStatusOutsideTheEnumToo(): void
    {
        $this->connection = CatalogDatabase::connectSqlite();

        $this->assertTheServerRefusesAStatusOutsideTheEnum($this->connection);
    }

    /** the tinyint variants validate at conversion time, so the server never sees the value */
    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderMySqlEngine')]
    public function testTheTinyintVariantsRefuseAValueOutsideTheirRangeBeforeTheServer(string $environmentVariable): void
    {
        $catalogRepository = $this->createCatalog($this->boot($environmentVariable));
        $categoryId = $catalogRepository->saveCategory('shoes', 255);

        try {
            $catalogRepository->saveCategory('hats', -1);

            static::fail('an unsigned tinyint accepted -1');
        } catch (InvalidTypeValueException $invalidTypeValueException) {
            static::assertSame('value `-1` is out of TINYINT range (0 to 255)', $invalidTypeValueException->getMessage());
        }

        $this->expectException(InvalidTypeValueException::class);
        $this->expectExceptionMessage('value `200` is out of TINYINT range (-128 to 127)');

        $catalogRepository->saveProduct(
            $categoryId,
            'sneaker',
            ProductStatus::draft,
            Currency::RON,
            [SalesChannel::wholesale],
            200,
            new DateTime('2026-09-02 10:15:00'),
        );
    }

    /** `DateTimeType` with `update`: MySQL rewrites the column on every UPDATE that changes the row */
    #[DataProviderExternal(CatalogDatabase::class, 'dataProviderMySqlEngine')]
    public function testMysqlRewritesTheModifiedColumnOnUpdate(string $environmentVariable): void
    {
        $catalogRepository = $this->createCatalog($this->boot($environmentVariable));
        $productId = $catalogRepository->saveProduct(
            $catalogRepository->saveCategory('shoes', 1),
            'sneaker',
            ProductStatus::active,
            Currency::USD,
            [SalesChannel::shop],
            0,
            new DateTime('2020-01-01 00:00:00'),
        );

        $catalogRepository->renameProduct($productId, 'running shoe');

        $product = $catalogRepository->findProduct($productId);

        static::assertSame('running shoe', $product['name']);
        static::assertNotSame('2020-01-01 00:00:00', $product['modified']->format('Y-m-d H:i:s'));
    }

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            CatalogDatabase::dropCatalog($this->connection);
            $this->connection->close();
            $this->connection = null;
        }

        parent::tearDown();
    }

    private function boot(string $environmentVariable): Connection
    {
        try {
            $this->connection = CatalogDatabase::connect($environmentVariable);
        } catch (SkipException $skipException) {
            static::markTestSkipped($skipException->getMessage());
        }

        return $this->connection;
    }

    private function createCatalog(Connection $connection): CatalogRepository
    {
        CatalogSchema::registerTypes();

        $catalogRepository = new CatalogRepository($connection);
        $catalogRepository->createSchema();

        return $catalogRepository;
    }

    /** a raw insert, past the type: only the server's own constraint can refuse it */
    private function assertTheServerRefusesAStatusOutsideTheEnum(Connection $connection): void
    {
        $this->createCatalog($connection);

        try {
            $connection->insert(CatalogSchema::PRODUCT_TABLE, [
                'category_id' => 1,
                'name' => 'sneaker',
                'status' => 'unknown',
                'currency' => 'EUR',
                'priority' => 0,
                'modified' => '2026-09-02 10:15:00',
            ]);

            static::fail('the server accepted a status outside the enum');
        } catch (DbalException $dbalException) {
            static::assertNotSame('', $dbalException->getMessage());
        }
    }
}
