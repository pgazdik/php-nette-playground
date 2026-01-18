<?php
namespace App\Model\Entity\Event;


enum NotificationMsgStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function toColor(): StatusColor
    {
        return match ($this) {
            self::Draft => StatusColor::Grey,
            self::Scheduled => StatusColor::Teal,
            self::Sent => StatusColor::LightBlue,
            self::Delivered => StatusColor::Green,
            self::Failed => StatusColor::Red,
        };
    }
}