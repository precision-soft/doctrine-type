<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Exception;

use Exception as BaseException;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Exception\InvalidTypeValueException;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

class ExceptionTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testExceptionExtendsBaseException(): void
    {
        $exception = new Exception('test message');

        self::assertInstanceOf(BaseException::class, $exception);
        self::assertSame('test message', $exception->getMessage());
    }

    public function testInvalidTypeValueExceptionExtendsException(): void
    {
        $invalidTypeValueException = new InvalidTypeValueException('invalid value');

        self::assertInstanceOf(Exception::class, $invalidTypeValueException);
        self::assertInstanceOf(BaseException::class, $invalidTypeValueException);
        self::assertSame('invalid value', $invalidTypeValueException->getMessage());
    }
}
