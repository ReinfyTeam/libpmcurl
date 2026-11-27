<?php

declare(strict_types=1);

namespace ReinfyTeam\libpmcurl;

class CurlRequest {
	public function __construct(
		public string $url,
		/** @var array<string, string> */
		public array $headers = [],
		public int $timeout = 10,
		/** @var array<int, mixed> */
		public array $extraOpts = [],
		public int $id = 0,
	) {
		if ($url === "" ) {
			throw new \InvalidArgumentException('Curl URL must not be empty');
		}
	}
}
