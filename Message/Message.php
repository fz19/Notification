<?php
namespace App\Libraries\Message;

class Message extends \StdClass
{
    public $messageType;
    public $to;
    public $from;
    public $subject;
    public $text;

    public function __construct($type) {
        $this->messageType = $type;
    }
}