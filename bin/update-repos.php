<?php
/**
 * Update component repos for ZF2.
 *
 * Algorithm:
 *
 * - For each of master/develop:
 *   - Get commit from last update
 *   - Get most recent commit
 *     https://api.github.com/repos/:owner/:repo/branches/:branch
 *     "commit.sha"
 *   - Get diff between commits
 *     https://api.github.com/repos/:owner/:repo/compare/:base...:head
 *   - loop through files, retrieving filename object
 *     - toss anything not in "library"
 *   - build component list from files
 *   - chdir to subsplit dir
 *   - exec /usr/local/bin/git subsplit publish "
 *         library/Zend/{COMPONENT}:git@github.com:zendframework/Component_Zend{COMPONENT}.git
 *         ...
 *     " --update --heads="{BRANCH}" --no-tags
 *   - rm -rf .subsplit/.git/subtree-cache
 */

ini_set('display_errors', true);
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('America/Chicago');
require __DIR__ . '/../vendor/autoload.php';

$token  = include __DIR__ . '/cache/github.token';
$token  = trim($token);

$client = new Zend\Http\Client();
$client->setOptions(array(
    'adapter' => 'Zend\Http\Client\Adapter\Curl',
));

$base    = 'https://api.github.com';
$request = $client->getRequest();
$headers = $request->getHeaders();
$headers->addHeaderLine("Authorization", "token $token");
$headers->addHeaderLine('Accept', 'application/json');

