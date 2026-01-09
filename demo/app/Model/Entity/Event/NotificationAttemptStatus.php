<?php
namespace App\Model\Entity\Event;


enum NotificationAttemptStatus: string
{
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}