
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($name === '' || $phone === '') {
                throw new Exception('Customer name and phone are required.');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE customers
                     SET full_name=?, email=NULLIF(?, ''),
                         phone=?, address=NULLIF(?, '')
                     WHERE id=?"
                );
                $stmt->bind_param(
                    "ssssi", $name, $email, $phone, $address, $id
                );
                $stmt->execute();
                $stmt->close();

                header('Location: customers.php?notice=updated');
                exit;
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO customers (full_name, email, phone, address)
                     VALUES (?, NULLIF(?, ''), ?, NULLIF(?, ''))"
                );
                $stmt->bind_param("ssss", $name, $email, $phone, $address);
                $stmt->execute();
                $stmt->close();

                header('Location: customers.php?notice=added');
                exit;
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id < 1) {
                throw new Exception('Invalid customer selected.');
            }

            $stmt = $conn->prepare("DELETE FROM customers WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            header(
                'Location: customers.php?notice=' .
                ($deleted > 0 ? 'deleted' : 'notfound')
            );
            exit;
        }
    } catch (Throwable $e) {
        if ($e instanceof mysqli_sql_exception && $e->getCode() === 1451) {
            $error = 'This customer has booking records and cannot be deleted.';
        } elseif ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
            $error = 'This email or other unique field already exists.';
        } elseif ($e instanceof mysqli_sql_exception) {
            $error = 'Database error. Please check your customers table fields.';
        } else {
            $error = $e->getMessage();
        }
    }
}

$notices = [
    'added' => 'Customer added successfully!',
    'updated' => 'Customer updated successfully!',
    'deleted' => 'Customer deleted successfully!',
    'notfound' => 'Customer not found.'
];

$message = $notices[$_GET['notice'] ?? ''] ?? '';

$editCustomer = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editCustomer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$customers = $conn->query("SELECT * FROM customers ORDER BY id DESC");

$totalCustomers = $customers->num_rows;
$customerRows = [];

while ($customer = $customers->fetch_assoc()) {
    $customerRows[] = $customer;
}

$adminName = $_SESSION['admin_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Management | GrandVista</title>

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

.brand-mark { color: var(--gold); font-size: 29px; }

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

.nav-link.active { border-left: 3px solid var(--gold); }

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid #ffffff20;
    padding-top: 18px;
}

.main {
    margin-left: 245px;
    padding: 30px 36px 45px;
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
        linear-gradient(90deg, rgba(20,43,59,.96), rgba(20,43,59,.70)),
        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1400&q=80') center/cover;
    box-shadow: 0 10px 28px #142b3b15;
}

.hero h2 { font-size: 27px; margin: 8px 0; }

.hero p {
    max-width: 570px;
    color: #e2e7e9;
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 0;
}

.stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 25px;
}

.stat {
    background: white;
    border: 1px solid var(--border);
    padding: 20px;
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

.grid {
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

.field input, .field textarea {
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

.field textarea {
    min-height: 95px;
    resize: vertical;
}

.field input:focus, .field textarea:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px #c8a56b20;
}

.form-address { margin-top: 18px; }

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

.customer-avatar {
    width: 39px;
    height: 39px;
    border-radius: 50%;
    background: #f6f0e5;
    color: #96702f;
    display: inline-grid;
    place-items: center;
    font-size: 14px;
    font-weight: 700;
    margin-right: 10px;
    vertical-align: middle;
}

.customer-name { font-weight: 700; color: var(--navy); }
.customer-email { display: block; color: var(--muted); font-size: 11px; margin-top: 4px; }
.phone { white-space: nowrap; }
.address-cell { min-width: 150px; max-width: 250px; color: #66737b; line-height: 1.6; }

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
    .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .sidebar { position: relative; width: 100%; padding: 15px; }
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
    .grid { grid-template-columns: 1fr; }
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
        <a class="nav-link" href="rooms.php"><span>▣</span> Room Management</a>
        <a class="nav-link active" href="customers.php"><span>♙</span> Customers</a>
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
            <div class="eyebrow">GrandVista / Guest Relations</div>
            <h1>Customer Management</h1>
            <p class="subtitle">Welcome your guests and keep their details organised.</p>
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
        <div class="eyebrow" style="color:#dfc18d">Exceptional Hospitality Begins Here</div>
        <h2>Every Guest Matters.</h2>
        <p>Maintain accurate guest records and deliver a warm, seamless experience from arrival to departure.</p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice success">✓ &nbsp;<?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error">! &nbsp;<?= h($error) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat">
            <div class="stat-icon">♙</div>
            <small>Total Customers</small>
            <h3><?= $totalCustomers ?></h3>
        </div>
        <div class="stat">
            <div class="stat-icon">✦</div>
            <small>Guest Records</small>
            <h3><?= count($customerRows) ?></h3>
        </div>
    </section>

    <section class="panel" id="customer-form">
        <div class="panel-heading">
            <div>
                <div class="eyebrow"><?= $editCustomer ? 'Update Guest Record' : 'Guest Registration' ?></div>
                <h2><?= $editCustomer ? 'Edit Customer Details' : 'Add a New Customer' ?></h2>
            </div>
            <span class="badge gold"><?= $editCustomer ? 'EDIT MODE' : 'NEW GUEST' ?></span>
        </div>

        <form method="POST" action="customers.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h($editCustomer['id'] ?? 0) ?>">

            <div class="grid">
                <div class="field">
                    <label for="full_name">Full Name *</label>
                    <input id="full_name" name="full_name" required maxlength="150"
                           placeholder="Enter guest's full name"
                           value="<?= h($editCustomer['full_name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email" maxlength="150"
                           placeholder="guest@example.com"
                           value="<?= h($editCustomer['email'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="phone">Phone Number *</label>
                    <input id="phone" name="phone" type="tel" required maxlength="30"
                           placeholder="Enter contact number"
                           value="<?= h($editCustomer['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="field form-address">
                <label for="address">Residential Address</label>
                <textarea id="address" name="address" maxlength="1000"
                          placeholder="Enter guest's address (optional)"><?= h($editCustomer['address'] ?? '') ?></textarea>
            </div>

            <div class="actions">
                <button class="btn btn-gold" type="submit">
                    <?= $editCustomer ? '✓ Update Customer' : '+ Add Customer' ?>
                </button>
                <?php if ($editCustomer): ?>
                    <a class="btn btn-muted" href="customers.php">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <div class="eyebrow">Guest Directory</div>
                <h2>All Customers</h2>
                <p class="subtitle">Review and maintain your registered guest records.</p>
            </div>
            <span class="badge blue"><?= $totalCustomers ?> Guests</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Guest Details</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($customerRows) === 0): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty">
                                <div class="empty-icon">♙</div>
                                <strong>No customers registered yet</strong>
                                <p>Add your first guest using the form above.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customerRows as $customer): ?>
                    <tr>
                        <td>#<?= (int)$customer['id'] ?></td>
                        <td>
                            <span class="customer-avatar">
                                <?= h(strtoupper(substr(trim($customer['full_name']), 0, 1))) ?>
                            </span>
                            <span class="customer-name"><?= h($customer['full_name']) ?></span>
                            <?php if (!empty($customer['email'])): ?>
                                <span class="customer-email"><?= h($customer['email']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="phone"><?= h($customer['phone'] ?? '') ?></td>
                        <td class="address-cell"><?= h($customer['address'] ?: 'Not provided') ?></td>
                        <td>
                            <div class="action-group">
                                <a class="btn btn-small"
                                   href="customers.php?edit=<?= (int)$customer['id'] ?>#customer-form">
                                    Edit
                                </a>
                                <form method="POST" action="customers.php"
                                      onsubmit="return confirm('Delete this customer? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$customer['id'] ?>">
                                    <button class="btn btn-small btn-delete" type="submit">Delete</button>
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
