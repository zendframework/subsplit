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
chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

$token  = file_get_contents(realpath(getcwd()) . '/cache/github.token');
$token  = trim($token);

$branches = ['master', 'develop'];

$client = new Zend\Http\Client();
$client->setOptions(array(
    'adapter' => 'Zend\Http\Client\Adapter\Curl',
));

$base    = 'https://api.github.com';
$request = $client->getRequest();
$headers = $request->getHeaders();
$headers->addHeaderLine("Authorization", "token $token");
$headers->addHeaderLine('Accept', 'application/json');

foreach ($branches as $branch) {
    $lastUpdateSha = getLastSha($branch);
    $response = queryGithub($base . '/repos/zendframework/zf2/branches/' . $branch, $client);
    $currentSha = $response->commit->sha;
    if ($currentSha === $lastUpdateSha) {
        echo "No updates found on $branch\n";
        continue;
    }

    $diff         = queryGithub(sprintf('%s/repos/zendframework/zf2/compare/%s...%s', $base, $lastUpdateSha, $currentSha), $client);
    $components   = getComponentsFromDiff($diff);
    $subsplitList = createSubsplitList($components);

    echo "Performing subtree split on branch $branch...\n";
    $command = sprintf(
        '/usr/local/bin/git subsplit publish "%s" --update --heads="%s" --no-tags 2>&1 | tee publish.log',
        implode(' ', $subsplitList),
        $branch
    );
    system($command);
    echo "\nDONE\n";

    echo "Removing subtree cache...\n";
    $command = sprintf('rm -rf %s/.subsplit/.git/subtree-cache', realpath(getcwd()));
    system($command);
    echo "\nDONE\n";

    echo "Updating last update SHA...";
    updateLastSha($branch, $currentSha);
    echo "DONE\n";
}

function queryGithub($uri, $client)
{
    $client->setUri($uri);
    $client->setMethod('GET');
    $response = $client->send();
    $body = $response->getBody();
    $payload = json_decode($body);
    if (!$response->isOk()) {
        printf("Error requesting %s (status code %s):\n%s", $uri, $response->getStatusCode(), var_export($payload, 1));
        exit(2);
    }
    return $payload;
}

function getLastSha($branch)
{
    $lastUpdateShaFile = __DIR__ . '/../cache/' . $branch . '.sha';
    if (!file_exists($lastUpdateShaFile)) {
        echo "Unable to find last cache file with last update SHA for $branch; please seed this file and re-run";
        exit(2);
    }
    $lastUpdateSha = file_get_contents($lastUpdateShaFile);
    $lastUpdateSha = trim($lastUpdateSha);
    return $lastUpdateSha;
}

function updateLastSha($branch, $sha)
{
    $lastUpdateShaFile = __DIR__ . '/../cache/' . $branch . '.sha';
    file_put_contents($lastUpdateShaFile, $sha);
}

function getComponentsFromDiff($diff)
{
    $components = [];
    foreach ($diff->files as $fileinfo) {
        $filename = $fileinfo->filename;
        if (!preg_match('#^library/Zend/(?P<component>[^/]+)/(?P<subcomponent>[^/.]+)#', $filename, $matches)) {
            continue;
        }

        $component    = $matches['component'];
        $subcomponent = $matches['subcomponent'];

        if ($component == 'Permissions') {
            $component = printf('%s/%s', $component, $subcomponent);
        }

        if (in_array($component, $components)) {
            continue;
        }
        $components[] = $component;
    }
    return $components;
}

function createSubsplitList($components)
{
    $subsplits = [];
    foreach ($components as $component) {
        $subsplits[] = sprintf(
            'library/Zend/%s:git@github.com:zendframework/Component_Zend%s.git',
            $component,
            str_replace('/', '', $component)
        );
    }
    return $subsplits;
}
