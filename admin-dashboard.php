
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$adminName = $_SESSION['admin_name'] ?? 'Administrator';

function getCount(mysqli $conn, string $sql): int {
    try {
        $result = $conn->query($sql);
        $row = $result->fetch_row();
        return (int) ($row[0] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$roomCount = getCount($conn, "SELECT COUNT(*) FROM rooms");
$customerCount = getCount($conn, "SELECT COUNT(*) FROM customers");
$bookingCount = getCount($conn, "SELECT COUNT(*) FROM bookings");
$availableRooms = getCount(
    $conn,
    "SELECT COUNT(*) FROM rooms WHERE status = 'Available'"
);

$occupancy = $roomCount > 0
    ? round((($roomCount - $availableRooms) / $roomCount) * 100)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>GrandVista | Luxury Admin Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #142b3b;
    --navy-light: #1d3b4e;
    --gold: #c8a56b;
    --gold-pale: #f6efe3;
    --cream: #f7f6f2;
    --white: #ffffff;
    --text: #243746;
    --muted: #87919a;
    --border: #ece9e2;
    --green: #298568;
    --shadow: 0 12px 35px rgba(26, 42, 52, .06);
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
    text-decoration: none;
}

button, a {
    -webkit-tap-highlight-color: transparent;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 255px;
    flex-shrink: 0;
    background: var(--navy);
    color: white;
    padding: 28px 18px;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

.sidebar::before {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    border: 1px solid rgba(200,165,107,.13);
    border-radius: 50%;
    right: -130px;
    top: 100px;
    pointer-events: none;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 10px 30px;
    border-bottom: 1px solid rgba(255,255,255,.12);
}

.brand-mark {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border: 1px solid var(--gold);
    border-radius: 12px;
    color: var(--gold);
    font-family: 'Playfair Display', serif;
    font-size: 27px;
}

.brand h2 {
    margin: 0;
    color: white;
    font-family: 'Playfair Display', serif;
    font-size: 23px;
    font-weight: 600;
}

.brand small {
    display: block;
    color: #b7c2ca;
    font-size: 10px;
    letter-spacing: 2.5px;
    margin-top: 3px;
}

.menu-label {
    color: #93a5b1;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.8px;
    padding: 28px 12px 12px;
}

.nav-links {
    display: grid;
    gap: 7px;
}

.nav-links a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    color: #d3dce1;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: .25s ease;
    position: relative;
}

.nav-links a .nav-icon {
    font-size: 18px;
    width: 22px;
    text-align: center;
}

.nav-links a:hover,
.nav-links a.active {
    background: rgba(200,165,107,.15);
    color: #f1d6a7;
    transform: translateX(3px);
}

.nav-links a.active::before {
    content: "";
    position: absolute;
    left: -18px;
    height: 25px;
    width: 3px;
    background: var(--gold);
    border-radius: 4px;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 30px;
}

.help-card {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 14px;
    padding: 16px;
}

.help-card .help-icon {
    font-size: 23px;
}

.help-card h4 {
    color: white;
    font-size: 13px;
    margin: 9px 0 5px;
}

.help-card p {
    color: #aebdc7;
    font-size: 11px;
    line-height: 1.7;
    margin: 0;
}

.sidebar-footer {
    color: #91a2ae;
    text-align: center;
    font-size: 10px;
    margin-top: 22px;
}

/* Main */
.main {
    flex: 1;
    min-width: 0;
}

.topbar {
    height: 82px;
    padding: 0 36px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    background: rgba(255,255,255,.92);
    border-bottom: 1px solid var(--border);
}

.breadcrumb {
    color: var(--muted);
    font-size: 12px;
}

.breadcrumb strong {
    color: var(--navy);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.live-status {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    color: var(--green);
    font-weight: 700;
    background: #edf7f2;
    border-radius: 30px;
    padding: 8px 12px;
}

.status-dot {
    width: 7px;
    height: 7px;
    background: #39a77e;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avatar {
    display: grid;
    place-items: center;
    width: 39px;
    height: 39px;
    background: var(--gold-pale);
    border: 1px solid #eadbc2;
    border-radius: 50%;
    color: var(--navy);
    font-weight: 800;
}

.profile-name {
    font-size: 12px;
    font-weight: 700;
    color: var(--navy);
}

.profile-role {
    font-size: 10px;
    color: var(--muted);
    margin-top: 2px;
}

.content {
    max-width: 1500px;
    margin: 0 auto;
    padding: 32px 36px 45px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 20px;
    margin-bottom: 25px;
    animation: fadeUp .55s ease both;
}

.eyebrow {
    color: #ad8951;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 2.5px;
    font-weight: 800;
}

.page-heading h1 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(27px, 3vw, 36px);
    font-weight: 600;
    margin: 7px 0 5px;
    color: var(--navy);
}

.page-heading p {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
}

.date-label {
    color: #63717c;
    font-size: 11px;
    background: white;
    border: 1px solid var(--border);
    padding: 11px 14px;
    border-radius: 10px;
    white-space: nowrap;
}

/* Hero banner */
.hero {
    min-height: 235px;
    padding: 32px 36px;
    border-radius: 19px;
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    background:
        linear-gradient(90deg,
            rgba(12,29,41,.94) 0%,
            rgba(18,42,56,.78) 47%,
            rgba(18,42,56,.16) 100%),
        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=85')
        center 52% / cover no-repeat;
    box-shadow: 0 16px 38px rgba(20,43,59,.14);
    animation: fadeUp .65s ease both;
}

.hero-content {
    position: relative;
    z-index: 1;
    max-width: 470px;
}

.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #f1d7a8;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 700;
}

.hero h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(25px, 3vw, 36px);
    color: white;
    line-height: 1.25;
    font-weight: 500;
    margin: 13px 0 10px;
}

.hero p {
    color: #e0e6e9;
    font-size: 12px;
    line-height: 1.8;
    margin: 0;
    max-width: 400px;
}

.hero-decoration {
    position: absolute;
    right: 38px;
    bottom: -47px;
    font-family: 'Playfair Display', serif;
    font-size: 230px;
    line-height: 1;
    color: rgba(255,255,255,.11);
    pointer-events: none;
}

/* Statistics */
.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 31px 0 16px;
}

.section-heading h3 {
    margin: 0;
    font-size: 16px;
    color: var(--navy);
}

.section-heading p {
    color: var(--muted);
    font-size: 11px;
    margin: 4px 0 0;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 17px;
}

.stat-card {
    background: white;
    padding: 21px;
    border: 1px solid var(--border);
    border-radius: 15px;
    box-shadow: var(--shadow);
    min-width: 0;
    animation: fadeUp .6s ease both;
    transition: transform .25s ease, box-shadow .25s ease;
}

.stat-card:nth-child(2) { animation-delay: .08s; }
.stat-card:nth-child(3) { animation-delay: .16s; }
.stat-card:nth-child(4) { animation-delay: .24s; }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 35px rgba(26,42,52,.1);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.stat-label {
    color: #75818b;
    font-size: 12px;
    font-weight: 600;
}

.stat-icon {
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    font-size: 20px;
}

.icon-blue { background: #eaf1f7; }
.icon-gold { background: #f8f0e3; }
.icon-green { background: #e7f4ed; }
.icon-purple { background: #f0ebf8; }

.stat-number {
    color: var(--navy);
    font-size: 34px;
    font-weight: 800;
    letter-spacing: -1.5px;
    margin: 17px 0 4px;
    line-height: 1.2;
}

.stat-foot {
    font-size: 10px;
    color: #9aa2a9;
}

.stat-foot strong {
    color: var(--green);
    font-weight: 700;
}

/* Occupancy */
.occupancy {
    background: white;
    padding: 21px 23px;
    border: 1px solid var(--border);
    border-radius: 15px;
    margin-top: 19px;
    box-shadow: var(--shadow);
    animation: fadeUp .7s ease both;
}

.occupancy-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 14px;
}

.occupancy-top h3 {
    font-size: 13px;
    margin: 0;
}

.occupancy-top span {
    color: var(--gold);
    font-size: 20px;
    font-weight: 800;
}

.progress-track {
    height: 8px;
    border-radius: 20px;
    background: #f0eee9;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    width: <?= $occupancy ?>%;
    background: linear-gradient(90deg, #b28b50, #dfc18b);
    border-radius: 20px;
    animation: growBar 1.1s ease both;
}

.occupancy-note {
    font-size: 10px;
    color: var(--muted);
    margin-top: 10px;
}

/* Management links */
.management-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.management-card {
    display: flex;
    align-items: center;
    gap: 13px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px 15px;
    box-shadow: var(--shadow);
    transition: .25s ease;
    animation: fadeUp .7s ease both;
}

.management-card:hover {
    transform: translateY(-4px);
    border-color: #d8c19a;
    box-shadow: 0 14px 28px rgba(26,42,52,.09);
}

.management-icon {
    flex-shrink: 0;
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: var(--gold-pale);
    font-size: 20px;
}

.management-title {
    color: var(--navy);
    font-size: 12px;
    font-weight: 700;
}

.management-desc {
    color: var(--muted);
    font-size: 10px;
    margin-top: 4px;
}

.arrow {
    color: var(--gold);
    font-size: 19px;
    margin-left: auto;
}

.footer {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding-top: 27px;
    color: #98a0a5;
    font-size: 10px;
}

.footer strong {
    color: #b08d58;
    font-weight: 700;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(57,167,126,.35); }
    50% { box-shadow: 0 0 0 5px rgba(57,167,126,0); }
}

@keyframes growBar {
    from { width: 0; }
    to { width: <?= $occupancy ?>%; }
}

@media (max-width: 1150px) {
    .sidebar { width: 225px; }
    .content { padding: 27px 24px 35px; }
    .topbar { padding: 0 24px; }
    .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .management-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .layout { display: block; }

    .sidebar {
        width: 100%;
        padding: 16px;
    }

    .brand {
        padding: 0 2px 15px;
    }

    .menu-label,
    .sidebar-bottom {
        display: none;
    }

    .nav-links {
        display: flex;
        gap: 7px;
        overflow-x: auto;
        padding: 13px 0 3px;
    }

    .nav-links a {
        flex-shrink: 0;
        padding: 10px 12px;
        font-size: 11px;
    }

    .nav-links a.active::before {
        display: none;
    }

    .topbar {
        height: auto;
        min-height: 65px;
        padding: 13px 18px;
    }

    .live-status { display: none; }
    .profile { gap: 7px; }
    .profile-name { max-width: 115px; overflow-wrap: anywhere; }

    .content {
        padding: 23px 16px 30px;
    }

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .date-label { white-space: normal; }

    .hero {
        min-height: 225px;
        padding: 24px;
        background-position: center;
    }

    .hero-decoration {
        right: 5px;
        font-size: 155px;
    }

    .cards { gap: 11px; }
    .stat-card { padding: 15px; }
    .stat-number { font-size: 29px; }
    .stat-icon { width: 37px; height: 37px; font-size: 17px; }
    .stat-label { font-size: 11px; }

    .management-grid { gap: 10px; }
    .management-card { padding: 13px 10px; gap: 9px; }
    .management-icon { width: 35px; height: 35px; font-size: 17px; }
    .management-title { font-size: 11px; }
    .management-desc { font-size: 9px; }

    .footer { flex-direction: column; }
}

@media (max-width: 370px) {
    .cards, .management-grid {
        grid-template-columns: 1fr;
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
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">G</div>
            <div>
                <h2>GrandVista</h2>
                <small>HOTEL & RESORT</small>
            </div>
        </div>

        <div class="menu-label">MAIN MENU</div>

        <nav class="nav-links" aria-label="Main navigation">
            <a class="active" href="admin-dashboard.php">
                <span class="nav-icon">⌂</span> Dashboard
            </a>
            <a href="rooms.php">
                <span class="nav-icon">▤</span> Room Management
            </a>
            <a href="customers.php">
                <span class="nav-icon">♙</span> Customers
            </a>
            <a href="bookings.php">
                <span class="nav-icon">▣</span> Bookings
            </a>
            <a href="payments.php">
                <span class="nav-icon">＄</span> Payments
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card">
                <div class="help-icon">✦</div>
                <h4>Hospitality, elevated.</h4>
                <p>Manage your hotel operations from one elegant workspace.</p>
            </div>

            <a href="admin-logout.php"
               style="display:block;text-align:center;color:#e8c995;padding:15px 5px;font-size:12px;">
                ↗ &nbsp; Sign out of account
            </a>
        </div>

        <div class="sidebar-footer">GRANDVISTA MANAGEMENT SYSTEM</div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="breadcrumb">
                Workspace &nbsp; / &nbsp; <strong>Dashboard</strong>
            </div>

            <div class="topbar-right">
                <div class="live-status">
                    <span class="status-dot"></span>
                    Management Portal
                </div>

                <div class="profile">
                    <div class="avatar">
                        <?= htmlspecialchars(
                            strtoupper(substr($adminName, 0, 1)),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>
                    <div>
                        <div class="profile-name">
                            <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="profile-role">Hotel Administrator</div>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            <div class="page-heading">
                <div>
                    <div class="eyebrow">Your property at a glance</div>
                    <h1>Welcome back, <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>!</h1>
                    <p>Here is what's happening at GrandVista today.</p>
                </div>

                <div class="date-label">
                    ✦ &nbsp; GRANDVISTA ADMINISTRATION
                </div>
            </div>

            <section class="hero">
                <div class="hero-content">
                    <div class="hero-tag">✦ &nbsp; THE GRANDVISTA EXPERIENCE</div>
                    <h2>Exceptional stays.<br>Effortless management.</h2>
                    <p>
                        Your hotel, beautifully managed. Keep track of rooms,
                        guests, reservations and payments all in one place.
                    </p>
                </div>
                <div class="hero-decoration">G</div>
            </section>

            <div class="section-heading">
                <div>
                    <h3>Property Overview</h3>
                    <p>Your hotel's key numbers in one view.</p>
                </div>
            </div>

            <section class="cards">
                <article class="stat-card">
                    <div class="stat-top">
                        <div class="stat-label">Total Rooms</div>
                        <div class="stat-icon icon-blue">▤</div>
                    </div>
                    <div class="stat-number"><?= $roomCount ?></div>
                    <div class="stat-foot">Rooms registered in the system</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <div class="stat-label">Available Rooms</div>
                        <div class="stat-icon icon-green">⌂</div>
                    </div>
                    <div class="stat-number"><?= $availableRooms ?></div>
                    <div class="stat-foot"><strong>Ready</strong> &nbsp; Marked as available</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-icon icon-gold">♙</div>
                    </div>
                    <div class="stat-number"><?= $customerCount ?></div>
                    <div class="stat-foot">Registered guest records</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <div class="stat-label">Total Bookings</div>
                        <div class="stat-icon icon-purple">▣</div>
                    </div>
                    <div class="stat-number"><?= $bookingCount ?></div>
                    <div class="stat-foot">Bookings recorded in the system</div>
                </article>
            </section>

            <section class="occupancy">
                <div class="occupancy-top">
                    <h3>Room Occupancy Overview</h3>
                    <span><?= $occupancy ?>%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-bar"></div>
                </div>
                <div class="occupancy-note">
                    Based on rooms not currently marked Available.
                    This is a room-status estimate, not a live reservation occupancy calculation.
                </div>
            </section>

            <div class="section-heading">
                <div>
                    <h3>Management Centre</h3>
                    <p>Quick access to your daily hotel operations.</p>
                </div>
            </div>

            <section class="management-grid">
                <a class="management-card" href="rooms.php">
                    <div class="management-icon">🛏️</div>
                    <div>
                        <div class="management-title">Room Management</div>
                        <div class="management-desc">Rooms & availability</div>
                    </div>
                    <span class="arrow">›</span>
                </a>

                <a class="management-card" href="customers.php">
                    <div class="management-icon">👥</div>
                    <div>
                        <div class="management-title">Customers</div>
                        <div class="management-desc">Guest information</div>
                    </div>
                    <span class="arrow">›</span>
                </a>

                <a class="management-card" href="bookings.php">
                    <div class="management-icon">🗓️</div>
                    <div>
                        <div class="management-title">Bookings</div>
                        <div class="management-desc">Manage reservations</div>
                    </div>
                    <span class="arrow">›</span>
                </a>

                <a class="management-card" href="payments.php">
                    <div class="management-icon">💳</div>
                    <div>
                        <div class="management-title">Payments</div>
                        <div class="management-desc">Payment records</div>
                    </div>
                    <span class="arrow">›</span>
                </a>
            </section>

            <footer class="footer">
                <span>© <?= date('Y') ?> <strong>GrandVista Hotel</strong>. All rights reserved.</span>
                <span>Crafted for exceptional hospitality ✦</span>
            </footer>
        </main>
    </div>
</div>
</body>
</html>
