<?php

namespace App\Console\Commands;

use Elasticsearch\Client;
use Illuminate\Console\Command;

class UserLoginsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:logins {userid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $_es;

    /**
     * Create a new command instance.
     *
     * @param Client $elasticsearch The Elasticsearch client.
     *
     * @return void
     */
    public function __construct(Client $elasticsearch)
    {
        parent::__construct();

        $this->_es = $elasticsearch;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("Searching logins for user {$this->argument('userid')}");

        $json = sprintf(
            '{
                "from": 0, "size": 1000,
                "sort": [
                    { "@timestamp": "desc" }
                ],
                "query": {
                    "query_string": {
                        "query": "client_auth:\"%s\" AND tags:(foreign OR inbound)"
                    }
                }
            }',
            $this->argument('userid')
        );

        $params = [
            "index" => "logstash-*",
            "body" => $json
        ];

        $result = $this->_es->search($params);

        foreach ($result['hits']['hits'] as $item) {
            $this->info(
                sprintf(
                    "%s: %s (%s, %s)",
                    $item['_source']['@timestamp'],
                    $item['_source']['src_ip'],
                    $item['_source']['geo_src']['country_name'],
                    array_key_exists('city_name', $item['_source']['geo_src']) ? $item['_source']['geo_src']['city_name'] : ""
                )
            );
        }

        $this->info('Done!');
    }
}
