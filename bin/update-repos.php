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

// GitHub token is required
$tokenPath = realpath(getcwd()) . '/cache/github.token';
if (!file_exists($tokenPath)) {
    exitWithError(
        "Missing github token file; please place your github token in '%s'.",
        [$tokenPath]
    );
}
$token  = file_get_contents($tokenPath);
$token  = trim($token);

// Check to see if an alternate path to the git executable has been provided
$git     = 'git';
$gitPath = realpath(getcwd()) . '/cache/git.path';
if (file_exists($gitPath)) {
    $git = file_get_contents($gitPath);
    $git = trim($git);
}

// Don't run at all if subtree-cache dir is still around; means another job is 
// running, or failed previously
$subtreeCacheDir = realpath(getcwd()) . '/.subsplit/.git/subtree-cache';
if (file_exists($subtreeCacheDir) && is_dir($subtreeCacheDir)) {
    exitWithError("Previous execution is still running or failed before cleanup.");
}

// Initialize HTTP client
// - set authorization token
// - set accept header
$client = new Zend\Http\Client();
$client->setOptions(array(
    'adapter' => 'Zend\Http\Client\Adapter\Curl',
));
$request = $client->getRequest();
$headers = $request->getHeaders();
$headers->addHeaderLine("Authorization", "token $token");
$headers->addHeaderLine('Accept', 'application/json');

// Setup base URI for GitHub API requests
$base    = 'https://api.github.com';

// For each branch, do the work
foreach (['master', 'develop'] as $branch) {
    // Get SHA from last run, as well as most recent SHA for branch from GitHub
    $lastUpdateSha = getLastSha($branch);
    $response      = queryGithub($base . '/repos/zendframework/zf2/branches/' . $branch, $client);
    $currentSha    = $response->commit->sha;
    if ($currentSha === $lastUpdateSha) {
        // Most current is same as last run; nothing to do on this branch
        emitMessage('No updates found on %s', [$branch]);
        continue;
    }

    // Get a list of components affected by commits since the last update, and 
    // build the list of components for which to update subsplits
    $diff         = queryGithub(sprintf('%s/repos/zendframework/zf2/compare/%s...%s', $base, $lastUpdateSha, $currentSha), $client);
    $components   = getComponentsFromDiff($diff);
    $subsplitList = createSubsplitList($components);

    // No components found? (e.g., only tests were changed)
    // Done with this branch.
    if (empty($subsplitList)) {
        emitMessage('No updates found on %s', [$branch]);
        continue;
    }

    emitMessage('Performing subtree split on branch %s...', [$branch]);
    performSubsplit($branch, $subsplitList, $git);
    emitMessage('DONE (performing subtree split)');

    emitMessage('Updating last update SHA on branch %s to %s', [$branch, $currentSha]);
    updateLastSha($branch, $currentSha);
    emitMessage('DONE (updating last update SHA)');
}

/**
 * Query the GitHub API
 *
 * Exits with status 2 if the query fails, and echos an error message.
 * 
 * @param  string $uri 
 * @param  Zend\Http\Client $client 
 * @return array
 */
function queryGithub($uri, $client)
{
    $client->setUri($uri);
    $client->setMethod('GET');
    $response = $client->send();
    $body = $response->getBody();
    $payload = json_decode($body);
    if (!$response->isOk()) {
        exitWithError(
            "Error requesting %s (status code %s):\n%s",
            [$uri, $response->getStatusCode(), var_export($payload, 1)]
        );
    }
    return $payload;
}

/**
 * Retrieve the SHA from the previous job execution
 *
 * Looks in the "cache/{branch}.sha" for a SHA file, returning the SHA when
 * found. If none is found, exits with status 2 and echos an error message.
 * 
 * @param  string $branch 
 * @return string
 */
function getLastSha($branch)
{
    $lastUpdateShaFile = __DIR__ . '/../cache/' . $branch . '.sha';
    if (!file_exists($lastUpdateShaFile)) {
        exitWithError(
            "Unable to find last cache file with last update SHA for %s; please seed this file and re-run.",
            [$branch]
        );
    }
    $lastUpdateSha = file_get_contents($lastUpdateShaFile);
    $lastUpdateSha = trim($lastUpdateSha);
    return $lastUpdateSha;
}

/**
 * Writes the current SHA to a cache file
 *
 * Writes the SHA for the branch to "cache/{branch}.sha".
 * 
 * @param  string $branch 
 * @param  string $sha 
 */
function updateLastSha($branch, $sha)
{
    $lastUpdateShaFile = __DIR__ . '/../cache/' . $branch . '.sha';
    file_put_contents($lastUpdateShaFile, $sha);
}

/**
 * Retrieve a list of affected components from a GitHub diff response
 *
 * Loops through the "files" member of a GitHub diff; if the "filename" member
 * of any given file is a file inside the library, it determines the component
 * affected, and adds it to the list it returns.
 * 
 * @param  stdClass $diff 
 * @return array
 */
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

/**
 * Creates a list of component/repository strings for use with git subsplit
 * 
 * @param  array $components 
 * @return array
 */
function createSubsplitList(array $components)
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

/**
 * Executes a subsplit
 *
 * Given a branch, a list of components, and the git executable, executes a git
 * subsplit, and cleans up after itself when done.
 *
 * A message with the timestamp and the command executed is echo'd for each of
 * the subsplit performed as well as the "rm -rf" executed on the subtree-cache
 * when complete.
 *
 * If either command returns a non-zero status, an error message is echo'd, and
 * the script will exit with a status of 2.
 * 
 * @param string $branch 
 * @param array $subsplitList 
 * @param string $git 
 */
function performSubsplit($branch, $subsplitList, $git)
{
    $return  = 0;
    $command = sprintf(
        '%s subsplit publish "%s" --update --heads="%s" --no-tags 2>&1',
        $git,
        implode(' ', $subsplitList),
        $branch
    );

    emitMessage("EXECUTING:\n%s", [$command]);
    passthru($command, $return);
    if (0 != $return) {
        exitWithError(
            "Error executing subsplit\nCommand executed: %s\n\nReturn value: %s\n\nSubtree cache was NOT flushed.",
            [$command, $return]
        );
    }

    $command = sprintf('rm -rf %s/.subsplit/.git/subtree-cache', realpath(getcwd()));
    emitMessage("EXECUTING:\n%s", [$command]);
    passthru($command);

    if (0 != $return) {
        exitWithError(
            "Error flushing subtree cache; return status was '%s'.",
            [$return]
        );
    }
}

/**
 * Present a message
 * 
 * @param string $message 
 * @param array $params 
 */
function emitMessage($message, array $params = [])
{
    $message = '[%s] ' . $message . "\n";
    array_unshift($params, date('Y-m-d H:i:s'));
    vprintf($message, $params);
}

/**
 * Exit with error status 2, and provide a message
 * 
 * @param string $message 
 * @param array $params 
 */
function exitWithError($message, array $params = [])
{
    emitMessage($message, $params);
    exit(2);
}
