<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class TailCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'tail';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Tail slack';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $slackTail = new \App\SlackTail(env('AMAZEE_SLACK_TOKEN'));
        $slackTail->addRegexFilter('/.*/');

        $slackTail->tail(function($match) {
//            print_r($match['data']);
            print($match['data']['text']);
        });
    }
}
