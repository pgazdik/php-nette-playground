<?php
namespace App\Model\Entity\Event;


enum MediaType: string
{
    case Text = 'text';
    case Image = 'image';
}