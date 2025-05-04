<?php

declare(strict_types=1);

namespace App\Presentation\Event;

use Nette;

final class EventPresenter extends Nette\Application\UI\Presenter
{
    public function renderShowCalendar(): void
    {
        // Static events for demonstration
        $this->template->events = [
            [
                'title' => 'Team Meeting',
                'start' => '09:00',
                'end' => '10:00',
                'day' => 'Monday'
            ],
            [
                'title' => 'Project Review',
                'start' => '14:00',
                'end' => '15:30',
                'day' => 'Tuesday'
            ],
            [
                'title' => 'Lunch Break',
                'start' => '12:00',
                'end' => '13:00',
                'day' => 'Wednesday'
            ],
            [
                'title' => 'Client Call',
                'start' => '10:30',
                'end' => '11:30',
                'day' => 'Thursday'
            ],
            [
                'title' => 'Team Building',
                'start' => '16:00',
                'end' => '18:00',
                'day' => 'Friday'
            ],
        ];
    }
}
