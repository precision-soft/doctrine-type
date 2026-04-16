<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Exception;

use Doctrine\DBAL\Exception as DoctrineDbalException;
use Exception as BaseException;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

/** @internal */
final class ExceptionTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testExceptionImplementsDoctrineDbalException(): void
    {
        $exception = new Exception('test message');

        static::assertInstanceOf(DoctrineDbalException::class, $exception);
    }

    public function testExceptionExtendsBaseException(): void
    {
        $exception = new Exception('test message');

        static::assertInstanceOf(BaseException::class, $exception);
        static::assertSame('test message', $exception->getMessage());
    }

    public function testInvalidTypeValueExceptionExtendsException(): void
    {
        $invalidTypeValueException = new InvalidTypeValueException('invalid value');

        static::assertInstanceOf(Exception::class, $invalidTypeValueException);
        static::assertInstanceOf(BaseException::class, $invalidTypeValueException);
        static::assertSame('invalid value', $invalidTypeValueException->getMessage());
    }
}
