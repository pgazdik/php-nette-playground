<?php
namespace App\Model\Entity\Event;

use App\Utils\DateUtils;
use DateTime;

class NotificationAttempt
{
    public function __construct(
        public int $notificationMsgId,

        public int $attemptNo, // 1, 2, 3...
        public NotificationAttemptStatus $status,

        public ?DateTime $sentAt = null, // actually sent, which can be different than scheduled
        public ?DateTime $checkAt = null,

        public ?string $sendingError = null, // if error happens while trying to send and we can't even reach the GW
        public ?string $checkError = null, // if error happens while checking the status

        public ?int $gwId = null,
        public ?string $gwSendStatus = null,
        public ?string $gwCheckStatus = null,
        public ?string $gwCheckStatusHistory = null,
        public ?int $gwErrorCode = null,
        public ?DateTime $gwSendDate = null,
        public ?DateTime $gwDeliveryDate = null,

        
        public ?int $id = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,

        // NOT MAPPED TO DB
        public ?NotificationMsg $msg = null,
    ) {
    }

}