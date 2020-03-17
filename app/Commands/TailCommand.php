<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class TailCommand extends Command
{
    protected $regexMessageWhitelistFilters = [];

    public function addMessageWhitelistRegexMatch($regex)
    {
        $this->regexMessageWhitelistFilters[] = $regex;
    }

    public function messageMatchesWhitelist($message)
    {
      $return = FALSE;

      foreach($this->regexMessageWhitelistFilters as $regex) {
        if($this->isVerbose()) {
            $this->info("Checking message regex: " . $regex);
        }

        if(preg_match($regex, $message)) {
            $return = TRUE;
            break;
        } 
      }

      return $return;
    }

    protected $regexSendersWhitelistFilters = [];

    public function addSendersWhitelistRegexMatch($regex)
    {
        $this->regexSendersWhitelistFilters[] = $regex;
    }

    public function senderMatchesWhitelist($sender)
    {
      $return = FALSE;
    
      foreach($this->regexSendersWhitelistFilters as $regex) {
        if($this->isVerbose()) {
            $this->info("Checking sender '{$sender}' against regex: " . $regex);
        }

        if(preg_match($regex, $sender)) {
            $return = TRUE;
            break;
        } 
      }

      return $return;
    }

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'tail {context=all : Specify a Slail context} {--slailfile=slailfile.yaml : Slail YML file}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Tail slack';

    public function loadSlailFile($slailFile, $context = "all") {
        if(!file_exists($slailFile)) {
            $this->error("Slailfile missing: " . $slailFile);
            return false;
        }

        $slailConfig = Yaml::parse(file_get_contents($slailFile));
        if(isset($slailConfig[$context]['message-whitelist']) && is_array($slailConfig[$context]['message-whitelist'])) 
        {
            foreach($slailConfig[$context]['message-whitelist'] as $wl) {
                if($this->isVerbose()) {
                    $this->info("[$context] " . 'Loading ' . $wl . ' to message whitelist');
                }

                $this->addMessageWhitelistRegexMatch($wl);
            }
        }

        if(isset($slailConfig[$context]['sender-whitelist']) && is_array($slailConfig[$context]['sender-whitelist'])) 
        {
            foreach($slailConfig[$context]['sender-whitelist'] as $wl) {
                if($this->isVerbose()) {
                    $this->info("[$context] " . 'Loading ' . $wl . ' to sender whitelist');
                }

                $this->addSendersWhitelistRegexMatch($wl);
            }
        }

        return TRUE;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if(!$this->loadSlailfile($this->option("slailfile"), $this->argument('context'))) {
            return 255;
        }

        $this->info("Loaded context: " . $this->argument('context'));

        $slackTail = new \App\SlackTail(env('AMAZEE_SLACK_TOKEN'));
        

        $output = $this->output;
        $slackTail->tail(function($data) use ($output) {
            if($data['subtype'] == "message_deleted") {
                return;
            }

            if(empty($data['text']) && isset($data['message_data']['text'])) {
                $data['text'] = $data['message_data']['text'];    
            }

            $sender = $this->findUserName($data) ?? '__user__';
            $outline = $this->getFormattedOutput($data);
            if($this->messageMatchesWhitelist($outline) && $this->senderMatchesWhitelist($sender)) {
                $output->writeln($outline);
            }
            
            if(isset($data['message_data']['attachment_simplifications'])) {
                foreach($data['message_data']['attachment_simplifications'] as $attachment) {
                    $adata = [
                        'user_info' => $data['message_data']['user_info'],
                        'conversation' => $data['conversation']
                    ];

                    if(isset($data['message'])) {
                        $adata['message'] = $data['message'];
                    }

                    $adata['text'] = " {+++} " . $attachment;
                    $sender = $this->findUserName($adata) ?? '__user__';
                    $outline = $this->getFormattedOutput($adata);
                    if($this->messageMatchesWhitelist($outline) && $this->senderMatchesWhitelist($sender)) {
                        $output->writeln($outline);
                    }
                }
            }
        }, 
        function($reactionData) use ($output) {
            $sender = $this->findUserName($reactionData) ?? '__user__';
            $outline = $this->getFormattedOutput($reactionData);
            
            if($this->messageMatchesWhitelist($outline) && $this->senderMatchesWhitelist($sender)) {
                $output->writeln($outline);            
            }
        });
    }

    public function getFormattedOutput($data) {
        $user = $this->findUserName($data) ?? '__user__';
        $channel = $this->findChannelName($data) ?? '__channel__';

        $prefix = "<fg={$this->getColorFor($channel)}>#" . $channel . '</>' .
            ': ' . 
            "<fg={$this->getColorFor($user)}>@" . $user . '</>';
    

        if(isset($data['thread_ts'])) {
            $prefix = $prefix . " ({$data['thread_ts']}) ";
        }

        if(isset($data['type']) && $data['type'] == "reaction_added") {
            return  $prefix . '] {{' . $data['reaction_item']['text'] .'}} ==> <options=underscore,reverse> ++' . $data['reaction'] . '</>';
        } else {
            return  $prefix . '] ' . $data['text'];
        }
    }

    public function findChannelName($data)
    {
        if(isset($data['conversation']['channel']['name_normalized'])) {
            return $data['conversation']['channel']['name_normalized'];
        }

        if(isset($data['conversation']['with_user']['user']['name'])) {
            return $data['conversation']['with_user']['user']['name'];
        }
    }

    public function findUserName($data)
    {
        if(isset($data['user_info']['user']['real_name'])) {
            return $data['user_info']['user']['real_name'];
        }

        if(isset($data['message_data']['user_info']['user']['real_name'])) {
            return $data['message_data']['user_info']['user']['real_name'];
        }
    }

    public function getColorFor($for) {
        $colors = [
            "red", "green", "yellow", "blue", "magenta", "cyan", "white"
        ];

        $lastColorCacheKey = "color-for-last-color-index";
        $lastColorIndex = Cache::get($lastColorCacheKey, 0);

        $cacheKey = "color-for-" . $for;
        if(Cache::has($cacheKey)) {
            $color = Cache::get($cacheKey);
        } else {
            if($lastColorIndex >= count($colors)) { 
                $lastColorIndex = 0;
            }

            $color = $colors[$lastColorIndex];
            $lastColorIndex++;
            Cache::put($cacheKey, $color, now()->addMinutes(2));
            Cache::put($lastColorCacheKey, $lastColorIndex);
        }

        return $color;
    }

    public function isVerbose()
    {
        $verbosityLevel = $this->getOutput()->getVerbosity();

        if($verbosityLevel >= OutputInterface::VERBOSITY_VERBOSE){
            return TRUE;
        }      
        
        return FALSE;
    }
}
