<?php
/**
 * Events / Albums API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($id) {
            $stmt = $db->prepare("
                SELECT e.*, 
                    (SELECT COUNT(*) FROM photos WHERE event_id = e.id) AS photo_count,
                    (SELECT COUNT(*) FROM photo_faces pf JOIN photos p ON pf.photo_id = p.id WHERE p.event_id = e.id) AS total_faces
                FROM events e 
                WHERE e.id = ? 
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $event = $stmt->fetch();

            if (!$event) {
                json_response(['success' => false, 'message' => 'Event not found'], 404);
            }

            json_response(['success' => true, 'data' => $event]);
        } else {
            // Get all events
            $isAdmin = is_admin_logged_in();
            $whereClause = $isAdmin ? "" : "WHERE e.is_active = 1";

            $stmt = $db->query("
                SELECT e.*, 
                    (SELECT COUNT(*) FROM photos WHERE event_id = e.id) AS photo_count,
                    (SELECT COUNT(*) FROM photo_faces pf JOIN photos p ON pf.photo_id = p.id WHERE p.event_id = e.id) AS total_faces,
                    (SELECT file_path FROM photos WHERE event_id = e.id ORDER BY id DESC LIMIT 1) AS sample_photo
                FROM events e 
                $whereClause
                ORDER BY e.event_date DESC, e.id DESC
            ");
            $events = $stmt->fetchAll();

            json_response(['success' => true, 'data' => $events]);
        }
        break;

    case 'POST':
        require_admin_login();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $eventDate = !empty($input['event_date']) ? $input['event_date'] : date('Y-m-d');
        $location = trim($input['location'] ?? '');
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        if (empty($title)) {
            json_response(['success' => false, 'message' => 'Event title is required'], 400);
        }

        // Generate clean unique slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            $check = $db->prepare("SELECT id FROM events WHERE slug = ? LIMIT 1");
            $check->execute([$slug]);
            if (!$check->fetch()) break;
            $slug = $baseSlug . '-' . $counter++;
        }

        $stmt = $db->prepare("
            INSERT INTO events (title, slug, description, event_date, location, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $slug, $description, $eventDate, $location, $isActive]);
        $eventId = (int)$db->lastInsertId();

        json_response([
            'success' => true, 
            'message' => 'Event created successfully',
            'data' => [
                'id' => $eventId,
                'title' => $title,
                'slug' => $slug
            ]
        ], 201);
        break;

    case 'PUT':
        require_admin_login();
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            parse_str(file_get_contents('php://input'), $input);
        }

        $id = (int)($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $eventDate = !empty($input['event_date']) ? $input['event_date'] : null;
        $location = trim($input['location'] ?? '');
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        if (!$id || empty($title)) {
            json_response(['success' => false, 'message' => 'Event ID and Title are required'], 400);
        }

        $stmt = $db->prepare("
            UPDATE events 
            SET title = ?, description = ?, event_date = ?, location = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $eventDate, $location, $isActive, $id]);

        json_response(['success' => true, 'message' => 'Event updated successfully']);
        break;

    case 'DELETE':
        require_admin_login();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            json_response(['success' => false, 'message' => 'Event ID is required'], 400);
        }

        // Delete event (foreign keys with SET NULL or CASCADE handle photos)
        $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);

        json_response(['success' => true, 'message' => 'Event deleted successfully']);
        break;

    default:
        json_response(['success' => false, 'message' => 'Method not supported'], 405);
}
