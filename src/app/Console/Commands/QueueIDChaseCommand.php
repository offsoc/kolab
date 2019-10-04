<?php

namespace App\Console\Commands;

use Elasticsearch\Client;
use Illuminate\Console\Command;

class QueueIDChaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queueid:chase {queueid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    private $_es;
    private $_qids = [];
    private $_qidChain = [];

    /**
     * Create a new command instance.
     *
     * @param Client $elasticsearch Elasticsearch client.
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
        $this->info("Searching for Queue ID {$this->argument('queueid')}");

        $this->_qidChain[] = $this->argument('queueid');

        $done = false;

        while (array_diff($this->_qidChain, $this->_qids)) {
            foreach (array_diff($this->_qidChain, $this->_qids) as $qid) {
                $this->_searchQid($qid);
            }
        }

        $json = sprintf(
            '{
                "from": 0, "size": 1000,
                "sort": [
                    { "@timestamp": "asc" }
                ],
                "query": {
                    "query_string": {
                        "query": "(%s)",
                        "fields": [ "local_queueid" ]
                    }
                }
            }',
            implode(') OR (', $this->_qidChain)
        );

        $params = [
            "index" => "logstash-*",
            "body" => $json
        ];

        $result = $this->_es->search($params);

        foreach ($result['hits']['hits'] as $item) {
            $this->info($item['_source']['@timestamp'] . ': ' . $item['_source']['message']);
        }

        $this->info('Done!');
    }

    /**
     * Search a Queue ID in Elasticsearch logstash-* indices.
     *
     * @param string $qid The Queue ID to look for.
     *
     * @return void
     */
    private function _searchQid($qid)
    {
        if (in_array($qid, $this->_qids)) {
            return;
        }

        $this->_qids[] = $qid;

        $json = sprintf(
            '{
                "from": 0, "size": 1000,
                "query": {
                    "multi_match": {
                        "query": "%s",
                        "fields": [ "local_queueid", "remote_queueid" ]
                    }
                }
            }',
            $qid
        );

        $params = [
            'index' => 'logstash-*',
            'body' => $json
        ];

        $result = $this->_es->search($params);

        foreach ($result['hits']['hits'] as $item) {
            if (array_key_exists('local_queueid', $item['_source'])) {
                $this->_qidChain[] = $item['_source']['local_queueid'];
            }

            if (array_key_exists('remote_queueid', $item['_source'])) {
                $this->_qidChain[] = $item['_source']['remote_queueid'];
            }
        }
    }
}
