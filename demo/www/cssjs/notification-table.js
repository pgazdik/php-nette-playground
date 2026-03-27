document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('table.data-table tbody tr');
    
    const idToRows = [];

    tableRows.forEach(row => {
        const eventId = row.getAttribute('event-id');
        if (eventId) {
            idToRows[eventId] = idToRows[eventId] || [];
            idToRows[eventId].push(row);
        }

        row.addEventListener('mouseenter', function() {
            const eventId = this.getAttribute('event-id');
            if (eventId) {
                // Highlight all rows with the same Event ID
                idToRows[eventId].forEach(otherRow => {
                    otherRow.style.backgroundColor = 'rgba(0, 0, 0, 0.1)';
                });
            }
        });

        row.addEventListener('mouseleave', function() {
            const eventId = this.getAttribute('event-id');
            if (eventId) {
                // Remove highlight from all rows with the same Event ID
                idToRows[eventId].forEach(otherRow => {
                    otherRow.style.backgroundColor = '';
                });
            }
        });
    });
});
