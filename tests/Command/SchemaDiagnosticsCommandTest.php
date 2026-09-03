<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Test\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrecisionSoft\Doctrine\Type\Command\SchemaDiagnosticsCommand;
use PrecisionSoft\Doctrine\Type\Exception\Exception;
use PrecisionSoft\Doctrine\Type\Schema\Diagnostic;
use PrecisionSoft\Doctrine\Type\Schema\SchemaDiagnostics;
use PrecisionSoft\Doctrine\Type\Test\Utility\StubSchemaDiagnostics;
use PrecisionSoft\Doctrine\Type\Test\Utility\TestSchemaDiagnosticsCommand;

/** @internal */
final class SchemaDiagnosticsCommandTest extends TestCase
{
    private const USAGE = "usage: doctrine-type-diagnose <database-url>\n";

    /** @var resource */
    private $standardOutput;

    /** @var resource */
    private $standardError;

    /** @return iterable<string, array{list<string>}> */
    public static function dataProviderHelpArgumentList(): iterable
    {
        yield 'long flag' => [['doctrine-type-diagnose', '--help']];
        yield 'short flag' => [['doctrine-type-diagnose', '-h']];
        yield 'flag after the url' => [['doctrine-type-diagnose', 'sqlite:///:memory:', '--help']];
    }

    public function testAMissingUrlPrintsTheUsageOnStandardErrorAndExitsWithTheUsageCode(): void
    {
        $exitCode = $this->createCommand()->run(['doctrine-type-diagnose']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_USAGE, $exitCode);
        static::assertSame('', $this->readStandardOutput());
        static::assertSame(static::USAGE, $this->readStandardError());
    }

    public function testAnEmptyArgumentListIsAMissingUrl(): void
    {
        static::assertSame(SchemaDiagnosticsCommand::EXIT_USAGE, $this->createCommand()->run([]));
        static::assertSame(static::USAGE, $this->readStandardError());
    }

    /** @param list<string> $argumentList */
    #[DataProvider('dataProviderHelpArgumentList')]
    public function testHelpPrintsTheUsageOnStandardOutputAndExitsCleanly(array $argumentList): void
    {
        $testSchemaDiagnosticsCommand = $this->createCommand();

        static::assertSame(SchemaDiagnosticsCommand::EXIT_SUCCESS, $testSchemaDiagnosticsCommand->run($argumentList));
        static::assertSame(static::USAGE, $this->readStandardOutput());
        static::assertSame('', $this->readStandardError());
        static::assertSame([], $testSchemaDiagnosticsCommand->receivedDatabaseUrls, 'help must not open a connection');
    }

    public function testMoreThanOneUrlIsAUsageError(): void
    {
        $testSchemaDiagnosticsCommand = $this->createCommand();

        $exitCode = $testSchemaDiagnosticsCommand->run(['doctrine-type-diagnose', 'sqlite:///a.db', 'sqlite:///b.db']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_USAGE, $exitCode);
        static::assertSame(static::USAGE, $this->readStandardError());
        static::assertSame([], $testSchemaDiagnosticsCommand->receivedDatabaseUrls);
    }

    public function testEveryDiagnosticIsOneTabSeparatedLineAndTheExitCodeSaysSomethingWasFound(): void
    {
        $testSchemaDiagnosticsCommand = $this->createCommand(new StubSchemaDiagnostics(true, [
            new Diagnostic('orders', 'status', 'enum', Diagnostic::SEVERITY_WARNING, 'first message'),
            new Diagnostic('orders', 'flags', 'tinyint', Diagnostic::SEVERITY_WARNING, 'second message'),
        ]));

        $exitCode = $testSchemaDiagnosticsCommand->run(['doctrine-type-diagnose', 'mysql://root@mysql/test']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_DIAGNOSTICS_FOUND, $exitCode);
        static::assertSame(
            "warning\torders.status\tenum\tfirst message\nwarning\torders.flags\ttinyint\tsecond message\n",
            $this->readStandardOutput(),
        );
        static::assertSame('', $this->readStandardError());
        static::assertSame(['mysql://root@mysql/test'], $testSchemaDiagnosticsCommand->receivedDatabaseUrls);
        static::assertNotNull($testSchemaDiagnosticsCommand->lastConnection);
        static::assertFalse($testSchemaDiagnosticsCommand->lastConnection->isConnected(), 'the connection must be closed');
    }

    public function testACleanSchemaPrintsNothingAndExitsWithSuccess(): void
    {
        $exitCode = $this->createCommand(new StubSchemaDiagnostics(true, []))
            ->run(['doctrine-type-diagnose', 'mysql://root@mysql/test']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_SUCCESS, $exitCode);
        static::assertSame('', $this->readStandardOutput());
        static::assertSame('', $this->readStandardError());
    }

    /** the stub would report a diagnostic, so the empty output proves the command stopped at the note */
    public function testAnUnsupportedPlatformIsANoteNotASilentSuccess(): void
    {
        $exitCode = $this->createCommand(new StubSchemaDiagnostics(false, [
            new Diagnostic('orders', 'status', 'enum', Diagnostic::SEVERITY_WARNING, 'never printed'),
        ]))->run(['doctrine-type-diagnose', 'sqlite:///:memory:']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_SUCCESS, $exitCode);
        static::assertSame('', $this->readStandardOutput());
        static::assertSame(
            "note: this platform has no introspection query, nothing was inspected\n",
            $this->readStandardError(),
        );
    }

    public function testAFailureIsReportedOnStandardErrorWithTheFailureCode(): void
    {
        $testSchemaDiagnosticsCommand = $this->createCommand(
            new StubSchemaDiagnostics(true, [], new Exception('the schema is unreadable')),
        );

        $exitCode = $testSchemaDiagnosticsCommand->run(['doctrine-type-diagnose', 'mysql://root@mysql/test']);

        static::assertSame(SchemaDiagnosticsCommand::EXIT_FAILURE, $exitCode);
        static::assertSame('', $this->readStandardOutput());
        static::assertSame("error: the schema is unreadable\n", $this->readStandardError());
        static::assertNotNull($testSchemaDiagnosticsCommand->lastConnection);
        static::assertFalse(
            $testSchemaDiagnosticsCommand->lastConnection->isConnected(),
            'the connection must be closed after a failure too',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->standardOutput = $this->openMemoryStream();
        $this->standardError = $this->openMemoryStream();
    }

    protected function tearDown(): void
    {
        \fclose($this->standardOutput);
        \fclose($this->standardError);

        parent::tearDown();
    }

    private function createCommand(?SchemaDiagnostics $schemaDiagnostics = null): TestSchemaDiagnosticsCommand
    {
        return new TestSchemaDiagnosticsCommand(
            $schemaDiagnostics ?? new StubSchemaDiagnostics(),
            $this->standardOutput,
            $this->standardError,
        );
    }

    /** @return resource */
    private function openMemoryStream()
    {
        $stream = \fopen('php://memory', 'w+');

        if (false === $stream) {
            throw new Exception('cannot open a memory stream');
        }

        return $stream;
    }

    private function readStandardOutput(): string
    {
        return $this->readStream($this->standardOutput);
    }

    private function readStandardError(): string
    {
        return $this->readStream($this->standardError);
    }

    /** @param resource $stream */
    private function readStream($stream): string
    {
        \rewind($stream);

        return (string)\stream_get_contents($stream);
    }
}
