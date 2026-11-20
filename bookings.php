
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bookingBadge(string $status): string {
    return match ($status) {
        'Confirmed' => 'green',
        'Checked In' => 'blue',
        'Checked Out' => 'gold',
        'Cancelled' => 'red',
        default => 'neutral'
    };
}

$statuses = ['Pending', 'Confirmed', 'Checked In', 'Checked Out', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $customerId = (int)($_POST['customer_id'] ?? 0);
            $roomId = (int)($_POST['room_id'] ?? 0);
            $checkIn = $_POST['check_in'] ?? '';
            $checkOut = $_POST['check_out'] ?? '';
            $adults = (int)($_POST['adults'] ?? 1);
            $children = (int)($_POST['children'] ?? 0);
            $status = $_POST['status'] ?? 'Pending';

            $start = DateTime::createFromFormat('!Y-m-d', $checkIn);
            $end = DateTime::createFromFormat('!Y-m-d', $checkOut);

            if (
                !$start || $start->format('Y-m-d') !== $checkIn ||
                !$end || $end->format('Y-m-d') !== $checkOut ||
                $end <= $start
            ) {
                throw new Exception(
                    'Check-out date must be after check-in date.'
                );
            }

            if (
                $customerId < 1 || $roomId < 1 ||
                $adults < 1 || $adults > 20 ||
                $children < 0 || $children > 20 ||
                !in_array($status, $statuses, true)
            ) {
                throw new Exception('Please enter valid booking details.');
            }

            $stmt = $conn->prepare(
                "SELECT id FROM customers WHERE id = ?"
            );
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $customerExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$customerExists) {
                throw new Exception('Please select a valid customer.');
            }

            $stmt = $conn->prepare(
                "SELECT price_per_night FROM rooms WHERE id = ?"
            );
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $room = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$room) {
                throw new Exception('Please select a valid room.');
            }

            $nights = (int)$start->diff($end)->days;
            $totalAmount = $nights * (float)$room['price_per_night'];

            // Check overlapping bookings for the selected room.
            if (in_array($status, ['Pending', 'Confirmed', 'Checked In'], true)) {
                $sql = "SELECT id FROM bookings
                        WHERE room_id = ?
                        AND check_in < ?
                        AND check_out > ?
                        AND status IN ('Pending', 'Confirmed', 'Checked In')";

                if ($id > 0) {
                    $sql .= " AND id <> ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(
                        "issi", $roomId, $checkOut, $checkIn, $id
                    );
                } else {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(
                        "iss", $roomId, $checkOut, $checkIn
                    );
                }

                $stmt->execute();
                $conflict = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($conflict) {
                    throw new Exception(
                        'This room already has a booking during those dates. Choose another room or dates.'
                    );
                }
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE bookings
                     SET customer_id=?, room_id=?, check_in=?, check_out=?,
                         adults=?, children=?, total_amount=?, status=?
                     WHERE id=?"
                );

                // i, i, s, s, i, i, d, s, i
                $stmt->bind_param(
                    "iissiidsi",
                    $customerId,
                    $roomId,
                    $checkIn,
                    $checkOut,
                    $adults,
                    $children,
                    $totalAmount,
                    $status,
                    $id
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO bookings
                     (customer_id, room_id, check_in, check_out,
                      adults, children, total_amount, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iissiids",
                    $customerId,
                    $roomId,
                    $checkIn,
                    $checkOut,
                    $adults,
                    $children,
                    $totalAmount,
                    $status
                );
            }

            $stmt->execute();
            $stmt->close();

            header(
                'Location: bookings.php?notice=' .
                ($id > 0 ? 'updated' : 'created')
            );
            exit;

        } elseif ($action === 'status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if ($id < 1 || !in_array($status, $statuses, true)) {
                throw new Exception('Invalid booking or status.');
            }

            $stmt = $conn->prepare(
                "SELECT room_id, check_in, check_out
                 FROM bookings WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$current) {
                throw new Exception('Booking not found.');
            }

            if (in_array($status, ['Pending', 'Confirmed', 'Checked In'], true)) {
                $stmt = $conn->prepare(
                    "SELECT id FROM bookings
                     WHERE room_id = ?
                     AND check_in < ?
                     AND check_out > ?
                     AND status IN ('Pending', 'Confirmed', 'Checked In')
                     AND id <> ?
                     LIMIT 1"
                );

                $stmt->bind_param(
                    "issi",
                    $current['room_id'],
                    $current['check_out'],
                    $current['check_in'],
                    $id
                );

                $stmt->execute();
                $conflict = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($conflict) {
                    throw new Exception(
                        'Another active booking conflicts with these dates.'
                    );
                }
            }

            $stmt = $conn->prepare(
                "UPDATE bookings SET status = ? WHERE id = ?"
            );
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();
            $stmt->close();

            header('Location: bookings.php?notice=status');
            exit;
        }
    } catch (Throwable $e) {
        if ($e instanceof mysqli_sql_exception) {
            $error = 'Database error. Please check the bookings table and its fields.';
        } else {
            $error = $e->getMessage();
        }
    }
}

$notices = [
    'created' => 'Booking created successfully!',
    'updated' => 'Booking updated successfully!',
    'status' => 'Booking status updated successfully!'
];

$message = $notices[$_GET['notice'] ?? ''] ?? $message;

$editBooking = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editBooking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$customers = $conn->query(
    "SELECT id, full_name, phone FROM customers ORDER BY full_name"
);

$rooms = $conn->query(
    "SELECT id, room_number, price_per_night, status
     FROM rooms ORDER BY room_number"
);

$bookings = $conn->query(
    "SELECT b.*, c.full_name, r.room_number
     FROM bookings b
     JOIN customers c ON b.customer_id = c.id
     JOIN rooms r ON b.room_id = r.id
     ORDER BY b.id DESC"
);

$customerRows = $customers->fetch_all(MYSQLI_ASSOC);
$roomRows = $rooms->fetch_all(MYSQLI_ASSOC);
$bookingRows = $bookings->fetch_all(MYSQLI_ASSOC);

$totalBookings = count($bookingRows);
$pendingCount = 0;
$confirmedCount = 0;
$checkedInCount = 0;

foreach ($bookingRows as $b) {
    if ($b['status'] === 'Pending') $pendingCount++;
    if ($b['status'] === 'Confirmed') $confirmedCount++;
    if ($b['status'] === 'Checked In') $checkedInCount++;
}

$adminName = $_SESSION['admin_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Management | GrandVista</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --navy:#142b3b;
    --navy-light:#203e51;
    --gold:#c8a56b;
    --cream:#f7f6f2;
    --white:#fff;
    --text:#263747;
    --muted:#7c8790;
    --border:#e9e7e0;
    --green:#23845b;
    --red:#b84747;
    --blue:#356e9c;
}

* { box-sizing:border-box; }

body {
    margin:0;
    background:var(--cream);
    color:var(--text);
    font-family:'DM Sans',sans-serif;
}

a { text-decoration:none; }
h1,h2,h3 { font-family:'Playfair Display',serif; }

.sidebar {
    width:245px;
    background:var(--navy);
    color:white;
    position:fixed;
    inset:0 auto 0 0;
    padding:28px 18px;
    display:flex;
    flex-direction:column;
    z-index:5;
}

.brand { padding:0 12px 28px; border-bottom:1px solid #ffffff20; }
.brand-mark { color:var(--gold); font-size:29px; }
.brand h2 { font-size:25px; margin:7px 0 3px; }
.brand p {
    color:#c3cbd0;
    margin:0;
    font-size:11px;
    letter-spacing:2px;
    text-transform:uppercase;
}

.nav-label {
    margin:28px 12px 12px;
    color:#aab7c0;
    font-size:10px;
    letter-spacing:1.8px;
    text-transform:uppercase;
}

.nav-link {
    display:flex;
    gap:12px;
    align-items:center;
    padding:13px 14px;
    border-radius:9px;
    color:#dce3e8;
    margin:3px 0;
    font-size:13px;
    transition:.25s;
}

.nav-link:hover,.nav-link.active {
    background:#ffffff12;
    color:var(--gold);
}

.nav-link.active { border-left:3px solid var(--gold); }
.sidebar-bottom { margin-top:auto; border-top:1px solid #ffffff20; padding-top:18px; }

.main { margin-left:245px; padding:30px 36px 45px; max-width:1700px; }

.topbar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    flex-wrap:wrap;
    margin-bottom:28px;
}

.eyebrow {
    color:#a17c43;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:2px;
    font-weight:700;
}

.topbar h1 {
    margin:7px 0;
    font-size:clamp(28px,3vw,38px);
    color:var(--navy);
}

.subtitle { margin:0; color:var(--muted); font-size:13px; line-height:1.7; }

.profile {
    display:flex;
    align-items:center;
    gap:11px;
    background:white;
    padding:10px 15px;
    border:1px solid var(--border);
    border-radius:12px;
}

.avatar {
    width:38px;
    height:38px;
    border-radius:50%;
    background:var(--navy);
    color:var(--gold);
    display:grid;
    place-items:center;
    font-size:18px;
}

.profile strong { display:block; font-size:12px; }
.profile small { color:var(--muted); font-size:11px; }

.hero {
    border-radius:17px;
    padding:29px;
    margin-bottom:23px;
    color:white;
    background:
      linear-gradient(90deg,rgba(20,43,59,.97),rgba(20,43,59,.73)),
      url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1400&q=80') center/cover;
    box-shadow:0 10px 28px #142b3b15;
}

.hero h2 { font-size:27px; margin:8px 0; }
.hero p { max-width:650px; color:#e2e7e9; font-size:13px; line-height:1.8; margin-bottom:0; }

.stats {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:16px;
    margin-bottom:25px;
}

.stat {
    background:white;
    border:1px solid var(--border);
    padding:19px;
    border-radius:13px;
    transition:transform .25s,box-shadow .25s;
}

.stat:hover { transform:translateY(-4px); box-shadow:0 12px 25px #142b3b0d; }

.stat-icon {
    width:42px;
    height:42px;
    display:grid;
    place-items:center;
    background:#f6f0e5;
    border-radius:11px;
    font-size:20px;
    margin-bottom:14px;
}

.stat small { color:var(--muted); font-size:12px; }
.stat h3 { margin:7px 0 0; font-size:29px; color:var(--navy); }

.panel {
    background:white;
    border:1px solid var(--border);
    padding:25px;
    margin-bottom:24px;
    border-radius:15px;
    box-shadow:0 5px 20px #142b3b05;
    animation:rise .45s ease both;
}

@keyframes rise {
    from { opacity:0; transform:translateY(10px); }
    to { opacity:1; transform:translateY(0); }
}

.panel-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:21px;
}

.panel-heading h2 { margin:5px 0 0; font-size:24px; color:var(--navy); }

.grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:18px;
}

.field label { display:block; font-size:12px; font-weight:700; margin-bottom:8px; }

.field input,.field select {
    width:100%;
    padding:12px 13px;
    background:#fdfdfc;
    border:1px solid #e0e3e4;
    border-radius:8px;
    color:var(--text);
    font:inherit;
    font-size:13px;
    outline:none;
    transition:.2s;
}

.field input:focus,.field select:focus {
    border-color:var(--gold);
    box-shadow:0 0 0 3px #c8a56b20;
}

.date-note {
    background:#faf8f2;
    border:1px solid #eee6d6;
    border-radius:9px;
    color:#766344;
    padding:12px 14px;
    margin-top:18px;
    font-size:12px;
    line-height:1.7;
}

.actions { display:flex; gap:9px; align-items:center; flex-wrap:wrap; margin-top:22px; }

.btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    padding:11px 15px;
    border:0;
    border-radius:8px;
    background:var(--navy);
    color:white;
    font:inherit;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    transition:.2s;
}

.btn:hover { background:var(--navy-light); transform:translateY(-1px); }
.btn-gold { background:var(--gold); color:var(--navy); }
.btn-gold:hover { background:#d7b982; }
.btn-muted { background:#edf0f2; color:var(--text); }
.btn-muted:hover { background:#e1e5e8; }
.btn-small { padding:8px 10px; font-size:11px; white-space:nowrap; }

.notice { padding:14px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; }
.success { background:#e9f6ee; color:#206c43; border:1px solid #d1eadb; }
.error { background:#fff0ef; color:#a52d2d; border:1px solid #f2d4d1; }

.table-wrap { overflow-x:auto; }
table { width:100%; min-width:1050px; border-collapse:collapse; }

th {
    text-align:left;
    padding:14px 12px;
    background:#f8f7f3;
    color:#697781;
    font-size:10px;
    letter-spacing:1px;
    text-transform:uppercase;
    white-space:nowrap;
}

td {
    padding:15px 12px;
    border-bottom:1px solid #efeee9;
    font-size:12px;
    vertical-align:middle;
}

tbody tr { transition:background .2s; }
tbody tr:hover { background:#fcfbf8; }
tbody tr:last-child td { border-bottom:0; }

.badge {
    display:inline-block;
    padding:6px 9px;
    border-radius:30px;
    font-size:10px;
    font-weight:700;
    white-space:nowrap;
}

.badge.green { background:#e7f5ec; color:var(--green); }
.badge.red { background:#fdebea; color:var(--red); }
.badge.gold { background:#fbf1dd; color:#96702f; }
.badge.blue { background:#e9f1fa; color:var(--blue); }
.badge.neutral { background:#eff0f1; color:#606a72; }

.room-name { font-weight:700; color:var(--navy); }
.price { font-weight:700; white-space:nowrap; }

.status-form { display:flex; gap:6px; min-width:190px; align-items:center; }
.status-form select {
    min-width:120px;
    padding:8px;
    border:1px solid var(--border);
    border-radius:7px;
    background:#fff;
    font:inherit;
    font-size:11px;
}

.empty { padding:40px 15px; text-align:center; color:var(--muted); }
.empty-icon { font-size:35px; margin-bottom:10px; }
.footer { text-align:center; color:var(--muted); font-size:11px; padding:10px; }

@media(max-width:1200px) {
    .sidebar { width:205px; }
    .main { margin-left:205px; padding:25px 22px; }
    .stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
}

@media(max-width:700px) {
    .sidebar { position:relative; width:100%; padding:15px; }
    .brand { padding-bottom:13px; }
    .brand h2 { font-size:22px; }
    .nav-label { margin-top:15px; }
    .sidebar nav { display:flex; flex-wrap:wrap; gap:5px; }
    .nav-link { padding:10px; font-size:11px; }
    .sidebar-bottom { margin-top:12px; padding-top:10px; }
    .main { margin-left:0; padding:22px 14px; }
    .topbar { align-items:flex-start; }
    .hero { padding:22px; }
    .hero h2 { font-size:23px; }
    .grid { grid-template-columns:1fr; }
    .panel { padding:17px; }
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
        <a class="nav-link" href="rooms.php"><span>▣</span> Room Management</a>
        <a class="nav-link" href="customers.php"><span>♙</span> Customers</a>
        <a class="nav-link active" href="bookings.php"><span>▤</span> Bookings</a>
        <a class="nav-link" href="payments.php"><span>₹</span> Payments</a>
    </nav>

    <div class="sidebar-bottom">
        <a class="nav-link" href="admin-logout.php"><span>↪</span> Logout</a>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <div class="eyebrow">GrandVista / Reservations</div>
            <h1>Booking Management</h1>
            <p class="subtitle">Manage guest reservations, stay dates and booking status.</p>
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
        <div class="eyebrow" style="color:#dfc18d">Reservations & Guest Experience</div>
        <h2>Every Stay Starts with a Booking.</h2>
        <p>Create reservations, calculate room charges automatically and keep track of every guest's journey.</p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice success">✓ &nbsp;<?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error">! &nbsp;<?= h($error) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat">
            <div class="stat-icon">▤</div>
            <small>Total Bookings</small>
            <h3><?= $totalBookings ?></h3>
        </div>
        <div class="stat">
            <div class="stat-icon">◷</div>
            <small>Pending</small>
            <h3><?= $pendingCount ?></h3>
        </div>
        <div class="stat">
            <div class="stat-icon">✓</div>
            <small>Confirmed</small>
            <h3><?= $confirmedCount ?></h3>
        </div>
        <div class="stat">
            <div class="stat-icon">♙</div>
            <small>Checked In</small>
            <h3><?= $checkedInCount ?></h3>
        </div>
    </section>

    <section class="panel" id="booking-form">
        <div class="panel-heading">
            <div>
                <div class="eyebrow"><?= $editBooking ? 'Update Reservation' : 'Reservation Desk' ?></div>
                <h2><?= $editBooking ? 'Edit Booking Details' : 'Create a New Booking' ?></h2>
            </div>
            <span class="badge gold"><?= $editBooking ? 'EDIT MODE' : 'NEW BOOKING' ?></span>
        </div>

        <?php if (count($customerRows) === 0): ?>
            <div class="notice error">
                Please add a customer in Customer Management before creating a booking.
            </div>
        <?php elseif (count($roomRows) === 0): ?>
            <div class="notice error">
                Please add a room in Room Management before creating a booking.
            </div>
        <?php else: ?>
        <form method="POST" action="bookings.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h($editBooking['id'] ?? 0) ?>">

            <div class="grid">
                <div class="field">
                    <label for="customer_id">Customer *</label>
                    <select name="customer_id" id="customer_id" required>
                        <option value="">Select customer</option>
                        <?php foreach ($customerRows as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= (int)($editBooking['customer_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= h($c['full_name']) ?> — <?= h($c['phone']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="room_id">Room *</label>
                    <select name="room_id" id="room_id" required>
                        <option value="">Select room</option>
                        <?php foreach ($roomRows as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"
                            <?= (int)($editBooking['room_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
                            Room <?= h($r['room_number']) ?> — ₹<?= number_format((float)$r['price_per_night'], 2) ?>/night (<?= h($r['status']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="check_in">Check-in Date *</label>
                    <input type="date" id="check_in" name="check_in" required
                           value="<?= h($editBooking['check_in'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="check_out">Check-out Date *</label>
                    <input type="date" id="check_out" name="check_out" required
                           value="<?= h($editBooking['check_out'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="adults">Adults *</label>
                    <input type="number" id="adults" name="adults" min="1" max="20" required
                           value="<?= h($editBooking['adults'] ?? 1) ?>">
                </div>

                <div class="field">
                    <label for="children">Children</label>
                    <input type="number" id="children" name="children" min="0" max="20"
                           value="<?= h($editBooking['children'] ?? 0) ?>">
                </div>

                <div class="field">
                    <label for="status">Booking Status</label>
                    <select id="status" name="status" required>
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?= h($s) ?>"
                            <?= ($editBooking['status'] ?? 'Pending') === $s ? 'selected' : '' ?>>
                            <?= h($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="date-note">
                ✦ Room charges are calculated automatically from the selected room's nightly rate and the number of nights.
                A room cannot have overlapping active bookings.
            </div>

            <div class="actions">
                <button class="btn btn-gold" type="submit">
                    <?= $editBooking ? '✓ Update Booking' : '+ Create Booking' ?>
                </button>
                <?php if ($editBooking): ?>
                    <a class="btn btn-muted" href="bookings.php">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <div class="eyebrow">Reservation Records</div>
                <h2>All Bookings</h2>
                <p class="subtitle">Review reservations and update their current status.</p>
            </div>
            <span class="badge blue"><?= $totalBookings ?> Bookings</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Room</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Nights</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($bookingRows) === 0): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty">
                                <div class="empty-icon">▤</div>
                                <strong>No bookings created yet</strong>
                                <p>Create your first reservation using the form above.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookingRows as $b): ?>
                    <?php
                        $nightCount = (int)(
                            (new DateTime($b['check_in']))
                            ->diff(new DateTime($b['check_out']))->days
                        );
                    ?>
                    <tr>
                        <td>#<?= (int)$b['id'] ?></td>
                        <td><?= h($b['full_name']) ?></td>
                        <td><span class="room-name">Room <?= h($b['room_number']) ?></span></td>
                        <td><?= h($b['check_in']) ?></td>
                        <td><?= h($b['check_out']) ?></td>
                        <td><?= $nightCount ?></td>
                        <td><strong>₹<?= number_format((float)$b['total_amount'], 2) ?></strong></td>
                        <td>
                            <span class="badge <?= h(bookingBadge($b['status'])) ?>">
                                <?= h($b['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions" style="margin-top:0">
                                <a class="btn btn-small"
                                   href="bookings.php?edit=<?= (int)$b['id'] ?>#booking-form">
                                    Edit
                                </a>
                                <form method="POST" action="bookings.php" class="status-form">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                    <select name="status" aria-label="Booking status">
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= h($s) ?>"
                                            <?= $b['status'] === $s ? 'selected' : '' ?>>
                                            <?= h($s) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-small btn-gold" type="submit">Save</button>
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

    <div class="footer">
        © <?= date('Y') ?> GrandVista Hotel Management · Crafted for exceptional stays
    </div>
</main>
</body>
</html>
