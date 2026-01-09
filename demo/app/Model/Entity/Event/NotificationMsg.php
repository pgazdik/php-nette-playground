<?php
namespace App\Model\Entity\Event;

use DateTime;

class NotificationMsg
{
    public function __construct(
        public int $eventId,

        public int $msgIndex,
        public NotificationType $notificationType,
        public MediaType $mediaType,
        public NotificationMsgStatus $status,
        public string $text,
        public DateTime $sendAt,

        public ?DateTime $approvedAt = null,

        public ?int $id = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
   ) {
    }
}