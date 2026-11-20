
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

$allowedStatuses = ['Available', 'Occupied', 'Reserved', 'Maintenance'];
$allowedCleaning = ['Clean', 'Dirty', 'In Progress'];

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function statusClass(string $status): string {
    return match ($status) {
        'Available', 'Clean' => 'green',
        'Occupied', 'Dirty' => 'red',
        'Reserved' => 'gold',
        'Maintenance', 'In Progress' => 'blue',
        default => 'neutral'
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $roomNumber = trim($_POST['room_number'] ?? '');
            $roomTypeId = (int)($_POST['room_type_id'] ?? 0);
            $price = filter_var(
                $_POST['price_per_night'] ?? '',
                FILTER_VALIDATE_FLOAT
            );
            $bedType = trim($_POST['bed_type'] ?? '');
            $status = $_POST['status'] ?? 'Available';
            $cleaning = $_POST['cleaning_status'] ?? 'Clean';

            if (
                $roomNumber === '' ||
                $roomTypeId < 1 ||
                $price === false ||
                $price < 0 ||
                $bedType === ''
            ) {
                throw new Exception('Please enter valid details in all fields.');
            }

            if (
                !in_array($status, $allowedStatuses, true) ||
                !in_array($cleaning, $allowedCleaning, true)
            ) {
                throw new Exception('Please select valid room statuses.');
            }

            $check = $conn->prepare(
                "SELECT id FROM room_types WHERE id = ?"
            );
            $check->bind_param("i", $roomTypeId);
            $check->execute();
            $validType = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$validType) {
                throw new Exception('Please select a valid room type.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE rooms
                     SET room_number=?, room_type_id=?, price_per_night=?,
                         bed_type=?, status=?, cleaning_status=?
                     WHERE id=?"
                );

                $stmt->bind_param(
                    "sidsssi",
                    $roomNumber,
                    $roomTypeId,
                    $price,
                    $bedType,
                    $status,
                    $cleaning,
                    $id
                );
                $stmt->execute();
                $stmt->close();

                header('Location: rooms.php?notice=updated');
                exit;
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO rooms
                     (room_number, room_type_id, price_per_night,
                      bed_type, status, cleaning_status)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sidsss",
                    $roomNumber,
                    $roomTypeId,
                    $price,
                    $bedType,
                    $status,
                    $cleaning
                );
                $stmt->execute();
                $stmt->close();

                header('Location: rooms.php?notice=added');
                exit;
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id < 1) {
                throw new Exception('Invalid room selected.');
            }

            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            header(
                'Location: rooms.php?notice=' .
                ($deleted > 0 ? 'deleted' : 'notfound')
            );
            exit;
        }
    } catch (Throwable $e) {
        if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
            $error = 'This room number already exists. Use another number.';
        } elseif ($e instanceof mysqli_sql_exception && $e->getCode() === 1451) {
            $error = 'This room has booking records and cannot be deleted.';
        } else {
            $error = $e instanceof mysqli_sql_exception
                ? 'Database error. Please check your room tables and fields.'
                : $e->getMessage();
        }
    }
}

$notices = [
    'added' => 'Room added successfully!',
    'updated' => 'Room updated successfully!',
    'deleted' => 'Room deleted successfully!',
    'notfound' => 'Room was not found.'
];

$message = $notices[$_GET['notice'] ?? ''] ?? '';

$editRoom = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editRoom = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$roomTypesForForm = $conn->query(
    "SELECT id, type_name FROM room_types ORDER BY type_name"
);

$rooms = $conn->query(
    "SELECT r.*, rt.type_name
     FROM rooms r
     LEFT JOIN room_types rt ON r.room_type_id = rt.id
     ORDER BY r.room_number"
);

$totalRooms = $rooms->num_rows;
$availableCount = 0;
$occupiedCount = 0;
$maintenanceCount = 0;

$roomRows = [];

while ($room = $rooms->fetch_assoc()) {
    $roomRows[] = $room;

    if ($room['status'] === 'Available') {
        $availableCount++;
    } elseif ($room['status'] === 'Occupied') {
        $occupiedCount++;
    } elseif ($room['status'] === 'Maintenance') {
        $maintenanceCount++;
    }
}

$adminName = $_SESSION['admin_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Room Management | GrandVista</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #142b3b;
    --navy-light: #203e51;
    --gold: #c8a56b;
    --cream: #f7f6f2;
    --white: #fff;
    --text: #263747;
    --muted: #7c8790;
    --border: #e9e7e0;
    --green: #23845b;
    --red: #b84747;
    --blue: #356e9c;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    background: var(--cream);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
}

a { text-decoration: none; }

.sidebar {
    width: 245px;
    background: var(--navy);
    color: white;
    position: fixed;
    inset: 0 auto 0 0;
    padding: 28px 18px;
    display: flex;
    flex-direction: column;
    z-index: 5;
}

.brand {
    padding: 0 12px 28px;
    border-bottom: 1px solid #ffffff20;
}

.brand-mark {
    color: var(--gold);
    font-size: 29px;
}

.brand h2 {
    font-family: 'Playfair Display', serif;
    font-size: 25px;
    margin: 7px 0 3px;
}

.brand p {
    color: #c3cbd0;
    margin: 0;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.nav-label {
    margin: 28px 12px 12px;
    color: #aab7c0;
    font-size: 10px;
    letter-spacing: 1.8px;
    text-transform: uppercase;
}

.nav-link {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 13px 14px;
    border-radius: 9px;
    color: #dce3e8;
    margin: 3px 0;
    font-size: 13px;
    transition: .25s;
}

.nav-link:hover, .nav-link.active {
    background: #ffffff12;
    color: var(--gold);
}

.nav-link.active {
    border-left: 3px solid var(--gold);
}

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid #ffffff20;
    padding-top: 18px;
}

.main {
    margin-left: 245px;
    padding: 30px 36px 50px;
    max-width: 1700px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 28px;
}

.eyebrow {
    color: #a17c43;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 700;
}

h1, h2, h3 { font-family: 'Playfair Display', serif; }

.topbar h1 {
    margin: 7px 0;
    font-size: clamp(28px, 3vw, 38px);
    color: var(--navy);
}

.subtitle {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.7;
}

.profile {
    display: flex;
    align-items: center;
    gap: 11px;
    background: white;
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 12px;
}

.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--navy);
    color: var(--gold);
    display: grid;
    place-items: center;
    font-size: 18px;
}

.profile strong { display: block; font-size: 12px; }
.profile small { color: var(--muted); font-size: 11px; }

.hero {
    border-radius: 17px;
    padding: 29px;
    margin-bottom: 23px;
    color: white;
    background:
        linear-gradient(90deg, rgba(20,43,59,.97), rgba(20,43,59,.76)),
        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1400&q=80') center/cover;
    box-shadow: 0 10px 28px #142b3b15;
}

.hero h2 {
    font-size: 27px;
    margin: 8px 0;
}

.hero p {
    max-width: 570px;
    color: #e2e7e9;
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 0;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 25px;
}

.stat {
    background: white;
    border: 1px solid var(--border);
    padding: 19px;
    border-radius: 13px;
    transition: transform .25s, box-shadow .25s;
}

.stat:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px #142b3b0d;
}

.stat-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    background: #f6f0e5;
    border-radius: 11px;
    font-size: 20px;
    margin-bottom: 14px;
}

.stat small { color: var(--muted); font-size: 12px; }
.stat h3 {
    margin: 7px 0 0;
    font-size: 29px;
    color: var(--navy);
}

.panel {
    background: white;
    border: 1px solid var(--border);
    padding: 25px;
    margin-bottom: 24px;
    border-radius: 15px;
    box-shadow: 0 5px 20px #142b3b05;
    animation: rise .45s ease both;
}

@keyframes rise {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 21px;
}

.panel-heading h2 {
    margin: 5px 0 0;
    font-size: 24px;
    color: var(--navy);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 8px;
}

.field input, .field select {
    width: 100%;
    padding: 12px 13px;
    background: #fdfdfc;
    border: 1px solid #e0e3e4;
    border-radius: 8px;
    color: var(--text);
    font: inherit;
    font-size: 13px;
    outline: none;
    transition: .2s;
}

.field input:focus, .field select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px #c8a56b20;
}

.actions {
    display: flex;
    gap: 9px;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 22px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 16px;
    border: 0;
    border-radius: 8px;
    background: var(--navy);
    color: white;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.btn:hover { background: var(--navy-light); transform: translateY(-1px); }
.btn-gold { background: var(--gold); color: var(--navy); }
.btn-gold:hover { background: #d7b982; }
.btn-muted { background: #edf0f2; color: var(--text); }
.btn-muted:hover { background: #e1e5e8; }
.btn-delete { background: #fff0ef; color: #ad3434; }
.btn-delete:hover { background: #fbe0de; }
.btn-small { padding: 8px 11px; font-size: 11px; }

.notice {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
    animation: rise .3s ease;
}
.success { background: #e9f6ee; color: #206c43; border: 1px solid #d1eadb; }
.error { background: #fff0ef; color: #a52d2d; border: 1px solid #f2d4d1; }

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 14px 12px;
    background: #f8f7f3;
    color: #697781;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    white-space: nowrap;
}

td {
    padding: 15px 12px;
    border-bottom: 1px solid #efeee9;
    font-size: 12px;
    vertical-align: middle;
}

tbody tr { transition: background .2s; }
tbody tr:hover { background: #fcfbf8; }
tbody tr:last-child td { border-bottom: 0; }

.room-number {
    font-weight: 700;
    color: var(--navy);
    font-size: 13px;
}

.room-type {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 11px;
}

.price { font-weight: 700; white-space: nowrap; }

.badge {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.badge.green { background: #e7f5ec; color: var(--green); }
.badge.red { background: #fdebea; color: var(--red); }
.badge.gold { background: #fbf1dd; color: #96702f; }
.badge.blue { background: #e9f1fa; color: var(--blue); }
.badge.neutral { background: #eff0f1; color: #606a72; }

.action-group { display: flex; gap: 7px; align-items: center; }
.action-group form { margin: 0; }

.empty {
    padding: 40px 15px;
    text-align: center;
    color: var(--muted);
}

.empty-icon { font-size: 35px; margin-bottom: 10px; }

.footer {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    padding: 10px;
}

@media (max-width: 1100px) {
    .sidebar { width: 205px; }
    .main { margin-left: 205px; padding: 25px 22px; }
    .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        padding: 15px;
    }
    .brand { padding-bottom: 13px; }
    .brand h2 { font-size: 22px; }
    .nav-label { margin-top: 15px; }
    .sidebar nav { display: flex; flex-wrap: wrap; gap: 5px; }
    .nav-link { padding: 10px; font-size: 11px; }
    .sidebar-bottom { margin-top: 12px; padding-top: 10px; }
    .main { margin-left: 0; padding: 22px 14px; }
    .topbar { align-items: flex-start; }
    .hero { padding: 22px; }
    .hero h2 { font-size: 23px; }
    .stats { gap: 10px; }
    .stat { padding: 14px; }
    .stat h3 { font-size: 25px; }
    .form-grid { grid-template-columns: 1fr; }
    .panel { padding: 17px; }
}
</style>
</head>

<body>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-mark">✦</div>
        <h2>GrandVista</h2>
        <p>Hotel Management</p>
    </div>

    <div class="nav-label">Main Menu</div>
    <nav>
        <a class="nav-link" href="admin-dashboard.php"><span>▦</span> Dashboard</a>
        <a class="nav-link active" href="rooms.php"><span>▣</span> Room Management</a>
        <a class="nav-link" href="customers.php"><span>♙</span> Customers</a>
        <a class="nav-link" href="bookings.php"><span>▤</span> Bookings</a>
        <a class="nav-link" href="payments.php"><span>₹</span> Payments</a>
    </nav>

    <div class="sidebar-bottom">
        <a class="nav-link" href="admin-logout.php"><span>↪</span> Logout</a>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <div class="eyebrow">GrandVista / Management</div>
            <h1>Room Management</h1>
            <p class="subtitle">Manage room inventory, nightly rates and housekeeping status.</p>
        </div>
        <div class="profile">
            <div class="avatar">♙</div>
            <div>
                <strong><?= h($adminName) ?></strong>
                <small>Administrator</small>
            </div>
        </div>
    </div>

    <section class="hero">
        <div class="eyebrow" style="color:#dfc18d">Your Property, Perfectly Managed</div>
        <h2>Every Room Has a Story.</h2>
        <p>Keep your room inventory organised, maintain service standards and ensure every guest finds their perfect stay.</p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice success">✓ &nbsp;<?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error">! &nbsp;<?= h($error) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat">
            <div class="stat-icon">▣</div>
            <small>Total Rooms</small>
            <h3><?= $totalRooms ?></h3>
        </div>
        <div class="stat">
            <div class="stat-icon">✓</div>
            <small>Available Rooms</small>
            <h3><?= $availableCount ?></h3>
        </div>
        <div class="stat">
            <div class="stat-icon">♙</div>
            <small>Occupied Rooms</small>
            <h3><?= $occupiedCount ?></h3>
        </div>
        <div class="stat">
            <div class="stat-icon">⚙</div>
            <small>Under Maintenance</small>
            <h3><?= $maintenanceCount ?></h3>
        </div>
    </section>

    <section class="panel" id="room-form">
        <div class="panel-heading">
            <div>
                <div class="eyebrow"><?= $editRoom ? 'Update Inventory' : 'Room Inventory' ?></div>
                <h2><?= $editRoom ? 'Edit Room Details' : 'Add a New Room' ?></h2>
            </div>
            <span class="badge gold"><?= $editRoom ? 'EDIT MODE' : 'NEW ROOM' ?></span>
        </div>

        <?php if ($roomTypesForForm->num_rows === 0): ?>
            <div class="notice error">
                No room types found. Please add room types to the
                <strong>room_types</strong> table first.
            </div>
        <?php else: ?>
        <form method="POST" action="rooms.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h($editRoom['id'] ?? 0) ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="room_number">Room Number</label>
                    <input id="room_number" name="room_number"
                           placeholder="e.g. 101"
                           value="<?= h($editRoom['room_number'] ?? '') ?>"
                           maxlength="20" required>
                </div>

                <div class="field">
                    <label for="room_type_id">Room Type</label>
                    <select id="room_type_id" name="room_type_id" required>
                        <option value="">Choose room type</option>
                        <?php while ($type = $roomTypesForForm->fetch_assoc()): ?>
                        <option value="<?= (int)$type['id'] ?>"
                            <?= (int)($editRoom['room_type_id'] ?? 0) === (int)$type['id'] ? 'selected' : '' ?>>
                            <?= h($type['type_name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="price_per_night">Price Per Night (₹)</label>
                    <input id="price_per_night" name="price_per_night"
                           type="number" min="0" step="0.01"
                           placeholder="e.g. 3500"
                           value="<?= h($editRoom['price_per_night'] ?? '') ?>"
                           required>
                </div>

                <div class="field">
                    <label for="bed_type">Bed Type</label>
                    <select id="bed_type" name="bed_type" required>
                        <?php foreach (['Single', 'Twin', 'Queen', 'King', 'Double'] as $bed): ?>
                        <option value="<?= h($bed) ?>"
                            <?= ($editRoom['bed_type'] ?? '') === $bed ? 'selected' : '' ?>>
                            <?= h($bed) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="status">Room Status</label>
                    <select id="status" name="status" required>
                        <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= h($status) ?>"
                            <?= ($editRoom['status'] ?? 'Available') === $status ? 'selected' : '' ?>>
                            <?= h($status) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="cleaning_status">Cleaning Status</label>
                    <select id="cleaning_status" name="cleaning_status" required>
                        <?php foreach ($allowedCleaning as $cleaning): ?>
                        <option value="<?= h($cleaning) ?>"
                            <?= ($editRoom['cleaning_status'] ?? 'Clean') === $cleaning ? 'selected' : '' ?>>
                            <?= h($cleaning) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="actions">
                <button class="btn btn-gold" type="submit">
                    <?= $editRoom ? '✓ Update Room' : '+ Add Room' ?>
                </button>
                <?php if ($editRoom): ?>
                    <a class="btn btn-muted" href="rooms.php">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <div class="eyebrow">Property Inventory</div>
                <h2>All Rooms</h2>
                <p class="subtitle">A complete overview of your hotel's rooms and housekeeping.</p>
            </div>
            <span class="badge blue"><?= $totalRooms ?> Rooms Listed</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Room Type</th>
                        <th>Price / Night</th>
                        <th>Bed Type</th>
                        <th>Room Status</th>
                        <th>Housekeeping</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($roomRows) === 0): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty">
                                <div class="empty-icon">▣</div>
                                <strong>No rooms added yet</strong>
                                <p>Add your first room using the form above.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($roomRows as $room): ?>
                    <tr>
                        <td>
                            <span class="room-number">Room <?= h($room['room_number']) ?></span>
                        </td>
                        <td><?= h($room['type_name'] ?? 'Unknown') ?></td>
                        <td class="price">₹<?= number_format((float)$room['price_per_night'], 2) ?></td>
                        <td><?= h($room['bed_type']) ?></td>
                        <td>
                            <span class="badge <?= h(statusClass($room['status'])) ?>">
                                <?= h($room['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= h(statusClass($room['cleaning_status'])) ?>">
                                <?= h($room['cleaning_status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-group">
                                <a class="btn btn-small" href="rooms.php?edit=<?= (int)$room['id'] ?>#room-form">
                                    Edit
                                </a>
                                <form method="POST" action="rooms.php"
                                      onsubmit="return confirm('Are you sure you want to delete this room?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
                                    <button class="btn btn-small btn-delete" type="submit">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer">© <?= date('Y') ?> GrandVista Hotel Management · Crafted for exceptional stays</div>
</main>
</body>
</html>
