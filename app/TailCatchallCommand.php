<?php

namespace App;

use PhpSlackBot\Bot;

// This special command executes on all events
class TailCatchallCommand extends \PhpSlackBot\Command\BaseCommand {

    private $fgColors = [
        'black' => '0;30',
        'dark grey' => '1;30',
        'red' => '0;31',
        'light red' => '1;31',
        'green' => '0;32',
        'light green' => '1;32',
        'brown' => '0;33',
        'yellow' => '1;33',
        'blue' => '0;34',
        'light blue' => '1;34',
        'magenta' => '0;35',
        'light magenta' => '1;35',
        'cyan' => '0;36',
        'light cyan' => '1;36',
        'light grey' => '0;37',
        'white' => '1;37',
    ];

    private $bgColors = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'light grey' => '47',
    ];

    private $bgColorIndex = [];
    private $bgColorTracker = 0;

    private $fgColorIndex = [];
    private $fgColorTracker = 0;

    private $spaceTracker = '';

    public function getFGColor($colorString) {
        if(isset($this->fgColors[$colorString])) {
            return $this->fgColors[$colorString];
	}

	return $this->fgColors['white'];
    }

    public function getBGColor($colorString) {
        if(isset($this->bgColors[$colorString])) {
            return $this->bgColors[$colorString];
	}

	return $this->bgColors['white'];
    }

    public function colorizeString($string, $fg, $bg) {
        return  "\e[" . $this->getFGColor($fg) . ";" . $this->getBGColor($bg)  . "m" . $string  . "\e[0m";
    }

    public function incrementFgColor() {
        $this->fgColorTracker++;
        if($this->fgColorTracker > count($this->fgColorIndex) - 1) {
          $this->fgColorTracker = 0;
        }
    }

    public function incrementBgColor() {
        $this->bgColorTracker++;
        if($this->bgColorTracker > count($this->bgColorIndex) - 1) {
          $this->bgColorTracker = 0;
        }
    }

    public function emmitLine($length = 10) {
        print str_repeat("-", $length);
        print "\n";
    }

    protected function configure() {
        $i = 0;
        foreach($this->fgColors as $color => $code) {
            $this->fgColorIndex[$i] = $color;
            $i++;
        }

        $i = 0;
        foreach($this->bgColors as $color => $code) {
            $this->bgColorIndex[$i] = $color;
            $i++;
        }
    }

    public function replaceUserIdsWithNames($message) {
        preg_match_all("/<@\w+>/", $message, $matches);

        if(is_array($matches[0])) {
            foreach($matches[0] as $match) {
                $id = $match;
                $id = str_replace("<","", $id);
                $id = str_replace(">","", $id);
                $id = str_replace("@","", $id);
                $user = $this->getUserNameFromUserId($id);
                $message = str_replace($match, "@" . $user, $message);
            }
        }

        return $message;
    }

    protected function execute($data, $context) {
        if ($data['type'] == 'message') {
            $channel = $this->getChannelNameFromChannelId($data['channel']);
            if(!$channel) {
                $context = $this->getCurrentContext();
                foreach ($context['ims'] as $im) {
                    if ($im['id'] == $data['channel']) {
                        $channel = "@" . $this->getUserNameFromUserId($im['user']);
                    }
                }
            } else {
                $channel = '#' . $channel;
            }

            $team = $this->getChannelNameFromChannelId($data['channel']);
            if(isset($data['user'])) {
                $username = $this->getUserNameFromUserId($data['user']);
            } else {
                $username = "UNKNOWN";
            }

            $spaceIdentifier = $channel ? $channel : 'UNKNOWN';

            if($spaceIdentifier != $this->spaceTracker) {
                $this->spaceTracker = $spaceIdentifier;
                $this->incrementFgColor();
                $this->emmitLine();
            }

            $fgColor = $this->fgColorIndex[$this->fgColorTracker];
            $spaceIdentifier = $this->colorizeString($spaceIdentifier, $fgColor, 'black');
            $message = $this->replaceUserIdsWithNames($data['text']);

            echo '[' . $spaceIdentifier . '] ' . $username . ': ' . $message . PHP_EOL;
        }
    }
}

