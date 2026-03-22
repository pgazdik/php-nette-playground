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

        public ?int $id = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}