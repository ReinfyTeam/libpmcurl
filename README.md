# libpmcurl
Curl library for Axolotl-PM using curl_multi

# Requirements

- PHP `^8.4`.
- Axolotl-PM API `5.0.0` or newer.
- PHP extension `ext-curl`.

CI analyzes PHP `8.4` and `8.5`. It builds the virion PHAR with AxolotlBucketLabs DevTools `4.1.2`.

# Initialization

libpmcurl uses SingletonTrait so it it automatically initialized when `Curl::getInstance()` is called for the first time

# Usage

GET requests can be done with `Curl::getInstance()->getRequest("https://example.com", function(?InternetRequestResult $result, ?string $error) {})` and POST requests can be done with `Curl::getInstance()->postRequest("https://example.com", function(?InternetRequestResult $result, ?string $error) {}, ["headers"], "body")`

# Examples

Basic example:
```php
public function onEnable() : void {
	Curl::getInstance()->getRequest("https://example.com", function(?InternetRequestResult $result, ?string $error) : void {
		var_dump($result, $error);
	});
}
```

Discord webhook example:

```php
class Main extends PluginBase implements Listener {
	public function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onChat(PlayerChatEvent $event) : void {
		Curl::getInstance()->postRequest("https://discord.com/api/webhooks/WEBHOOK_ID", function() : void {}, [], ["content" => $event->getMessage(), "username" => $event->getPlayer()->getName()]);
	}
}
```
# Why use over libasynCurl

Libasyncurl handles requests by creating an async task for every curl request. This means that a thread can only process one curl request at a time. This results in performance loss when doing multiple requests at once, as the threads are only doing one at a time. This library uses curl_multi on a single thread meaning the thread can process multiple requests at once, resulting in much better performance when sending multiple requests at a time.
