<?php
$pageTitle = 'Events & Albums';
require_once __DIR__ . '/header.php';

$db = Database::getConnection();

// Fetch all events
$stmt = $db->query("
    SELECT e.*, 
        (SELECT COUNT(*) FROM photos WHERE event_id = e.id) AS photo_count,
        (SELECT COUNT(*) FROM photo_faces pf JOIN photos p ON pf.photo_id = p.id WHERE p.event_id = e.id) AS total_faces
    FROM events e 
    ORDER BY e.event_date DESC, e.id DESC
");
$events = $stmt->fetchAll();
?>

<div class="admin-card">
    <div class="card-header-row">
        <div>
            <div class="card-title">
                <i class="ri-calendar-event-line" style="color: var(--primary);"></i>
                <span>Manage Events & Albums</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">Organize photos by occasions, conferences, weddings, or galleries</div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openEventModal()">
            <i class="ri-add-line"></i> Create New Event
        </button>
    </div>

    <?php if (empty($events)): ?>
        <div style="text-align: center; padding: 56px 20px; color: var(--text-secondary);">
            <div style="font-size: 48px; margin-bottom: 12px; color: var(--text-muted);"><i class="ri-folder-add-line"></i></div>
            <div style="font-weight: 700; color: #ffffff; font-size: 1.15rem; margin-bottom: 4px;">No events created yet</div>
            <p style="font-size: 0.9rem; margin-bottom: 20px;">Create your first event album to start grouping photos.</p>
            <button class="btn btn-primary btn-sm" onclick="openEventModal()"><i class="ri-add-line"></i> Create Event</button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title & Description</th>
                        <th>Event Date</th>
                        <th>Location</th>
                        <th>Photos</th>
                        <th>Faces Indexed</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <tr id="event-row-<?php echo $ev['id']; ?>">
                            <td>
                                <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem;"><?php echo htmlspecialchars($ev['title']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; max-width: 320px;">
                                    <?php echo htmlspecialchars($ev['description'] ?: 'No description'); ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 600;">
                                    <i class="ri-calendar-line"></i> <?php echo $ev['event_date'] ? date('M d, Y', strtotime($ev['event_date'])) : 'N/A'; ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="ri-map-pin-line"></i> <?php echo htmlspecialchars($ev['location'] ?: 'Not specified'); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/admin/photos.php?event_id=<?php echo $ev['id']; ?>" style="font-weight: 700; color: #818cf8; text-decoration: none;">
                                    📸 <?php echo $ev['photo_count']; ?> photos
                                </a>
                            </td>
                            <td>
                                <span class="status-badge badge-pass">
                                    <i class="ri-user-smile-line"></i> <?php echo $ev['total_faces']; ?> faces
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $ev['is_active'] ? 'badge-pass' : 'badge-fail'; ?>">
                                    <?php echo $ev['is_active'] ? 'Active' : 'Hidden'; ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="<?php echo BASE_URL; ?>/admin/upload.php?event_id=<?php echo $ev['id']; ?>" class="btn btn-primary btn-sm" style="margin-right: 6px;" title="Upload photos into this event">
                                    <i class="ri-upload-2-line"></i> Upload
                                </a>
                                <button class="btn btn-secondary btn-sm" onclick='editEvent(<?php echo json_encode($ev); ?>)' style="margin-right: 6px;">
                                    <i class="ri-edit-line"></i> Edit
                                </button>
                                <button class="btn btn-secondary btn-sm" style="color: #f43f5e; border-color: rgba(244, 63, 94, 0.3);" onclick="deleteEvent(<?php echo $ev['id']; ?>, '<?php echo htmlspecialchars(addslashes($ev['title'])); ?>')">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Event Modal -->
<div class="admin-modal" id="eventModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalHeading">Create New Event</h3>
            <button type="button" class="modal-close-btn" onclick="closeEventModal()">&times;</button>
        </div>

        <form id="eventForm" onsubmit="saveEvent(event)">
            <div class="modal-body">
                <input type="hidden" id="eventId" name="id" value="">

                <div class="form-group">
                    <label class="form-label">Event / Album Title *</label>
                    <input type="text" id="eventTitle" name="title" class="input-control" placeholder="e.g. Annual Gala 2026, Wedding Ceremony" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Event Date</label>
                        <input type="date" id="eventDate" name="event_date" class="input-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" id="eventLocation" name="location" class="input-control" placeholder="e.g. Grand Ballroom">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea id="eventDescription" name="description" class="input-control" rows="3" placeholder="Brief details about the event..."></textarea>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; font-size: 0.88rem; font-weight: 600; color: var(--text-primary); cursor: pointer;">
                        <input type="checkbox" id="eventIsActive" name="is_active" value="1" checked style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>Active & Visible to Public Face Search</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeEventModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="saveEventBtn">Save Event</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEventModal() {
    document.getElementById('modalHeading').textContent = 'Create New Event';
    document.getElementById('eventId').value = '';
    document.getElementById('eventTitle').value = '';
    document.getElementById('eventDate').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('eventLocation').value = '';
    document.getElementById('eventDescription').value = '';
    document.getElementById('eventIsActive').checked = true;
    document.getElementById('eventModal').classList.add('active');
}

function editEvent(ev) {
    document.getElementById('modalHeading').textContent = 'Edit Event';
    document.getElementById('eventId').value = ev.id;
    document.getElementById('eventTitle').value = ev.title;
    document.getElementById('eventDate').value = ev.event_date || '';
    document.getElementById('eventLocation').value = ev.location || '';
    document.getElementById('eventDescription').value = ev.description || '';
    document.getElementById('eventIsActive').checked = (ev.is_active == 1);
    document.getElementById('eventModal').classList.add('active');
}

function closeEventModal() {
    document.getElementById('eventModal').classList.remove('active');
}

async function saveEvent(e) {
    e.preventDefault();
    const id = document.getElementById('eventId').value;
    const title = document.getElementById('eventTitle').value;
    const event_date = document.getElementById('eventDate').value;
    const location = document.getElementById('eventLocation').value;
    const description = document.getElementById('eventDescription').value;
    const is_active = document.getElementById('eventIsActive').checked ? 1 : 0;

    const payload = { id, title, event_date, location, description, is_active };
    const method = id ? 'PUT' : 'POST';

    try {
        const res = await fetch('../api/events.php', {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Event saved successfully');
            location.reload();
        } else {
            alert(data.message || 'Error saving event');
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}

async function deleteEvent(id, title) {
    if (!confirm(`Are you sure you want to delete the event "${title}"?`)) return;

    try {
        const res = await fetch(`../api/events.php?id=${id}`, {
            method: 'DELETE'
        });
        const data = await res.json();
        if (data.success) {
            const row = document.getElementById(`event-row-${id}`);
            if (row) row.remove();
        } else {
            alert(data.message || 'Error deleting event');
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
