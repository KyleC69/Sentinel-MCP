<?php
require __DIR__ . '/tests/bootstrap.php';
putenv('ANTHROPIC_API_KEY=sk-test');
$r = \WordPress\AiClient\AiClient::defaultRegistry();
var_dump($r->isProviderConfigured('anthropic'));
var_dump($r->getProviderClassName('anthropic'));
$class = $r->getProviderClassName('anthropic');
$provider_id = 'mock';
if (str_contains($class, '::')) {
    [$class, $provider_id] = explode('::', $class, 2);
}
var_dump($class);
var_dump($provider_id);
$p = new $class([], $provider_id);
var_dump($p->getAuthentication());
var_dump(getenv('ANTHROPIC_API_KEY'));
var_dump(\SentinelMCP\Chat_Engine::has_api_key('anthropic'));
