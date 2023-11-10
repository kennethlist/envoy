{{--
NAME
    envoy - deploy m2

SYNOPSIS
    envoy run deploy --on=[ENV] [--branch=[NAME]]

DESCRIPTION
    @TODO

EXAMPLES
    Deploy master:
        $ envoy run deploy --on=staging

    Deploy a particular branch:
        $ envoy run deploy --on=staging --branch=feature1
--}}
@include('config.php');

@servers($servers)

@setup
    if (!isset($on)) {
        throw new Exception('--on must be specified');
    }

    if (!isset($branch)) {
        // default to master branch if not specified
        $branch = 'master';
    }

    // load/setup env specific variables
    require_once(implode(DIRECTORY_SEPARATOR, [__DIR__, 'environments', $on.'.env.php']));
@endsetup

{{-- deploy task:
    Deploy a new release
--}}
@macro('deploy', ['on' => $on])
    start_release
    git_clone
    composer_install
    symlink_storage
    {{-- apply_patches --}}
    recompile_code
    collect_static_assets
    update_file_permissions
    cron_disable
    maintenance_enable
    database_migration
    publish_release
    maintenance_disable
    cron_enable
    clear_cache
    remove_old_releases
@endmacro

{{-- start_release task:
    Create new release folder
--}}
@task('start_release', ['on' => $on])
    echo '{{ $upcoming_release_directory }}'
    mkdir {{ $upcoming_release_directory }}
    echo "Building release..."
@endtask

{{-- cron_disable task:
    @TODO - leave disabled
--}}
@task('cron_disable', ['on' => $on])
    {{-- crontab -l > crontab.txt && crontab -r --}}
    echo "Cron disabled"
@endtask

{{-- cron_enable task:
    @TODO - leave disabled
--}}
@task('cron_enable', ['on' => $on])
    {{-- crontab crontab.txt --}}
    echo "Cron enabled"
@endtask

{{-- clear_cache task:
    clear_cache.php - clears opcache
--}}
@task('clear_cache', ['on' => $on])
    cd {{ $current_release_directory }};
    wget -O- --no-check-certificate {{ $website_url.'/clear_cache.php' }}
    php bin/magento cache:enable
    php bin/magento cache:flush
    php bin/magento cache:clean
    echo "Cache cleared"
@endtask

@task('maintenance_enable', ['on' => $on])
    cd {{ $current_release_directory }};
    php bin/magento maintenance:enable
    echo "Maintenance mode enabled"
@endtask

@task('maintenance_disable', ['on' => $on])
    cd {{ $current_release_directory }};
    php bin/magento maintenance:disable
    echo "Maintenance mode disabled"
@endtask

@task('git_clone', ['on' => $on])
    cd {{ $upcoming_release_directory }};
    git clone --branch={{ $branch }} --depth=1 {{ $git_repo }} .
    echo "Git repo cloned"
@endtask

@task('composer_install', ['on' => $on])
    {{-- for dependencies --}}
    cd {{ $upcoming_release_directory }};
    composer install --no-dev --optimize-autoloader

    {{-- Prevent magento composer install from modifing enabled modules --}}
    git checkout app/etc/config.php

    echo "Composer modules updated"
@endtask

@task('recompile_code', ['on' => $on])
    cd {{ $upcoming_release_directory }};
    rm -rf generated
    php bin/magento setup:di:compile
    echo "Code recompiled"
@endtask

@task('database_migration', ['on' => $on])
    {{-- keep-generated doesn't wipe out generated classes --}}
    cd {{ $upcoming_release_directory }};
    php bin/magento setup:upgrade --keep-generated
    echo "Database migration"
@endtask

{{-- collect_static_assets task:
    compile css/javascript themes
--}}
@task('collect_static_assets', ['on' => $on])
    cd {{ $upcoming_release_directory }};
    rm -rf var/view_preprocessed/ pub/static/* var/cache/ var/page_cache/
    php bin/magento setup:static-content:deploy {{ $themes }}
    echo "Deployed static assets"
@endtask

{{-- apply_patches task:
    @FIXME find better way to apply patches
--}}
@task('apply_patches', ['on' => $on])
    echo "Appling patches"
    cd {{ $upcoming_release_directory }};

    for i in patches/*.patch
    do
        output=$(patch -p1 -N --dry-run < $i 1>/dev/null || echo "1")
        if [ "$output" != "1" ]; then
            patch -p1 -N < $i
        fi
    done

    echo "Patches applied"
@endtask

@task('update_file_permissions', ['on' => $on])
    echo "Updating file permissions"
    cd {{ $upcoming_release_directory }};

    {{-- @FIXME - setup correct permissons --}}
    find . -type d -exec chmod 755 {} \;
    find . -type f -exec chmod 644 {} \;
    chmod o-rwx app/etc/env.php && chmod u+x bin/magento
    chmod -R u+w .
@endtask

{{-- symlink_storage task:
    A notable command is the creation of the symlink to make the new code files live. This is the ln command with the -nfs flags used:

    -s - Create a symbolic link.
    -f - Force the creation of the symlink even if a file, directory, or symlink already exists at that location (it will unlink, aka delete, the target directory!).
    -n - If the target directory or file is a symlink, don't follow it. Often used with the -f flag.
--}}
@task('symlink_storage', ['on' => $on])
    echo "Symlinking storage folders"
    {{-- remove any default m2 media assets before symlinking --}}
    rm -rf {{ $upcoming_release_directory }}/pub/media;

    {{-- additional folders --}}
    @foreach($symlink_directories as $source_folder => $destination_folder)
        ln -nfs {{ $source_folder }} {{ $destination_folder }} ;
    @endforeach

    echo "Storage assets added."
@endtask

@task('publish_release', ['on' => $on])
    echo "Publishing release..."
    ln -nfs {{ $upcoming_release_directory }} {{ $current_release_directory }};
    echo "New release({{ $upcoming_release_directory }}) is LIVE!"
@endtask

{{-- remove_old_releases task:
    This will list our releases by modification time (most recent first and delete all but the $releasesToKeep most recent.
    tail -n +Number takes the output of the ls command and only starts including it at the Number line, which is
    why we add 1 to the $releasesToKeep value, so if it is 5 we want to keep, start the output on the 6 line
    which is the first one we want to delete.  remove the symlink to the current app also, probably not needed though. it will be overridden.
 --}}
@task('remove_old_releases', ['on' => $on])
    echo "Removing old releases"
    ls -dt {{ $releases_directory }}/* | tail -n +{{ $releases_to_keep + 1 }} | xargs -d '\n' rm -rf;
    echo "Old releases were removed"
@endtask
