<?php
namespace App\Model\Entity;

use DateTime;

class Event
{
    public function __construct(
        public string $patientName,
        public string $phoneNumber,
        public string $doctorName,
        public string $doctorAddress,
        public DateTime $appointmentDate,
        public ?string $attachmentContent = null,
        public ?string $attachmentName = null,
        public ?string $attachmentType = null,
        public ?int $id = null,
        public ?DateTime $createdAt = null,
    ) {
    }
}