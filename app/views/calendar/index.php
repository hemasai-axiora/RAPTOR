<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<style>
    .calendar-container {
        background-color: var(--panel-dark);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        box-shadow: var(--shadow-soft);
        overflow: hidden;
        margin-top: 1.5rem;
    }
    .fc {
        font-family: inherit;
        background-color: transparent;
        color: var(--text-primary);
    }
    .fc-theme-standard td, .fc-theme-standard th, .fc-theme-standard .fc-scrollgrid {
        border-color: var(--border-color) !important;
    }
    .fc-header-toolbar {
        padding: 1.25rem;
        background: var(--surface-soft);
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 0 !important;
    }
    .fc-toolbar-title {
        font-size: 1.25rem !important;
        font-weight: 700;
        color: var(--text-primary);
    }
    .fc-button {
        padding: 0.45rem 0.85rem !important;
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        border-radius: 8px !important;
        text-transform: capitalize !important;
        transition: all 0.2s ease !important;
    }
    .fc-button-primary {
        background-color: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #fff !important;
    }
    .fc-button-primary:hover {
        background-color: var(--primary-strong) !important;
        border-color: var(--primary-strong) !important;
    }
    .fc-button-primary:disabled {
        background-color: var(--surface-muted) !important;
        border-color: var(--border-color) !important;
        color: var(--text-muted) !important;
        opacity: 0.6;
    }
    .fc-button-active {
        background-color: var(--primary-strong) !important;
        border-color: var(--primary-strong) !important;
        box-shadow: 0 0 0 3px var(--primary-glow) !important;
    }
    .fc-day-today {
        background-color: rgba(56, 189, 248, 0.08) !important;
    }
    .fc-col-header-cell {
        background-color: var(--surface-soft);
        padding: 0.75rem 0 !important;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .fc-daygrid-day-number {
        font-size: 0.85rem;
        font-weight: 500;
        padding: 6px 8px !important;
        color: var(--text-secondary);
    }
    .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
        color: var(--primary);
        font-weight: 700;
    }
    .fc-daygrid-event {
        border-radius: 6px !important;
        padding: 3px 6px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        border: none !important;
        margin: 2px 4px !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.15s ease, opacity 0.15s ease;
    }
    .fc-daygrid-event:hover {
        transform: translateY(-1px);
        opacity: 0.9;
    }
    .modal-content {
        background-color: var(--panel-dark);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 14px;
        box-shadow: var(--shadow-soft);
    }
    .modal-header {
        border-bottom: 1px solid var(--border-color);
    }
    .modal-footer {
        border-top: 1px solid var(--border-color);
    }
</style>

<?php if (isset($_SESSION['calendar_error'])): ?>
    <div class="alert alert-danger border-0 shadow mb-3" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['calendar_error']; unset($_SESSION['calendar_error']); ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['calendar_success'])): ?>
    <div class="alert alert-success border-0 shadow mb-3" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
        <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['calendar_success']; unset($_SESSION['calendar_success']); ?>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h4 class="text-white mb-1"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Content & Events Calendar</h4>
        <div class="text-secondary" style="font-size:0.9rem;">Interactive visual workspace for content schedules, meetings, and marketing campaigns.</div>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal" style="background: var(--primary); border: none; border-radius: 8px; padding: 0.5rem 1rem;">
        <i class="fa-solid fa-calendar-plus me-2"></i>Schedule Event
    </button>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" style="border-radius: 20px; font-size: 0.78rem; font-weight: 600;"><i class="fa-solid fa-calendar me-1"></i> General Events</span>
    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2" style="border-radius: 20px; font-size: 0.78rem; font-weight: 600;"><i class="fa-solid fa-video me-1"></i> Meetings & Demos</span>
    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2" style="border-radius: 20px; font-size: 0.78rem; font-weight: 600;"><i class="fa-solid fa-list-check me-1"></i> Tasks & Deadlines</span>
    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2" style="border-radius: 20px; font-size: 0.78rem; font-weight: 600;"><i class="fa-solid fa-bullhorn me-1"></i> Campaigns</span>
</div>

<div class="calendar-container">
    <div id="calendar" style="padding: 1rem;"></div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white" id="detailTitle">Event Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <span class="badge" id="detailBadge" style="font-size: 0.8rem; text-transform: uppercase;"></span>
                </div>
                <div class="mb-3">
                    <label class="text-secondary small d-block">Schedule / Duration</label>
                    <span class="text-white font-monospace" id="detailTime"></span>
                </div>
                <div class="mb-3">
                    <label class="text-secondary small d-block">Description & Details</label>
                    <div class="text-white p-3 rounded bg-dark border border-secondary-subtle" id="detailDescription" style="white-space: pre-wrap; font-size: 0.9rem;"></div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <form id="deleteEventForm" method="POST" style="display: none;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this event?');">
                            <i class="fa-solid fa-trash me-2"></i>Delete Event
                        </button>
                    </form>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="index.php?route=calendar/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-header">
                    <h5 class="modal-title text-white"><i class="fa-solid fa-calendar-plus me-2 text-primary"></i>Schedule Custom Event</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="event_title" class="form-label text-secondary">Event Title</label>
                        <input type="text" class="form-control" name="title" id="event_title" placeholder="e.g. Content Strategy Brainstorming" required>
                    </div>
                    <div class="mb-3">
                        <label for="event_client" class="form-label text-secondary">Associated Client (Optional)</label>
                        <select class="form-select" name="client_id" id="event_client">
                            <option value="">None / General</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client->client_id; ?>"><?php echo htmlspecialchars($client->company_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label text-secondary">Start Date & Time</label>
                            <input type="datetime-local" class="form-control" name="start_date" id="start_date" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label text-secondary">End Date & Time</label>
                            <input type="datetime-local" class="form-control" name="end_date" id="end_date" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="event_desc" class="form-label text-secondary">Description / Remarks</label>
                        <textarea class="form-control" name="description" id="event_desc" rows="3" placeholder="Specify event agenda, room details, or prep guidelines."></textarea>
                    </div>
                </div>
                <div class="modal-header d-flex justify-content-end gap-2 border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background: var(--primary); border: none;">Save Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var eventsList = <?php echo $events_json; ?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            editable: false,
            selectable: false,
            events: eventsList,
            eventClick: function(info) {
                var props = info.event.extendedProps;
                
                document.getElementById('detailTitle').innerText = info.event.title;
                document.getElementById('detailDescription').innerText = props.details || 'No description provided.';
                
                // Set badge class and text
                var badge = document.getElementById('detailBadge');
                badge.innerText = props.type;
                badge.className = 'badge';
                if (props.type === 'meeting') {
                    badge.classList.add('bg-warning-subtle', 'text-warning', 'border', 'border-warning-subtle');
                } else if (props.type === 'task') {
                    badge.classList.add('bg-danger-subtle', 'text-danger', 'border', 'border-danger-subtle');
                } else {
                    badge.classList.add('bg-primary-subtle', 'text-primary', 'border', 'border-primary-subtle');
                }

                // Format Time range
                var timeText = info.event.start.toLocaleString();
                if (info.event.end) {
                    timeText += ' - ' + info.event.end.toLocaleString();
                }
                document.getElementById('detailTime').innerText = timeText;

                // Handle Delete button visibility
                var deleteForm = document.getElementById('deleteEventForm');
                if (props.can_delete && props.db_id) {
                    deleteForm.action = 'index.php?route=calendar/delete/' + props.db_id;
                    deleteForm.style.display = 'block';
                } else {
                    deleteForm.style.display = 'none';
                }

                var detailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
                detailModal.show();
            }
        });
        calendar.render();

        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        if (startDateInput && endDateInput) {
            startDateInput.addEventListener('change', function() {
                if (startDateInput.value) {
                    const start = new Date(startDateInput.value);
                    start.setHours(start.getHours() + 1);
                    const yyyy = start.getFullYear();
                    const mm = String(start.getMonth() + 1).padStart(2, '0');
                    const dd = String(start.getDate()).padStart(2, '0');
                    const hh = String(start.getHours()).padStart(2, '0');
                    const min = String(start.getMinutes()).padStart(2, '0');
                    endDateInput.value = `${yyyy}-${mm}-${dd}T${hh}:${min}`;
                    endDateInput.min = startDateInput.value;
                }
            });
            
            endDateInput.addEventListener('change', function() {
                if (startDateInput.value && endDateInput.value) {
                    if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                        alert('End date/time cannot be earlier than start date/time.');
                        endDateInput.value = '';
                    }
                }
            });
        }
    });
</script>
