<?php
namespace App\Service;

use App\Model\Entity\Event\Event;
use App\Utils\DateUtils;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class EventRepository
{
    public function __construct(
        private Explorer $database
    ) {
    }

    public function getById(int $eventId): ?Event
    {
        $row = $this->database->table('event')->get($eventId);
        return $row ? self::toEvent($row) : null;
    }

    public function insert(Event $event): void
    {
        $row = $this->database->table('event')->insert([
            'patient_name' => $event->patientName,
            'phone_number' => $event->phoneNumber,
            'doctor_name' => $event->doctorName,
            'doctor_address' => $event->doctorAddress,
            'appointment_date' => DateUtils::baToUtc($event->appointmentDate),
        ]);

        $event->id = $row->id;
        \Tracy\Debugger::log("Created new event with ID: " . $event->id);
    }

    /** @return Event[] */
    public function listAll(): array
    {
        $rows = $this->database->table('event')
            ->order('appointment_date ASC')
            ->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            $events[] = self::toEvent($row);
        }
        return $events;
    }

    private static function toEvent(ActiveRow $row): Event
    {
        return new Event(
            patientName: $row->patient_name,
            phoneNumber: $row->phone_number,
            doctorName: $row->doctor_name,
            doctorAddress: $row->doctor_address,
            appointmentDate: DateUtils::utcToBa($row->appointment_date),
            id: $row->id,
            createdAt: DateUtils::utcToBa($row->created_at),
            updatedAt: DateUtils::utcToBa($row->updated_at),
        );
    }
}
