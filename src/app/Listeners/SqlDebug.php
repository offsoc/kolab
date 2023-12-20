<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Events\Dispatcher;

class SqlDebug
{
    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        if (!\config('app.debug')) {
            return [];
        }

        return [
            QueryExecuted::class => 'handle',
            TransactionBeginning::class => 'handle',
            TransactionCommitted::class => 'handle',
            TransactionRolledBack::class => 'handle'
        ];
    }

    /**
     * Handle the event.
     *
     * @param object $event An event object
     */
    public function handle(object $event): void
    {
        switch (get_class($event)) {
            case TransactionBeginning::class:
                $query = 'begin';
                break;
            case TransactionCommitted::class:
                $query = 'commit';
                break;
            case TransactionRolledBack::class:
                $query = 'rollback';
                break;
            default:
                $query = sprintf(
                    '%s [%s]: %.4f sec.',
                    $event->sql,
                    self::serializeSQLBindings($event->bindings, $event->sql),
                    $event->time / 1000
                );
        }

        \Log::debug("[SQL] {$query}");
    }

    /**
     * Serialize a bindings array to a string.
     */
    private static function serializeSQLBindings(array $array, string $sql): string
    {
        $ipv = preg_match('/ip([46])nets/', $sql, $m) ? $m[1] : null;

        $serialized = array_map(function ($entry) use ($ipv) {
            if ($entry instanceof \DateTime) {
                return $entry->format('Y-m-d h:i:s');
            } elseif ($ipv && is_string($entry) && strlen($entry) == ($ipv == 6 ? 16 : 4)) {
                // binary IP address? use HEX representation
                return '0x' . bin2hex($entry);
            }

            return $entry;
        }, $array);

        return implode(', ', $serialized);
    }
}
