<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use PhpSlackBot\Bot;
use App\TailCatchallCommand;


class TailCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'tail {--channel= : Tail the specific channel}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Tail a slack channel';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Slinking into slack...');

        $logger = new \Zend\Log\Logger();
        $writer = new \Zend\Log\Writer\Stream("php://output");

        $filter = new \Zend\Log\Filter\Priority(\Zend\Log\Logger::CRIT);
        $writer->addFilter($filter);
        $logger->addWriter($writer);

        $bot = new Bot();
        $bot->setToken(config('slail.token'));
        $bot->initLogger($logger);
        $bot->loadCommand(new TailCatchallCommand());
        $bot->loadInternalCommands(); // This loads example commands
        $bot->run();
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
