<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'recipe/deploy/push.php';
require 'contrib/npm.php';

// Config
set('application', 'nextel');
set('repository', 'git@github.com:iamolayemi/nextel.git');
set('keep_releases', 5);
set('php_fpm_version', '8.4');
set('branch', 'online');
set('update_code_strategy', 'local_archive');

add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts
host('staging')
    ->set('hostname', '139.162.144.119')
    ->set('remote_user', 'root')
    ->set('deploy_path', '/var/www/nextelconnect')
    ->set('labels', ['type' => 'all']);

host('production-app')
    ->set('hostname', '172.235.173.69')
    ->set('remote_user', 'root')
    ->set('deploy_path', '/var/www/nextelconnect')
    ->set('branch', 'online')
    ->set('ssh_multiplexing', false)
    ->set('labels', ['type' => 'app']);

host('production-worker')
    ->set('hostname', '172.239.239.130')
    ->set('remote_user', 'root')
    ->set('deploy_path', '/var/www/nextelconnect')
    ->set('labels', ['type' => 'worker']);


task('deploy:update_code', function () {
    $target = get('target');
    $targetWithDir = $target;

    if (!empty(get('sub_directory'))) {
        $targetWithDir .= ':' . get('sub_directory');
    }

    // Resolve the local Git repository path on Windows/Git Bash.
    $gitRoot = trim(
        runLocally('cygpath -u "$(git rev-parse --show-toplevel)"')
    );

    $archivePath = $gitRoot . '/archive.tar';

	$rev = trim(
		runLocally(
			'git -C ' . escapeshellarg($gitRoot)
			. ' rev-parse '
			. escapeshellarg($target)
			. '^{commit}'
		)
	);

	$archiveTarget = $rev;

	if (!empty(get('sub_directory'))) {
		$archiveTarget .= ':' . get('sub_directory');
	}

    try {
        // Archive the selected local branch, including local commits.
        runLocally(
            'git -C ' . escapeshellarg($gitRoot)
            . ' archive '
			. escapeshellarg($archiveTarget)
            . ' -o '
            . escapeshellarg($archivePath)
        );

        // Use the current Deployer host instead of a hardcoded IP.
        $remoteUser = get('remote_user');
        $hostname = get('hostname');
        $remoteArchive = parse('{{release_path}}/archive.tar');
        $remoteTarget = $remoteUser . '@' . $hostname . ':' . $remoteArchive;

        runLocally(
            'scp '
            . escapeshellarg($archivePath)
            . ' '
            . escapeshellarg($remoteTarget)
        );

        run('tar -xf {{release_path}}/archive.tar -C {{release_path}}');
        run('rm -f {{release_path}}/archive.tar');

        $rev = trim(
            runLocally(
                'git -C ' . escapeshellarg($gitRoot)
                . ' rev-list '
                . escapeshellarg($target)
                . ' -1'
            )
        );

        run('echo ' . escapeshellarg($rev) . ' > {{release_path}}/REVISION');
    } finally {
        if (file_exists($archivePath)) {
            unlink($archivePath);
        }
    }
});

// Tasks
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'npm:install',
    'npm:build',
    'artisan:storage:link',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:event:cache',
    'artisan:migrate',
    'deploy:publish',
]);

// Each release has its own public/build (not a shared dir), so every web host must build
// its own assets — ->once() would build on only one type=app|all host and 500 the other.
task('npm:install')->select('type=app|all');
task('npm:build')->select('type=app|all');

// type=app|all resolves to exactly one host per database (staging → its own DB, production-app →
// the prod DB the worker shares), so migrate on each — ->once() would migrate only one and skip
// the other database entirely on any deploy that ships migrations.
task('artisan:migrate')->select('type=app|all');

// shared/.env is root-owned; make it group-readable by the web/worker user (www-data on every
// host) so php-fpm, Horizon and Reverb can boot even when the config cache is absent — otherwise
// APP_KEY can't be read and every web request 500s with MissingAppKeyException.
task('env:permissions', function () {
    run('chgrp www-data {{deploy_path}}/shared/.env && chmod 640 {{deploy_path}}/shared/.env');
});

task('opcache:reset', function () {
    run('command -v cachetool >/dev/null && cachetool opcache:reset --fcgi=/var/run/php/php{{php_fpm_version}}-fpm.sock || true');
})->select('type=app|all');


task('supervisor:restart', function () {
    run('sudo supervisorctl restart nextel-horizon nextel-reverb');
})->select('type=worker');


// Hooks
after('deploy:shared', 'env:permissions');
after('deploy:symlink', 'opcache:reset');
after('deploy:success', 'supervisor:restart');
after('deploy', 'deploy:cleanup');
after('deploy:failed', 'deploy:unlock');
