<?php
namespace App\Model\Entity\Event;


enum NotificationAttemptStatus: string
{
    case Sent = 'sent';
    case NotFound = 'notFound';
    case Queued = 'queued';
    case CheckError = 'checkError';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function supportsCheck(): bool
    {
        return in_array($this, self::checkSupportingStatuses());
    }

    public function toColor(): StatusColor
    {
        return match ($this) {
            self::Sent => StatusColor::LightBlue,
            self::Queued => StatusColor::LightBlue,
            self::NotFound => StatusColor::LightBlue,
            self::CheckError => StatusColor::DarkYellow,
            self::Delivered => StatusColor::Green,
            self::Failed => StatusColor::Red,
        };
    }
    public static function checkSupportingStatuses(): array
    {
        return [self::Sent, self::Queued, self::NotFound, self::CheckError];
    }

    public static function finalStatuses(): array
    {
        return [self::Delivered, self::Failed];
    }

}