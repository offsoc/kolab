<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\User;
use Illuminate\Support\Facades\Redis;

class HorizonInspectCommand extends Command
{
    protected $signature = 'horizon:inspect
                                {--count : Count all entries}
                                {--completed : Show only completed}
                                {--failed : Show only failed}
                                {--limit=5 : Limit after n matches}
                                {--name= : Job name to filter by}
                                {--user= : User to filter by}
                                {--exclude=* : Job name to skip}';
    protected $description = 'Inspect the job queue in redis';

    public function __construct()
    {
        parent::__construct();
    }

    private static function formatDate($value)
    {
        if (!empty($value)) {
            $date = \DateTime::createFromFormat('U.u', $value);
            return $date->format('Y-m-d H:i:s.u');
        }
        return $value;
    }

    public function handle()
    {
        $name = $this->option('name');
        $exclude = $this->option('exclude');
        $completed = $this->option('completed');
        $failed = $this->option('failed');
        $user = $this->option('user');
        $limit = $this->option('limit');
        $redis = Redis::connection('horizon');
        $cursor = "0";
        $count = 0;


        if ($user) {
            $user = $this->getUser($user, true);

            if (!$user) {
                $this->error("User not found.");
                return 1;
            }
        }

        if ($this->option('count')) {
            do {
                list($cursor, $keys) = $redis->scan($cursor, 'match', 'horizon:????????-*');
                $count += count($keys);
            } while ($cursor);
            $this->info("Number of keys: $count");
            return 0;
        }

        do {
            list($cursor, $keys) = $redis->scan($cursor, 'match', 'horizon:????????-*');
            foreach ($keys as $key) {
                try {
                    $match = false;
                    $value = $redis->hgetall(explode(":", $key)[1]);
                    if (empty($value)) {
                        $this->warn("Empty value on Key: " . $key);
                        continue;
                    }
                    //if ($user && !str_contains($value['payload'], $user->id)) {
                    if ($user && !str_contains($value['payload'], $user->email)) {
                        continue;
                    }
                    if ($completed && $value['status'] != 'completed') {
                        continue;
                    }
                    if ($failed && $value['status'] != 'failed') {
                        continue;
                    }
                    if (!empty($exclude) && in_array($value["name"], $exclude)) {
                        continue;
                    } else {
                        $match = true;
                    }
                    if ($name && $value["name"] == $name) {
                        $match = true;
                    }
                    if ($match) {
                        $this->info("Key: " . $key);
                        //Translate one of the timestamps to something readable

                        $value['created_at'] = static::formatDate($value['created_at']);
                        $value['completed_at'] = static::formatDate($value['completed_at']);
                        $value['updated_at'] = static::formatDate($value['updated_at']);
                        $value['reserved_at'] = static::formatDate($value['reserved_at']);

                        // $value['created_at'] = date('r', $value['created_at']);
                        // $value['completed_at'] = date('r', $value['completed_at']);
                        $this->line("Value: " . var_export($value, true));
                        $count++;
                    }
                } catch (\Exception $e) {
                    $this->warn("Exception on Key: " . $key);
                    $this->warn("Exception: " . $e);
                    $this->line("Value: " . var_export($value, true));
                }
                if ($count >= $limit) {
                    break;
                }
            }
        } while ($cursor && $count < $limit);
        $this->info("Number of keys: $count");

        //$this->info("Number of jobs of type {$type}: {$count}");
    }
}
