<?php

declare(strict_types=1);

namespace gameparrot\libpmcurl;

use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\SnoozeAwarePthreadsChannelWriter;
use function count;
use function curl_close;
use function curl_getinfo;
use function curl_init;
use function curl_multi_add_handle;
use function curl_multi_exec;
use function curl_multi_getcontent;
use function curl_multi_info_read;
use function curl_multi_init;
use function curl_multi_remove_handle;
use function curl_setopt;
use function curl_setopt_array;
use function igbinary_serialize;
use function igbinary_unserialize;
use function spl_object_id;
use const CURLINFO_HEADER_SIZE;
use const CURLINFO_HTTP_CODE;
use const CURLOPT_AUTOREFERER;
use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_FORBID_REUSE;
use const CURLOPT_FRESH_CONNECT;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_URL;

class CurlWorker {
	private \CurlMultiHandle $curlHandler;
	/** @var int[] */
	private array $requests = [];

	public function __construct(
		private PthreadsChannelReader $reader,
		private SnoozeAwarePthreadsChannelWriter $writer,
	) {
		$this->curlHandler = curl_multi_init();
	}

	public function doRequest(CurlRequest $req) : void {
		$handle = curl_init();
		$curlHeaders = [];
		foreach ($req->headers as $key => $value) {
			$curlHeaders[] = $key . ": " . $value;
		}
		curl_setopt($handle, CURLOPT_URL, $req->url);
		curl_setopt_array($handle, $req->extraOpts + [
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FORBID_REUSE => 1,
			CURLOPT_FRESH_CONNECT => 1,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT_MS => $req->timeout * 1000,
			CURLOPT_TIMEOUT_MS => $req->timeout * 1000,
			CURLOPT_HTTPHEADER => $curlHeaders,
			CURLOPT_HEADER => true,
		]);
		$this->requests[spl_object_id($handle)] = $req->id;
		curl_multi_add_handle($this->curlHandler, $handle);
	}

	public function tick() : void {
		while ($in = $this->reader->read()) {
			/** @var CurlRequest */
			$request = igbinary_unserialize($in);
			$this->doRequest($request);
		}

		if (count($this->requests) > 0) {
			curl_multi_exec($this->curlHandler, $active);
			do {
				$numMessages = 0;
				$status = curl_multi_info_read($this->curlHandler, $numMessages);
				if ($status === false) {
					continue;
				}
				if (isset($status["handle"])) {
					$curlHandle = $status["handle"];
					$requestId = $this->requests[spl_object_id($curlHandle)];

					$raw = curl_multi_getcontent($curlHandle);
					if ($raw === null) {
						$raw = '';
					}

					$result = new CurlResult($requestId, $status["result"], curl_getinfo($curlHandle, CURLINFO_HTTP_CODE), curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE), $raw);

					$serialized = igbinary_serialize($result);
					if ($serialized === null) {
						throw new \RuntimeException('Unable to serialize curl result');
					}

					$this->writer->write($serialized);

					curl_multi_remove_handle($this->curlHandler, $curlHandle);
					curl_close($curlHandle);
					unset($this->requests[spl_object_id($curlHandle)]);
				}
			} while ($numMessages > 0);
		}
	}
}
