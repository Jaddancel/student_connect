import { Calendar } from 'fullcalendar';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        var calendar = new Calendar(calendarEl, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            events: '/events/all',
            eventClick: function(info) {
                document.getElementById('modal_title').innerText = info.event.title;
                let body = `
                    <p><strong>Start:</strong> ${info.event.start.toLocaleString()}</p>
                    <p><strong>End:</strong> ${info.event.end ? info.event.end.toLocaleString() : 'N/A'}</p>
                `;
                if (info.event.extendedProps.location) {
                    body += `<p><strong>Location:</strong> ${info.event.extendedProps.location}</p>`;
                }
                if (info.event.extendedProps.description) {
                    body += `<p><strong>Description:</strong> ${info.event.extendedProps.description}</p>`;
                }
                document.getElementById('modal_body').innerHTML = body;
                document.getElementById('event_modal').showModal();
            }
        });
        calendar.render();
    }
});
