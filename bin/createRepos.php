<?php
die('Repository creation has already happened; please do not run');

ini_set('display_errors', true);
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('America/Chicago');
require __DIR__ . '/../vendor/autoload.php';

$client = new Zend\Http\Client();
$client->setOptions(array(
    'adapter' => 'Zend\Http\Client\Adapter\Curl',
));

$token   = file_get_contents(__DIR__ . '/../cache/github.token');
$token   = trim($token);

$base    = 'https://api.github.com';
$request = $client->getRequest();
$headers = $request->getHeaders();
$headers->addHeaderLine("Authorization", "token $token");
$headers->addHeaderLine('Accept', 'application/json');

$repos = array(
    'Component_ZendAuthentication',
    'Component_ZendBarcode',
    'Component_ZendCache',
    'Component_ZendCaptcha',
    'Component_ZendCode',
    'Component_ZendConfig',
    'Component_ZendConsole',
    'Component_ZendCrypt',
    'Component_ZendDb',
    'Component_ZendDebug',
    'Component_ZendDi',
    'Component_ZendDom',
    'Component_ZendEscaper',
    'Component_ZendEventManager',
    'Component_ZendFeed',
    'Component_ZendFile',
    'Component_ZendFilter',
    'Component_ZendForm',
    'Component_ZendHttp',
    'Component_ZendI18n',
    'Component_ZendInputFilter',
    'Component_ZendJson',
    'Component_ZendLdap',
    'Component_ZendLoader',
    'Component_ZendLog',
    'Component_ZendMail',
    'Component_ZendMath',
    'Component_ZendMemory',
    'Component_ZendMime',
    'Component_ZendModuleManager',
    'Component_ZendMvc',
    'Component_ZendNavigation',
    'Component_ZendPaginator',
    'Component_ZendPermissionsAcl',
    'Component_ZendPermissionsRbac',
    'Component_ZendProgressbar',
    'Component_ZendSerializer',
    'Component_ZendServer',
    'Component_ZendServiceManager',
    'Component_ZendSession',
    'Component_ZendSoap',
    'Component_ZendStdlib',
    'Component_ZendTag',
    'Component_ZendTest',
    'Component_ZendText',
    'Component_ZendUri',
    'Component_ZendValidator',
    'Component_ZendVersion',
    'Component_ZendView',
    'Component_ZendXmlRpc',
);

foreach ($repos as $repo) {
    echo "Creating repository $repo\n";

    $component = preg_replace('/Component_Zend/', '', $repo);
    $payload   = json_encode(array(
        'name'        => $repo,
        'description' => $component . ' component from Zend Framework 2',
        'homepage'    => 'https://github.com/zendframework/zf2',
        'has_issues'  => false,
        'has_wiki'    => false,
    ));
    $client->setUri($base . '/user/repos');
    $client->setMethod('POST');
    $client->setRawBody($payload);
    $client->setEncType('application/json');

    $response = $client->send();
    $body     = $response->getBody();
    $body     = json_decode($body);
    if (!$response->isOk()) {
        printf("Error creating repo '%s': %s\n%s", $repo, $response->getStatusCode(), var_export($body, 1));
        continue;
    }
}
