<?php

namespace App\Service;

use App\Service\Activity\ActivityLogger;
use Symfony\Component\Process\Process;

/**
 * Warms Symfony cache as www-data and restarts the web container (Docker socket required).
 */
final class SymfonyCacheRefresher
{
    private const DOCKER_SOCKET = '/var/run/docker.sock';

    public function __construct(
        private readonly string $projectDir,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return array{cleared: bool, warmed: bool, restart_scheduled: bool, messages: list<string>}
     */
    public function refreshAndRestartWeb(): array
    {
        $messages = [];

        $this->runConsole('cache:clear', ['--no-warmup' => true]);
        $messages[] = 'Symfony cache cleared.';
        $cleared = true;

        $this->fixCachePermissions();
        $this->runConsole('cache:warmup', ['--no-debug' => true]);
        $messages[] = 'Symfony cache warmed up.';
        $warmed = true;

        $this->reloadPhpFpm();
        $messages[] = 'PHP-FPM reloaded so workers use the new cache.';

        $restartScheduled = $this->scheduleWebContainerRestart();
        if ($restartScheduled) {
            $messages[] = 'Web container restart scheduled (about 3 seconds).';
        }

        $this->activityLogger->log('admin', 'symfony_cache_refresh', implode(' ', $messages), [
            'cleared' => $cleared,
            'warmed' => $warmed,
            'restart_scheduled' => $restartScheduled,
        ]);

        return [
            'cleared' => $cleared,
            'warmed' => $warmed,
            'restart_scheduled' => $restartScheduled,
            'messages' => $messages,
        ];
    }

    /**
     * @param array<string, bool|string> $options
     */
    private function runConsole(string $command, array $options = []): void
    {
        $args = ['php', 'bin/console', $command, '--no-interaction'];
        foreach ($options as $name => $value) {
            if ($value === true) {
                $args[] = is_int($name) ? (string) $name : $name;
            } elseif ($value !== false && $value !== null) {
                $args[] = sprintf('%s=%s', $name, $value);
            }
        }

        $commandLine = $this->wrapForWwwData(implode(' ', array_map('escapeshellarg', $args)));

        $process = Process::fromShellCommandline($commandLine, $this->projectDir);
        $process->setTimeout(300);
        $process->mustRun();
    }

    private function wrapForWwwData(string $innerCommand): string
    {
        if (\function_exists('posix_getuid') && posix_getuid() === 0) {
            return 'su -s /bin/sh www-data -c '.$innerCommand;
        }

        return $innerCommand;
    }

    private function fixCachePermissions(): void
    {
        if (!\function_exists('posix_getuid') || posix_getuid() !== 0) {
            return;
        }

        foreach (['var/cache', 'var/log'] as $dir) {
            $path = $this->projectDir.'/'.$dir;
            if (is_dir($path)) {
                @chown($path, 'www-data');
                @chgrp($path, 'www-data');
            }
        }
    }

    private function scheduleWebContainerRestart(): bool
    {
        if (!is_readable(self::DOCKER_SOCKET)) {
            return false;
        }

        $socket = escapeshellarg(self::DOCKER_SOCKET);
        $script = sprintf(
            'sleep 3 && curl -sf --unix-socket %s -X POST "http://localhost/containers/$(hostname)/restart?t=10"',
            $socket
        );

        $process = Process::fromShellCommandline($script);
        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();

        return true;
    }

    private function reloadPhpFpm(): void
    {
        $process = Process::fromShellCommandline('kill -USR2 $(pidof php-fpm) 2>/dev/null || true');
        $process->run();
    }
}
