<?php
namespace App\Model\Entity\Event;

use DateTime;

class Event
{
    public function __construct(
        public string $patientName,
        public string $phoneNumber,
        public string $doctorName,
        public string $doctorAddress,
        public DateTime $appointmentDate,
        public ?string $attachmentContent,
        public ?string $attachmentName,
        public ?string $attachmentType,

        public ?int $id = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}