ZF2 Subsplits
=============

This repository provides functionality for creating and updating individual
component repositories based off the master ZF2 repository.

You will need [git-subsplit](http://github.com/dflydev/git-subsplit) in order to
use any of the scripts, as well as a PHP version &gt;=5.4.0.

The scripts
-----------

- `bin/publish.sh` was used to create the initial subsplits. It can be edited to
  re-create them (for instance, in your own user/organization account).
- `bin/update.sh` was the original script used to update all component repos to
  latest master and develop commits. It should only be used now if you know for
  certain all of them must be updated.
- `bin/tag.sh` is used to publish a specific version tag for all component
  repos. Usage is `bin/tag.sh <version>`, where `<version>` is the semantic
  version name; e.g., "2.1.4", "2.0.8", etc.
- `bin/createRepos.php` was used to create all the repositories originally; it
  utilizes the GitHub API to create a repository per component.
- `bin/enablePackagistHook.php` was used to add the packagist hook to all
  component repositories.
- `bin/forkRepos.php` was used to fork the individual component repositories to
  the zendframework organization.
- `bin/update-repos.php` is the script to use to update component repositories.
  It checks for a cached SHA for each of the master and develop branches, and
  then looks up the current SHA via the GitHub API; if they do not match, it
  then retrieves the diff via the GitHub API and determines which components, if
  any, need to be updated.

Cache files
-----------

You will need to create the following files in `cache/`:

- `master.sha`, with the SHA of the last time the repositories were updated
  against the master branch.
- `develop.sha`, with the SHA of the last time the repositories were updated
  against the develop branch.
- `github.token`, with the GitHub API token you will use; make sure it has
  rights against the zendframework repository.
- `git.path`, **optional**; if present, should provide the filesystem path to
  the `git` executable. (Useful if using the `hub` command but running the
  process in a cronjob.)

These files are intentionally left out of version control.
