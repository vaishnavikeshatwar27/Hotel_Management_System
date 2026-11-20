
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$checkin = $_POST['checkin'] ?? '';
$checkout = $_POST['checkout'] ?? '';
$guests = filter_var(
    $_POST['guests'] ?? 2,
    FILTER_VALIDATE_INT
);

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout) ||
    !$guests || $guests < 1
) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter valid dates and guest count.'
    ]);
    exit;
}

$today = date('Y-m-d');

if ($checkin < $today || $checkout <= $checkin) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select valid future dates.'
    ]);
    exit;
}

$sql = "
    SELECT
        r.id,
        r.room_number,
        r.price_per_night,
        r.bed_type,
        rt.type_name,
        rt.capacity
    FROM rooms r
    INNER JOIN room_types rt
        ON rt.id = r.room_type_id
    WHERE r.status = 'Available'
      AND rt.capacity >= ?
      AND NOT EXISTS (
          SELECT 1
          FROM bookings b
          WHERE b.room_id = r.id
            AND b.status IN ('Confirmed', 'Checked In')
            AND b.check_in < ?
            AND b.check_out > ?
      )
    ORDER BY r.price_per_night ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not check availability.'
    ]);
    exit;
}

$stmt->bind_param('iss', $guests, $checkout, $checkin);
$stmt->execute();

$result = $stmt->get_result();
$rooms = [];

while ($row = $result->fetch_assoc()) {
    $rooms[] = $row;
}

echo json_encode([
    'success' => true,
    'count' => count($rooms),
    'rooms' => $rooms,
    'message' => count($rooms) > 0
        ? 'Rooms are available for your selected dates!'
        : 'Sorry, no rooms are available for your selected dates.'
]);

$stmt->close();
$conn->close();
?>
