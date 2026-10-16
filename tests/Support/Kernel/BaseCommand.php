<?php
declare(strict_types=1);
/*
 * This file is part of the CitOmni framework.
 * Low overhead, high performance, ready for anything.
 *
 * For more information, visit https://github.com/citomni
 *
 * Copyright (c) 2012-present Lars Grove Mortensen
 * SPDX-License-Identifier: MIT
 *
 * For full copyright, trademark, and license information,
 * please see the LICENSE file distributed with this source code.
 */

namespace CitOmni\Pdf\Tests\Support\Kernel;

use CitOmni\Kernel\Command\ArgvParser;
use CitOmni\Kernel\Command\HelpFormatter;

/**
 * BaseCommand: Test double for CitOmni\Kernel\Command\BaseCommand that captures the output.
 *
 * Gives Commands under test the kernel base's constructor, exit codes, argv handling and
 * option accessors, and keeps every line they write in stdoutLines or stderrLines instead
 * of writing it to the process streams.
 *
 * Rules:
 * - Mirror the kernel base wherever code under test can tell, except where the lines go.
 * - Parse argv with the real ArgvParser and HelpFormatter of citomni/kernel, so options
 *   behave as they do in an app.
 */
abstract class BaseCommand {

	/** Exit code: The command did what it was asked to do. */
	public const SUCCESS = 0;

	/** Exit code: A domain or runtime failure that the command handles. */
	public const FAILURE = 1;

	/** Exit code: A usage error, reported before the command delegates any work. */
	public const USAGE = 2;

	/** @var list<string> Lines written to stdout, without line endings. */
	public array $stdoutLines = [];

	/** @var list<string> Lines written to stderr, without line endings. */
	public array $stderrLines = [];

	/** App double. */
	protected App $app;

	/** @var array<string,mixed> Options of the command's entry in the command map. */
	protected array $options;

	/** Command name as registered in the command map. */
	protected string $commandName;

	/** Command description as registered in the command map. */
	protected string $commandDescription;

	/** @var array<string,mixed> Parsed options by name; empty until run() has parsed argv. */
	private array $parsedOpts = [];


	/**
	 * Keep the app and the command map entry, then run the subclass's init() when it has one.
	 *
	 * @param  App  $app  App double.
	 * @param  string  $commandName  Command name from the command map.
	 * @param  string  $commandDescription  Command description from the command map.
	 * @param  array<string,mixed>  $options  Command options from the command map.
	 * @see \CitOmni\Kernel\Command\BaseCommand::__construct()  The constructor this mirrors.
	 */
	public function __construct(App $app, string $commandName, string $commandDescription, array $options = []) {
		$this->app = $app;
		$this->commandName = $commandName;
		$this->commandDescription = $commandDescription;
		$this->options = $options;

		if (\method_exists($this, 'init')) {
			$this->init();
		}
	}


	/**
	 * Parse argv, validate the input and delegate to execute().
	 *
	 * Behavior:
	 * - Answers --help and -h with the help text on stdout and SUCCESS.
	 * - Reports a parse or validation error with the usage on stderr and USAGE.
	 * - Otherwise returns the exit code of execute().
	 *
	 * Notes:
	 * - Never validates the signature, as the kernel base outside dev mode. A case calls
	 *   ArgvParser::validateSignature() for that.
	 *
	 * @param  list<string>  $argv  Full argv: binary, command name, then the tokens.
	 * @return int  SUCCESS, FAILURE or USAGE.
	 * @see \CitOmni\Kernel\Command\BaseCommand::run()  The behavior this mirrors.
	 */
	final public function run(array $argv = []): int {
		$signature = $this->signature();
		$tokens    = \array_slice($argv, 2);

		if (ArgvParser::wantsHelp($tokens)) {
			$this->stdout(HelpFormatter::format($this->commandName, $this->commandDescription, $signature));

			return self::SUCCESS;
		}

		$result = ArgvParser::parse($tokens, $signature);

		if ($result['error'] !== null) {
			$this->error($result['error']);
			$this->stderr('');
			$this->stderr(HelpFormatter::usage($this->commandName, $signature));

			return self::USAGE;
		}

		$this->parsedOpts = $result['opts'];

		return $this->execute();
	}


	/**
	 * Declare accepted arguments and options.
	 *
	 * @return array<string,array<string,mixed>>
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [];
	}


	/**
	 * Run the command.
	 *
	 * @return int  Exit code.
	 */
	abstract protected function execute(): int;


	/**
	 * Get an option value (raw).
	 *
	 * @param  string  $name  Option name as declared in signature().
	 * @param  mixed  $default  Returned when the parsed options have no entry for the name.
	 * @return mixed
	 * @see \CitOmni\Kernel\Command\BaseCommand::opt()  The behavior this mirrors.
	 */
	protected function opt(string $name, mixed $default = null): mixed {
		return \array_key_exists($name, $this->parsedOpts) ? $this->parsedOpts[$name] : $default;
	}


	/**
	 * Get an option as bool.
	 *
	 * @param  string  $name  Option name as declared in signature().
	 * @return bool
	 * @throws \RuntimeException  When the value is null, which only a defective signature allows.
	 * @see \CitOmni\Kernel\Command\BaseCommand::getBool()  The behavior this mirrors.
	 */
	protected function getBool(string $name): bool {
		$value = $this->opt($name);

		if ($value === null) {
			throw new \RuntimeException("Option --{$name} is null (defective signature - bool options must default to false).");
		}

		return (bool)$value;
	}


	/**
	 * Keep a line as written to stdout.
	 *
	 * @param  string  $line  Text without the line ending.
	 * @return void
	 */
	protected function stdout(string $line): void {
		$this->stdoutLines[] = $line;
	}


	/**
	 * Keep a line as written to stderr.
	 *
	 * @param  string  $line  Text without the line ending.
	 * @return void
	 */
	protected function stderr(string $line): void {
		$this->stderrLines[] = $line;
	}


	/**
	 * Keep an informational message as written to stdout, without colors.
	 *
	 * @param  string  $message  Text without the line ending.
	 * @return void
	 * @see \CitOmni\Kernel\Command\BaseCommand::info()  The behavior this mirrors when stdout is not a terminal.
	 */
	protected function info(string $message): void {
		$this->stdout($message);
	}


	/**
	 * Keep a success message as written to stdout, without colors.
	 *
	 * @param  string  $message  Text without the line ending.
	 * @return void
	 * @see \CitOmni\Kernel\Command\BaseCommand::success()  The behavior this mirrors when stdout is not a terminal.
	 */
	protected function success(string $message): void {
		$this->stdout($message);
	}


	/**
	 * Keep a warning as written to stderr, without colors.
	 *
	 * @param  string  $message  Text without the line ending.
	 * @return void
	 * @see \CitOmni\Kernel\Command\BaseCommand::warning()  The behavior this mirrors when stderr is not a terminal.
	 */
	protected function warning(string $message): void {
		$this->stderr($message);
	}


	/**
	 * Keep an error message as written to stderr, without colors.
	 *
	 * @param  string  $message  Text without the line ending.
	 * @return void
	 * @see \CitOmni\Kernel\Command\BaseCommand::error()  The behavior this mirrors when stderr is not a terminal.
	 */
	protected function error(string $message): void {
		$this->stderr($message);
	}

}
