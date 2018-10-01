<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use PhpSlackBot\Bot;
use App\TailCatchallCommand;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Zend\Log\Filter\Priority;

class TailCommand extends Command
{
    protected $signature = 'tail';

    protected $description = 'Tail slack';

    public function handle()
    {
        $this->info('Slinking into slack...');

        $logger = new Logger();
        $writer = new Stream("php://output");

        $filter = new Priority(Logger::CRIT);
        $writer->addFilter($filter);
        $logger->addWriter($writer);

        $bot = new Bot();
        $bot->setToken(config('slail.token'));
        $bot->initLogger($logger);
        $bot->loadCommand(new TailCatchallCommand());
        $bot->run();
    }
}
