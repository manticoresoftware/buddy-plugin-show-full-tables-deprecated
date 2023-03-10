<?php declare(strict_types=1);

/*
 Copyright (c) 2023, Manticore Software LTD (https://manticoresearch.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License version 2 or any later
 version. You should have received a copy of the GPL license along with this
 program; if you did not, you can find it at http://www.gnu.org/
 */

namespace Manticoresearch\Buddy\Plugin\ShowFullTables;

use Manticoresearch\Buddy\Core\ManticoreSearch\Client as HTTPClient;
use Manticoresearch\Buddy\Core\Plugin\BaseHandlerWithTableFormatter;
use Manticoresearch\Buddy\Core\Plugin\TableFormatter;
use Manticoresearch\Buddy\Core\Task\Task;
use Manticoresearch\Buddy\Core\Task\TaskResult;
use RuntimeException;
use parallel\Runtime;

/**
 * This is the parent class to handle erroneous Manticore queries
 */
class Handler extends BaseHandlerWithTableFormatter {
	/**
	 *  Initialize the executor
	 *
	 * @param Payload $payload
	 * @return void
	 */
	public function __construct(public Payload $payload) {
	}

	/**
	 * Process the request and return self for chaining
	 *
	 * @return Task
	 * @throws RuntimeException
	 */
	public function run(Runtime $runtime): Task {
		$this->manticoreClient->setPath($this->payload->path);

		// We run in a thread anyway but in case if we need blocking
		// We just waiting for a thread to be done
		$taskFn = static function (
			Payload $payload,
			HTTPClient $manticoreClient,
			TableFormatter $tableFormatter
		): TaskResult {
			$time0 = hrtime(true);
			// First, get response from the manticore
			$query = 'SHOW TABLES';
			if ($payload->like) {
				$query .= " LIKE '{$payload->like}'";
			}
			$resp = $manticoreClient->sendRequest($query);
			/** @var array<int,array{error:string,data:array<int,array<string,string>>,total?:int,columns?:string}> $result */
			$result = $resp->getResult();
			$total = $result[0]['total'] ?? -1;
			if ($payload->hasCliEndpoint) {
				return new TaskResult($tableFormatter->getTable($time0, $result[0]['data'], $total));
			}
			return new TaskResult($result);
		};

		return Task::createInRuntime(
			$runtime, $taskFn, [$this->payload, $this->manticoreClient, $this->tableFormatter]
		)->run();
	}
}
