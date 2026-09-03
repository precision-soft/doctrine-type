<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Example\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PrecisionSoft\Doctrine\Type\DateTimeType;
use PrecisionSoft\Doctrine\Type\Example\Type\CurrencyType;
use PrecisionSoft\Doctrine\Type\Example\Type\ProductStatusType;
use PrecisionSoft\Doctrine\Type\Example\Type\SalesChannelSetType;
use PrecisionSoft\Doctrine\Type\SignedTinyintType;
use PrecisionSoft\Doctrine\Type\UnsignedTinyintType;

/** the two tables of the catalogue, declared once and built for whatever platform the connection has */
class CatalogSchema
{
    public const CATEGORY_TABLE = 'category';

    public const PRODUCT_TABLE = 'product';

    /** a reserved word, declared quoted: DBAL keeps the quotes wherever it writes the name, the types follow it */
    public const CATEGORY_ORDER_COLUMN = '"order"';

    public const MODIFIED_TYPE_NAME = 'catalogDateTime';

    /** guarded because the type registry is global to the process */
    public static function registerTypes(): void
    {
        $typeClasses = [
            ProductStatusType::getDefaultName() => ProductStatusType::class,
            CurrencyType::getDefaultName() => CurrencyType::class,
            SalesChannelSetType::getDefaultName() => SalesChannelSetType::class,
            SignedTinyintType::getDefaultName() => SignedTinyintType::class,
            UnsignedTinyintType::getDefaultName() => UnsignedTinyintType::class,
            static::MODIFIED_TYPE_NAME => DateTimeType::class,
        ];

        foreach ($typeClasses as $typeName => $typeClass) {
            if (false === Type::hasType($typeName)) {
                Type::addType($typeName, $typeClass);
            }
        }
    }

    public function build(AbstractPlatform $platform): Schema
    {
        $schema = new Schema();

        $category = $schema->createTable(static::CATEGORY_TABLE);
        $category->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $category->addColumn('name', Types::STRING, ['length' => 64]);
        $category->addColumn(static::CATEGORY_ORDER_COLUMN, $this->getUnsignedTinyintTypeName($platform));
        $this->setPrimaryKey($category);

        $product = $schema->createTable(static::PRODUCT_TABLE);
        $product->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $product->addColumn('category_id', Types::INTEGER);
        $product->addColumn('name', Types::STRING, ['length' => 128]);
        $product->addColumn('status', ProductStatusType::getDefaultName(), ['length' => 32]);
        $product->addColumn('currency', CurrencyType::getDefaultName(), ['length' => 3]);
        $product->addColumn('channels', SalesChannelSetType::getDefaultName(), ['length' => 64, 'notnull' => false]);
        $product->addColumn('priority', $this->getSignedTinyintTypeName($platform));
        $product->addColumn('modified', static::MODIFIED_TYPE_NAME)->setPlatformOptions(['update' => true]);
        $this->setPrimaryKey($product);

        return $schema;
    }

    /** the tinyint types declare MySQL columns only; elsewhere the catalogue falls back to the platform's small integer */
    public function getSignedTinyintTypeName(AbstractPlatform $platform): string
    {
        return true === $platform instanceof AbstractMySQLPlatform ? SignedTinyintType::getDefaultName() : Types::SMALLINT;
    }

    public function getUnsignedTinyintTypeName(AbstractPlatform $platform): string
    {
        return true === $platform instanceof AbstractMySQLPlatform ? UnsignedTinyintType::getDefaultName() : Types::SMALLINT;
    }

    protected function setPrimaryKey(Table $table): void
    {
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
    }
}
