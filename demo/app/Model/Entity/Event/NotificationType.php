<?php
namespace App\Model\Entity\Event;


enum NotificationType: string
{
    /**
     * Contains full information about the appointment, sent first and consists of multiple messages - one text message and potentially multiple images.
     */
    case Main = 'main';

    /**
     * Reminder shortly before the event, only a text message is sent.
     */
    case Reminder = 'reminder';
}