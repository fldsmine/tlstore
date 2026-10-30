<?php

namespace App\Services\Mailing;

use Illuminate\Support\Facades\Schema;
use Throwable;

class MailingInfrastructureHealth
{
    /** @return array{mailer_ready:bool,mailer_name:string,mailer_message:string,queue_ready:bool,queue_name:string,queue_message:string,cache_ready:bool,cache_name:string,cache_message:string,ready:bool} */
    public function report(): array
    {
        [$mailerReady, $mailerName, $mailerMessage] = $this->mailerStatus();
        [$queueReady, $queueName, $queueMessage] = $this->queueStatus();
        [$cacheReady, $cacheName, $cacheMessage] = $this->cacheStatus();

        return [
            'mailer_ready' => $mailerReady,
            'mailer_name' => $mailerName,
            'mailer_message' => $mailerMessage,
            'queue_ready' => $queueReady,
            'queue_name' => $queueName,
            'queue_message' => $queueMessage,
            'cache_ready' => $cacheReady,
            'cache_name' => $cacheName,
            'cache_message' => $cacheMessage,
            'ready' => $mailerReady && $queueReady && $cacheReady,
        ];
    }

    public function assertReady(): void
    {
        $status = $this->report();
        if (! $status['mailer_ready']) {
            throw new MailingConfigurationException($status['mailer_message']);
        }
        if (! $status['queue_ready']) {
            throw new MailingConfigurationException($status['queue_message']);
        }
        if (! $status['cache_ready']) {
            throw new MailingConfigurationException($status['cache_message']);
        }
    }

    private function mailerStatus(): array
    {
        $name = trim((string) config('mail.default', ''));
        $from = trim((string) config('mail.from.address', ''));
        if ($name === '') {
            return [false, 'unconfigured', 'Choose a mailer in Backoffice → Settings → Services before sending.'];
        }
        if (! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return [false, $name, 'Set a valid default From address in Backoffice → Settings → Services.'];
        }

        $config = config('mail.mailers.'.$name);
        if (! is_array($config)) {
            return [false, $name, 'The selected mailer is not defined in the application mail configuration.'];
        }

        $transport = (string) ($config['transport'] ?? $name);
        if (in_array($transport, ['log', 'array'], true)) {
            return [false, $name, 'The selected mailer does not deliver email. Configure SMTP or Resend in Backoffice → Settings → Services.'];
        }

        if ($transport === 'smtp' && blank($config['host'] ?? null)) {
            return [false, $name, 'Configure the SMTP host in Backoffice → Settings → Services.'];
        }
        if ($transport === 'resend' && blank(config('services.resend.key'))) {
            return [false, $name, 'Configure the Resend API key in Backoffice → Settings → Services.'];
        }
        if ($transport === 'postmark' && blank(config('services.postmark.key'))) {
            return [false, $name, 'Configure the Postmark API key before sending.'];
        }
        if (in_array($transport, ['ses', 'ses-v2'], true) && blank(config('services.ses.region'))) {
            return [false, $name, 'Configure the AWS SES region before sending.'];
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $fallbacks = is_array($config['mailers'] ?? null) ? $config['mailers'] : [];
            if ($fallbacks === [] || in_array('log', $fallbacks, true) || in_array('array', $fallbacks, true)) {
                return [false, $name, 'The configured mailer pool includes a non-delivery transport. Configure only real delivery mailers.'];
            }
            foreach ($fallbacks as $fallback) {
                $fallbackConfig = config('mail.mailers.'.$fallback);
                if (! is_array($fallbackConfig) || in_array($fallbackConfig['transport'] ?? $fallback, ['log', 'array'], true)) {
                    return [false, $name, 'One or more mailers in the configured pool cannot deliver email.'];
                }
            }
        }

        return [true, $name, 'Mailer is configured.'];
    }

    private function cacheStatus(): array
    {
        $name = trim((string) config('cache.default', ''));
        $config = $name !== '' ? config('cache.stores.'.$name) : null;
        if (! is_array($config)) {
            return [false, $name ?: 'unconfigured', 'The default cache store is not defined. Configure a shared cache for rate limiting and dispatch coordination.'];
        }

        $driver = (string) ($config['driver'] ?? '');
        if ($driver === 'array' && app()->environment('testing')) {
            return [true, $name, 'Array cache is enabled only for isolated tests.'];
        }
        if (in_array($driver, ['array', 'null', 'octane', 'failover'], true)) {
            return [false, $name, 'Bulk delivery requires a persistent cache store for global rate limiting, unique jobs and dispatch locks. Configure Redis, database, Memcached or file cache.'];
        }

        try {
            if ($driver === 'database') {
                $table = (string) ($config['table'] ?? 'cache');
                $lockTable = (string) ($config['lock_table'] ?? 'cache_locks');
                $cacheSchema = Schema::connection($config['connection'] ?? null);
                $lockSchema = Schema::connection($config['lock_connection'] ?? $config['connection'] ?? null);
                if (! $cacheSchema->hasTable($table) || ! $lockSchema->hasTable($lockTable)) {
                    return [false, $name, 'The configured database cache or cache-locks table is missing. Run the application migrations before launching a campaign.'];
                }
            } elseif ($driver === 'redis') {
                $cacheConnection = (string) ($config['connection'] ?? 'default');
                $lockConnection = (string) ($config['lock_connection'] ?? $cacheConnection);
                if (blank(config('database.redis.'.$cacheConnection.'.host'))
                    || blank(config('database.redis.'.$lockConnection.'.host'))) {
                    return [false, $name, 'Configure both Redis cache and lock connections before launching a campaign.'];
                }
            } elseif ($driver === 'memcached' && empty($config['servers'])) {
                return [false, $name, 'Configure at least one Memcached server for global dispatch coordination.'];
            } elseif (! in_array($driver, ['file', 'memcached', 'redis', 'database'], true)) {
                return [false, $name, 'The configured cache driver cannot provide durable mailing rate limits and dispatch locks.'];
            }
        } catch (Throwable) {
            return [false, $name, 'The cache store could not be checked. Verify its connection and required cache tables.'];
        }

        $message = $driver === 'file'
            ? 'File cache is persistent on this host; multi-node workers require a shared cache store.'
            : 'Persistent cache is configured for rate limiting and dispatch coordination.';

        return [true, $name, $message];
    }

    private function queueStatus(): array
    {
        $name = trim((string) config('queue.default', ''));
        $config = $name !== '' ? config('queue.connections.'.$name) : null;
        if (! is_array($config)) {
            return [false, $name ?: 'unconfigured', 'The default queue connection is not defined. Configure a durable queue connection.'];
        }

        $driver = (string) ($config['driver'] ?? '');
        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            return [false, $name, 'Bulk campaigns require a durable asynchronous queue (database, Redis, SQS or Beanstalkd); the current queue is synchronous or non-durable.'];
        }

        try {
            if ($driver === 'database') {
                $table = (string) ($config['table'] ?? 'jobs');
                if (! Schema::connection($config['connection'] ?? null)->hasTable($table)) {
                    return [false, $name, 'The queue jobs table is missing. Run the application migrations before launching a campaign.'];
                }
            } elseif ($driver === 'redis') {
                $redisConnection = (string) ($config['connection'] ?? 'default');
                if (blank(config('database.redis.'.$redisConnection.'.host'))) {
                    return [false, $name, 'Configure the Redis queue host before launching a campaign.'];
                }
            } elseif ($driver === 'sqs') {
                $prefix = (string) ($config['prefix'] ?? '');
                if ($prefix === '' || str_contains($prefix, 'your-account-id') || blank($config['queue'] ?? null)) {
                    return [false, $name, 'Configure the SQS queue URL and name before launching a campaign.'];
                }
            } elseif ($driver === 'beanstalkd' && blank($config['host'] ?? null)) {
                return [false, $name, 'Configure the Beanstalkd queue host before launching a campaign.'];
            }
        } catch (Throwable) {
            return [false, $name, 'The queue connection could not be checked. Verify the queue configuration and database migrations.'];
        }

        return [true, $name, 'Durable queue is configured. Confirm a queue worker and the scheduler are running.'];
    }
}
