<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Exception;

use Doctrine\DBAL\Exception as DoctrineDbalException;
use Exception as BaseException;
use PrecisionSoft\Doctrine\Type\Contract\ExceptionInterface;
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

    public function testExceptionImplementsBothMarkerInterfaces(): void
    {
        $exception = new Exception('test message');

        static::assertInstanceOf(DoctrineDbalException::class, $exception);
        static::assertInstanceOf(ExceptionInterface::class, $exception);
        static::assertInstanceOf(ExceptionInterface::class, new InvalidTypeValueException('invalid value'));
    }

    public function testContextDefaultsToAnEmptyArray(): void
    {
        static::assertSame([], (new Exception('test message'))->getContext());
        static::assertSame([], (new Exception('test message', 0, null, null))->getContext());
    }

    public function testContextIsReadBackFromTheConstructor(): void
    {
        $invalidTypeValueException = new InvalidTypeValueException(
            'invalid value',
            0,
            null,
            ['typeName' => 'php_enum', 'value' => 'unknown'],
        );

        static::assertSame(['typeName' => 'php_enum', 'value' => 'unknown'], $invalidTypeValueException->getContext());
    }

    public function testSetContextReplacesTheContextAndIsFluent(): void
    {
        $exception = new Exception('test message', 0, null, ['first' => 1]);

        static::assertSame($exception, $exception->setContext(['second' => 2]));
        static::assertSame(['second' => 2], $exception->getContext());

        $exception->setContext(null);

        static::assertSame([], $exception->getContext());
    }

    public function testTheContextDoesNotLeakIntoTheMessageCodeOrPrevious(): void
    {
        $previousException = new BaseException('root cause');

        $exception = new Exception('test message', 7, $previousException, ['key' => 'value']);

        static::assertSame('test message', $exception->getMessage());
        static::assertSame(7, $exception->getCode());
        static::assertSame($previousException, $exception->getPrevious());
    }

    public function testTheConstructorDefaultsToAnEmptyMessageZeroCodeAndNoPrevious(): void
    {
        $exception = new Exception();

        static::assertSame('', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
        static::assertSame([], $exception->getContext());
    }
}
