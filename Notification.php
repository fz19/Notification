<?php
// for CodeIgniter4 Library
namespace App\Libraries;

use CodeIgniter\Config\BaseService;
use App\AppException;
use App\Libraries\Message\Message;
use App\Libraries\Message\SMSSender;
use App\Libraries\Message\MailSender;
use App\Libraries\Message\AlimTalkSender;

class Notification extends BaseService {

    protected $sender = [];
    protected $messages = [];

    protected function _getSender($type)
    {
        if (!$type || !is_string($type))
            throw InvalidArgumentException('invalid type');

        if (array_key_exists($type, $this->sender))
            return $this->sender[$type];

        $config = config('OfficeCheckIN');

        switch ($type)
        {
            case 'sms':
                $this->sender[$type] = new SMSSender($config);
                return $this->sender[$type];
            case 'alimtalk':
                $this->sender[$type] = new AlimTalkSender($config);
                return $this->sender[$type];
            case 'mail':
                $this->sender[$type] = new MailSender($config);
                return $this->sender[$type];
        }

        throw new AppException('unsupported type -> ' . $type);
    }

    protected function _saveJob()
    {
        throw new \LogicException('not supported reservation');
    }

    public function send(int $reservation_time = 0)
    {
        if ($reservation_time != 0)
        {
            $this->_saveJob();
            return;
        }

        foreach ($this->messages as $msg)
        {
            $this->_getSender($msg->messageType)->addQueue($msg);
        }

        $this->messages = [];
        foreach ($this->sender as $sender)
        {
            $sender->send();
            $sender->clearQueue();
        }
    }

    public function addSimple($data)
    {
        switch ($data['type'])
        {
            case 'sms':
                $item = SMSSender::createMessage($data['message']);
                break;

            case 'alimtalk':
                $item = AlimTalkSender::createMessage($data['message']);
                break;

            case 'mail':
                $item = MailSender::createMessage($data['subject'], $data['message']);
                break;

            default:
                throw new AppException('unsupported type -> ' . $data['type']);
        }

        $this->addMessage($item, $data['receiver']);

        return $this;
    }

    public function addMessage(Message $msg, $to)
    {
        $msg->to = $to;
        $this->messages[] = $msg;
    }

    private function _increaseLimitCount($receiver, $sendIP)
    {
        return TRUE;
    }

    private function _bindData(string $text, array $data)
    {
        $replaces = [];
        foreach ($data as $key => $value) {
            if (is_array($value))
                continue;

            $replaces['[['.strtoupper($key).']]'] = $value;
            $replaces['#{'.strtoupper($key).'}'] = $value;
            $replaces['${'.strtoupper($key).'}'] = $value;
        }

        return str_replace(array_keys($replaces), array_values($replaces), $text);
    }
}

class NotificationException extends \Exception {
    function __construct($code=self::ERROR_UNKNOWN, $message=null)
    {
        parent::__construct($message);
        $this->code = $code;
    }

    const ERROR_NONE = 0;
    const ERROR_UNKNOWN = 1;
    const ERROR_EXCESS_SENDCOUNT = 2;
    const ERROR_CANNOT_FIND_TEMPLATE = 3;
}