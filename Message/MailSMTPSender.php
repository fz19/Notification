<?php
namespace App\Libraries\Message;

use \App\Config\Service;

class MailSMTPSender extends MessageSender
{
    const TYPE = 'mail_smtp';

    public function __construct($config)
    {
        $this->MAIL_DEFAULT_SENDER_MAIL = $config->MAIL_DEFAULT_SENDER_MAIL;
        $this->MAIL_DEFAULT_SENDER_NAME = $config->MAIL_DEFAULT_SENDER_NAME;

        $this->email = \Config\Services::email();
    }

    public static function createMessage($subject, $text, $contentType='html')
    {
        if ($contentType != 'text' && $contentType != 'html')
            throw new \InvalidArgumentException("contentType must be 'text' or 'html'");

        $msg = new Message(MailSender::TYPE);
        // $msg->from = null;
        $msg->subject = $subject;
        $msg->text = $text;
        $msg->contentType = $contentType;
        return $msg;
    }

    // @override
    public function send()
    {
        $result = FALSE;

        foreach ($this->queue as $msg)
        {
            $list = !is_array($msg->to) ? [$msg->to] : $msg->to;

            $this->email->initialize(['mailType' => $msg->contentType]);
            foreach ($list as $to)
            {
                $this->email->setFrom($this->MAIL_DEFAULT_SENDER_MAIL, $this->MAIL_DEFAULT_SENDER_NAME);
                $this->email->setTo($to);
                $this->email->setSubject($msg->subject);
                $this->email->setMessage($msg->text);

                $result = $this->email->send();
                if (!$result)
                    log_message('error', $this->email->printDebugger());
            }
        }

        return $result;
    }
}