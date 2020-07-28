<?php
namespace App\Libraries\Message;

class MessageSender
{
    protected $queue = array();

    public function addQueue(Message $msg)
    {
        if (!$msg)
            throw new \InvalidArgumentException('not allowed NULL message');

        $this->queue[] = $msg;
    }

    public function clearQueue()
    {
        unset($this->queue);
        $this->queue = [];
    }

    public function send()
    {
        throw new MessageException('not implemented');
    }

    protected function saveResult($result)
    {
        log_message('debug', 'MessageSender :: ' . $result);
    }
}