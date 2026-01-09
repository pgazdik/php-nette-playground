<?php
namespace App\Model\Entity\Event;


enum NotificationMsgStatus: string
{
    case New = 'new';
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}