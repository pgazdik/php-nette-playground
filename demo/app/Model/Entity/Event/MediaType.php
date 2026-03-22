<?php
namespace App\Model\Entity\Event;


enum MediaType: string
{
    case Text = 'text';
    case Image = 'image';

    public function isImage(): bool
    {
        return $this === self::Image;
    }

    public function isText(): bool
    {
        return $this === self::Text;
    }
}
