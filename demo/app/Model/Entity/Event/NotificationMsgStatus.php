<?php
namespace App\Model\Entity\Event;


enum NotificationMsgStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function supportsUpdate(): bool
    {
        return \in_array($this, self::updateSupportingStatuses());
    }

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

    public static function updateSupportingStatuses(): array
    {
        return [self::Draft, self::Scheduled];
    }

}