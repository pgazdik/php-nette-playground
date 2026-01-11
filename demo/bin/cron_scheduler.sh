#!/bin/bash

# The stupid crontab doesn't see environment variables and it's quite hard to force it
# So we load them from the .env file here...

while IFS= read -r line || [ -n "$line" ]; do
    # Trim whitespace and carriage returns
    line=$(echo "$line" | tr -d '\r')

    export "${line}"

done < "/application/.env"

# Execute the PHP script
cd /application/demo && php bin/scheduler.php