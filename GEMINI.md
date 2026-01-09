## Instructions for you:
- Follow the existing code style and patterns
- Make incremental changes to execute the prompt
- Ask follow-up questions if needed
- Write tests for new functionality
- Document your changes clearly
- Do not try to run migrations, do not make commits.
- We are running in Docker Compose, in order to run tests, call `docker compose exec -w /application/demo php-fpm ./vendor/bin/phpunit tests`.

## Project details:

This is a demo PHP app built with Nette framework.

We are focused on implementing an automatic notification system for events.
The system will send SMS notifications to users when events are created or updated.

The relevant code is within `demo` dir, and uses the term Event, e.g. 
* app/Presentation/Event
* app/Model/Event
* tests/Service/EventServiceTest



