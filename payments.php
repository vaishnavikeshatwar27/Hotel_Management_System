
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

$methods = ['Cash', 'Card', 'UPI', 'Online'];
$statuses = ['Pending', 'Paid', 'Failed', 'Refunded'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            $amount = filter_var(
                $_POST['amount'] ?? '',
                FILTER_VALIDATE_FLOAT
            );
            $method = $_POST['payment_method'] ?? '';
            $status = $_POST['payment_status'] ?? 'Pending';
            $reference = trim($_POST['transaction_reference'] ?? '');

            if ($bookingId < 1 || $amount === false || $amount <= 0) {
                throw new Exception(
                    'Please select a booking and enter a valid payment amount.'
                );
            }

            if (
                !in_array($method, $methods, true) ||
                !in_array($status, $statuses, true)
            ) {
                throw new Exception(
                    'Please select a valid payment method and status.'
                );
            }

            $stmt = $conn->prepare(
                "SELECT id FROM bookings WHERE id = ?"
            );
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$booking) {
                throw new Exception('The selected booking was not found.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "SELECT id FROM payments WHERE id = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $existingPayment = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existingPayment) {
                    throw new Exception('Payment record not found.');
                }
            }

            $paidAt = $status === 'Paid' ? date('Y-m-d H:i:s') : null;
            $referenceValue = $reference !== '' ? $reference : null;

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE payments
                     SET booking_id = ?, amount = ?, payment_method = ?,
                         payment_status = ?, transaction_reference = ?,
                         paid_at = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "idssssi",
                    $bookingId,
                    $amount,
                    $method,
                    $status,
                    $referenceValue,
                    $paidAt,
                    $id
                );

                $stmt->execute();
                $stmt->close();
                $message = 'Payment updated successfully!';
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO payments
                    (booking_id, amount, payment_method, payment_status,
                     transaction_reference, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "idssss",
                    $bookingId,
                    $amount,
                    $method,
                    $status,
                    $referenceValue,
                    $paidAt
                );

                $stmt->execute();
                $stmt->close();
                $message = 'Payment record added successfully!';
            }
        }
    } catch (Throwable $e) {
        if ($e instanceof mysqli_sql_exception) {
            $error = 'Database error. Please check your payments table fields.';
        } else {
            $error = $e->getMessage();
        }
    }
}

$editPayment = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editPayment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editPayment) {
        $error = 'The requested payment record was not found.';
    }
}

$bookings = $conn->query(
    "SELECT b.id, b.total_amount, b.check_in, b.check_out,
            c.full_name, r.room_number
     FROM bookings b
     JOIN customers c ON b.customer_id = c.id
     JOIN rooms r ON b.room_id = r.id
     ORDER BY b.id DESC"
);

$payments = $conn->query(
    "SELECT p.*, c.full_name, r.room_number
     FROM payments p
     JOIN bookings b ON p.booking_id = b.id
     JOIN customers c ON b.customer_id = c.id
     JOIN rooms r ON b.room_id = r.id
     ORDER BY p.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Management | GrandVista</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #172e40;
    --navy-light: #23475b;
    --gold: #c6a36a;
    --gold-light: #f2e7d3;
    --cream: #f7f6f2;
    --white: #ffffff;
    --text: #263746;
    --muted: #78848d;
    --border: #e8e7e1;
    --green: #26764c;
    --red: #b84444;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--cream);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
}

a {
    color: inherit;
}

.topbar {
    min-height: 78px;
    padding: 16px 5%;
    background: var(--navy);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    box-shadow: 0 5px 22px #172e401c;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-mark {
    width: 43px;
    height: 43px;
    border: 1px solid var(--gold);
    color: var(--gold);
    display: grid;
    place-items: center;
    font-family: 'Playfair Display', serif;
    font-size: 23px;
    border-radius: 5px;
}

.brand h2 {
    margin: 0;
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 600;
}

.brand p {
    margin: 3px 0 0;
    color: #c9d2d8;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.nav-links {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.nav-links a {
    text-decoration: none;
    padding: 10px 14px;
    border-radius: 7px;
    font-size: 13px;
    transition: .25s ease;
}

.nav-links a:hover {
    background: #ffffff18;
    color: #f1d5a6;
}

.nav-links .logout {
    border: 1px solid #ffffff50;
}

main {
    width: min(1400px, 94%);
    margin: 35px auto 55px;
    animation: fadeUp .55s ease both;
}

.page-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 27px;
}

.eyebrow {
    color: #9a7947;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2.5px;
}

h1, h2 {
    font-family: 'Playfair Display', serif;
}

.page-heading h1 {
    margin: 8px 0;
    color: var(--navy);
    font-size: clamp(29px, 4vw, 39px);
}

.page-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.7;
}

.heading-icon {
    width: 66px;
    height: 66px;
    border-radius: 17px;
    display: grid;
    place-items: center;
    background: var(--gold-light);
    font-size: 29px;
}

.notice {
    padding: 15px 18px;
    margin-bottom: 20px;
    border-radius: 10px;
    font-size: 14px;
    animation: fadeUp .3s ease;
}

.success {
    color: var(--green);
    background: #e9f6ed;
    border: 1px solid #cbe8d4;
}

.error {
    color: var(--red);
    background: #fff0ee;
    border: 1px solid #f2d1cc;
}

.panel {
    background: var(--white);
    padding: clamp(18px, 3vw, 29px);
    margin-bottom: 25px;
    border: 1px solid #eeede7;
    border-radius: 15px;
    box-shadow: 0 8px 30px #24374609;
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.panel-heading h2 {
    margin: 0;
    color: var(--navy);
    font-size: 24px;
}

.panel-heading p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
}

.section-number {
    color: #9a7947;
    background: #f7f0e4;
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 12px;
    font-weight: 700;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 21px;
}

.field {
    min-width: 0;
}

label {
    display: block;
    color: #354959;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 9px;
}

input, select {
    width: 100%;
    min-height: 46px;
    padding: 12px 13px;
    border: 1px solid #dfe3e4;
    border-radius: 8px;
    color: var(--text);
    background: #fff;
    font: inherit;
    font-size: 13px;
    transition: border .2s, box-shadow .2s;
}

input:focus, select:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px #c6a36a25;
}

input::placeholder {
    color: #a5adb2;
}

.help-text {
    margin: 20px 0 0;
    padding: 13px 15px;
    border-left: 3px solid var(--gold);
    border-radius: 0 7px 7px 0;
    background: #faf7f0;
    color: #756b5a;
    font-size: 12px;
    line-height: 1.7;
}

.actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 22px;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    min-height: 41px;
    padding: 10px 16px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: var(--navy);
    color: white;
    text-decoration: none;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: transform .2s, background .2s, box-shadow .2s;
}

.btn:hover {
    background: var(--navy-light);
    transform: translateY(-2px);
    box-shadow: 0 5px 14px #172e4020;
}

.btn-gold {
    background: var(--gold);
    color: #172e40;
}

.btn-gold:hover {
    background: #d8b77f;
}

.btn-cancel {
    color: #566570;
    background: #f1f2f1;
    border-color: #e1e4e2;
}

.btn-cancel:hover {
    background: #e5e8e6;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 10px;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    font-size: 12px;
}

thead th {
    background: #f8f7f2;
    color: #6d7880;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    white-space: nowrap;
}

th, td {
    padding: 15px 13px;
    text-align: left;
    border-bottom: 1px solid #eeeee9;
    vertical-align: middle;
}

tbody tr {
    transition: background .2s;
}

tbody tr:hover {
    background: #fcfaf5;
}

tbody tr:last-child td {
    border-bottom: 0;
}

.id-chip {
    display: inline-block;
    background: #f2eee5;
    color: #80653d;
    padding: 5px 8px;
    border-radius: 6px;
    font-weight: 700;
}

.amount {
    color: var(--navy);
    font-weight: 700;
    white-space: nowrap;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.status-paid {
    background: #e6f5eb;
    color: #26764c;
}

.status-pending {
    background: #fff2d9;
    color: #91651c;
}

.status-failed {
    background: #fde9e7;
    color: #b84444;
}

.status-refunded {
    background: #e9edf9;
    color: #4c609d;
}

.empty-state {
    padding: 35px 15px;
    text-align: center;
    color: var(--muted);
    line-height: 1.8;
}

.footer {
    text-align: center;
    color: #899198;
    font-size: 11px;
    padding: 10px 0 20px;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 700px) {
    .topbar {
        padding: 16px 4%;
    }

    .brand h2 {
        font-size: 19px;
    }

    .nav-links {
        width: 100%;
    }

    .nav-links a {
        padding: 9px 10px;
    }

    main {
        width: 92%;
        margin-top: 25px;
    }

    .grid {
        grid-template-columns: 1fr;
        gap: 17px;
    }

    .heading-icon {
        display: none;
    }

    .panel-heading h2 {
        font-size: 21px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-mark">G</div>
        <div>
            <h2>GrandVista</h2>
            <p>Hotel Management</p>
        </div>
    </div>

    <nav class="nav-links">
        <a href="admin-dashboard.php">← Dashboard</a>
        <a href="rooms.php">Rooms</a>
        <a href="customers.php">Customers</a>
        <a href="bookings.php">Bookings</a>
        <a href="payments.php">Payments</a>
        <a class="logout" href="admin-logout.php">Logout ↗</a>
    </nav>
</header>

<main>
    <div class="page-heading">
        <div>
            <div class="eyebrow">Finance &amp; Transactions</div>
            <h1>Payment Management</h1>
            <p>Manage payment records, transaction references and payment status.</p>
        </div>
        <div class="heading-icon">₹</div>
    </div>

    <?php if ($message): ?>
        <div class="notice success">✓ &nbsp;<?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error">⚠ &nbsp;<?= h($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2><?= $editPayment ? 'Edit Payment Record' : 'Add Payment Record' ?></h2>
                <p>Enter the details below to record a booking payment.</p>
            </div>
            <span class="section-number"><?= $editPayment ? 'EDIT MODE' : 'NEW RECORD' ?></span>
        </div>

        <?php if ($bookings->num_rows === 0): ?>
            <div class="empty-state">
                <div style="font-size:32px">🛎️</div>
                <strong>No bookings available yet</strong>
                <p>Create a booking before adding its payment record.</p>
                <a class="btn" href="bookings.php">Go to Bookings</a>
            </div>
        <?php else: ?>
        <form method="POST" action="payments.php<?= $editPayment ? '?edit=' . (int)$editPayment['id'] : '' ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h($editPayment['id'] ?? 0) ?>">

            <div class="grid">
                <div class="field">
                    <label for="booking_id">Booking *</label>
                    <select name="booking_id" id="booking_id" required>
                        <option value="">Select a booking</option>
                        <?php while ($b = $bookings->fetch_assoc()): ?>
                            <option value="<?= (int)$b['id'] ?>"
                                <?= (int)($editPayment['booking_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                                #<?= (int)$b['id'] ?> —
                                <?= h($b['full_name']) ?>,
                                Room <?= h($b['room_number']) ?>,
                                ₹<?= number_format((float)$b['total_amount'], 2) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="amount">Payment Amount (₹) *</label>
                    <input type="number" name="amount" id="amount"
                           min="0.01" step="0.01" required
                           placeholder="Enter payment amount"
                           value="<?= h($editPayment['amount'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="payment_method">Payment Method *</label>
                    <select name="payment_method" id="payment_method" required>
                        <?php foreach ($methods as $method): ?>
                            <option value="<?= h($method) ?>"
                                <?= ($editPayment['payment_method'] ?? 'Cash') === $method ? 'selected' : '' ?>>
                                <?= h($method) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="payment_status">Payment Status *</label>
                    <select name="payment_status" id="payment_status" required>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= h($status) ?>"
                                <?= ($editPayment['payment_status'] ?? 'Pending') === $status ? 'selected' : '' ?>>
                                <?= h($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="transaction_reference">Transaction Reference</label>
                    <input type="text" name="transaction_reference"
                           id="transaction_reference" maxlength="255"
                           value="<?= h($editPayment['transaction_reference'] ?? '') ?>"
                           placeholder="Optional reference number">
                </div>
            </div>

            <div class="help-text">
                <strong>Important:</strong> Select “Paid” only after confirming
                that the payment was received. This page stores payment records;
                it does not charge cards or process real UPI transactions.
            </div>

            <div class="actions">
                <button class="btn btn-gold" type="submit">
                    <?= $editPayment ? '✓ Update Payment' : '+ Save Payment' ?>
                </button>

                <?php if ($editPayment): ?>
                    <a class="btn btn-cancel" href="payments.php">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>All Payment Records</h2>
                <p>Review and edit the payments saved in your database.</p>
            </div>
            <span class="section-number"><?= (int)$payments->num_rows ?> RECORDS</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Room</th>
                        <th>Booking</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Paid At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($payments->num_rows === 0): ?>
                    <tr>
                        <td colspan="10">
                            <div class="empty-state">
                                <div style="font-size:30px">💳</div>
                                <strong>No payment records yet</strong>
                                <div>Your saved payments will appear here.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($p = $payments->fetch_assoc()): ?>
                    <?php
                        $statusClass = match ($p['payment_status']) {
                            'Paid' => 'status-paid',
                            'Pending' => 'status-pending',
                            'Failed' => 'status-failed',
                            'Refunded' => 'status-refunded',
                            default => 'status-pending'
                        };
                    ?>
                    <tr>
                        <td><span class="id-chip">#<?= (int)$p['id'] ?></span></td>
                        <td><?= h($p['full_name']) ?></td>
                        <td><?= h($p['room_number']) ?></td>
                        <td>#<?= (int)$p['booking_id'] ?></td>
                        <td class="amount">₹<?= number_format((float)$p['amount'], 2) ?></td>
                        <td><?= h($p['payment_method']) ?></td>
                        <td>
                            <span class="status <?= $statusClass ?>">
                                <?= h($p['payment_status']) ?>
                            </span>
                        </td>
                        <td><?= h($p['transaction_reference'] ?? '—') ?></td>
                        <td><?= h($p['paid_at'] ?? '—') ?></td>
                        <td>
                            <a class="btn" href="payments.php?edit=<?= (int)$p['id'] ?>">
                                Edit
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer">
        GrandVista Hotel Management System · Payment Administration
    </div>
</main>

</body>
</html>
