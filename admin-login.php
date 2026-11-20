
<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: admin-dashboard.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$error = '';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT id, full_name, email, password
                 FROM users
                 WHERE email = ? AND role = 'admin'
                 LIMIT 1"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];

                header('Location: admin-dashboard.php');
                exit;
            }

            $error = 'Invalid email or password.';
        } catch (Throwable $e) {
            $error = 'Unable to sign in. Please check your database connection.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | GrandVista</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #102b3b;
    --gold: #c9a366;
    --gold-light: #f0d6a2;
    --text: #233747;
    --muted: #78848e;
}

* {
    box-sizing: border-box;
}

html {
    min-height: 100%;
    background: #102b3b;
}

body {
    margin: 0;
    width: 100%;
    min-height: 100vh;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    background:
        linear-gradient(
            rgba(5, 20, 30, .30),
            rgba(5, 20, 30, .30)
        ),
        url('https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=2200&q=85')
        center / cover no-repeat fixed;
}

/* CENTERED MAIN CARD */
.login-layout {
    width: 100%;
    max-width: 1080px;
    min-width: 0;
    margin: auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    border: 1px solid rgba(240, 214, 162, .75);
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 25px 80px rgba(0, 0, 0, .38);
    animation: appear .55s ease both;
}

/* LEFT HOTEL PANEL */
.welcome-panel {
    position: relative;
    isolation: isolate;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 32px;
    color: #fff;
    background:
        linear-gradient(
            180deg,
            rgba(5, 24, 34, .72),
            rgba(5, 24, 34, .50) 48%,
            rgba(4, 21, 31, .91)
        ),
        url('https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1200&q=85')
        center / cover no-repeat;
}

.welcome-panel::before {
    content: "";
    position: absolute;
    z-index: -1;
    inset: 10px;
    border: 1px solid rgba(240, 214, 162, .55);
    border-radius: 12px;
    pointer-events: none;
}

.brand-mark {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
    display: grid;
    place-items: center;
    border: 1px solid var(--gold-light);
    border-radius: 8px;
    color: var(--gold-light);
    font-family: 'Playfair Display', serif;
    font-size: 29px;
}

.brand-name {
    font-family: 'Playfair Display', serif;
    font-size: 34px;
}

.brand-subtitle {
    margin-top: 6px;
    color: var(--gold-light);
    font-size: 9px;
    letter-spacing: 3px;
    text-transform: uppercase;
}

.brand-divider {
    width: 40px;
    height: 2px;
    margin-top: 20px;
    background: var(--gold-light);
}

.welcome-copy {
    margin: 28px 0;
}

.eyebrow {
    color: #a77b3f;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2.5px;
    text-transform: uppercase;
}

.welcome-copy .eyebrow {
    color: var(--gold-light);
}

.welcome-copy h1 {
    margin: 14px 0;
    font-family: 'Playfair Display', serif;
    font-size: clamp(29px, 3vw, 40px);
    line-height: 1.18;
    font-weight: 500;
}

.welcome-copy h1 em {
    color: var(--gold-light);
}

.welcome-copy p {
    max-width: 340px;
    color: #e1e9ed;
    font-size: 12px;
    line-height: 1.85;
}

.feature-list {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    padding-top: 16px;
    border-top: 1px solid rgba(240, 214, 162, .3);
}

.feature {
    padding: 0 3px;
    color: #f1e7d5;
    text-align: center;
    font-size: 10px;
}

.feature + .feature {
    border-left: 1px solid rgba(240, 214, 162, .25);
}

.feature-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    margin: 0 auto 7px;
    border: 1px solid var(--gold-light);
    border-radius: 50%;
    color: var(--gold-light);
    font-size: 16px;
}

.welcome-footer {
    margin-top: 18px;
    color: #d5dedf;
    font-size: 9px;
    font-style: italic;
    letter-spacing: 1.3px;
}

/* RIGHT LOGIN PANEL */
.login-panel {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 32px clamp(25px, 3.5vw, 45px);
    background:
        radial-gradient(ellipse at top right, #c9a36612, transparent 45%),
        linear-gradient(145deg, #fff, #fcfaf6);
}

.back-home {
    align-self: flex-start;
    display: inline-block;
    margin-bottom: 28px;
    padding: 10px 14px;
    border: 1px solid var(--gold);
    border-radius: 7px;
    color: #916d3b;
    background: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    transition: .2s ease;
}

.back-home:hover {
    color: white;
    background: var(--navy);
    border-color: var(--navy);
    transform: translateX(-2px);
}

.login-heading h2 {
    margin: 10px 0;
    color: var(--navy);
    font-family: 'Playfair Display', serif;
    font-size: clamp(30px, 3vw, 39px);
    font-weight: 600;
}

.login-heading p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.8;
}

.heading-line {
    width: 44px;
    height: 2px;
    margin: 17px 0 22px;
    background: var(--gold);
}

.error {
    margin-bottom: 16px;
    padding: 12px;
    border: 1px solid #f0cfca;
    border-radius: 7px;
    background: #fff2f0;
    color: #a52f2f;
    font-size: 12px;
    line-height: 1.6;
}

.field {
    margin-bottom: 18px;
}

.field label {
    display: block;
    margin-bottom: 8px;
    color: #304454;
    font-size: 12px;
    font-weight: 700;
}

.input-wrap {
    position: relative;
}

.input-icon {
    position: absolute;
    top: 50%;
    left: 14px;
    transform: translateY(-50%);
    color: #a48759;
    pointer-events: none;
}

.input-wrap input {
    width: 100%;
    height: 48px;
    padding: 10px 12px 10px 40px;
    border: 1px solid #d9dfe2;
    border-radius: 8px;
    background: #fff;
    color: var(--text);
    font: inherit;
    font-size: 12px;
    transition: .2s ease;
}

.input-wrap input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201, 163, 102, .15);
}

.login-button {
    width: 100%;
    min-height: 49px;
    margin-top: 3px;
    border: 1px solid var(--gold);
    border-radius: 8px;
    background: linear-gradient(110deg, #0b2635, #1b4050);
    color: #fff;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s ease;
}

.login-button:hover {
    background: linear-gradient(110deg, #c9a366, #e0bd82);
    color: #102b3b;
    transform: translateY(-2px);
}

.secure-note {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 10px;
    margin-top: 22px;
    color: #586c78;
    font-size: 10px;
    line-height: 1.8;
}

.secure-icon {
    color: #b38b50;
    font-size: 17px;
}

.secure-note strong {
    color: #354b5a;
}

.secure-note small {
    display: block;
    color: #8a969d;
    font-size: 10px;
}

/* ANIMATION */
@keyframes appear {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* FIT LAPTOP SCREEN HEIGHT */
@media (min-width: 801px) and (max-height: 850px) {
    body {
        padding: 12px 20px;
    }

    .welcome-panel {
        padding: 24px 30px;
    }

    .login-panel {
        padding: 24px 36px;
    }

    .welcome-copy {
        margin: 18px 0;
    }

    .welcome-copy h1 {
        font-size: 34px;
        margin: 10px 0;
    }

    .brand-mark {
        width: 42px;
        height: 42px;
        font-size: 25px;
    }

    .brand-name {
        font-size: 30px;
    }

    .brand-divider {
        margin-top: 14px;
    }

    .back-home {
        margin-bottom: 20px;
    }

    .heading-line {
        margin: 13px 0 18px;
    }

    .field {
        margin-bottom: 15px;
    }

    .input-wrap input {
        height: 45px;
    }

    .login-button {
        min-height: 46px;
    }

    .secure-note {
        margin-top: 17px;
    }
}

/* MOBILE AND TABLET */
@media (max-width: 800px) {
    body {
        padding: 16px 12px;
        background-attachment: scroll;
    }

    .login-layout {
        max-width: 470px;
        grid-template-columns: 1fr;
        border-radius: 15px;
    }

    .welcome-panel {
        padding: 26px;
    }

    .welcome-copy {
        margin: 22px 0;
    }

    .welcome-copy h1 {
        font-size: 29px;
    }

    .feature-list,
    .welcome-footer {
        display: none;
    }

    .login-panel {
        padding: 26px 24px;
    }

    .back-home {
        margin-bottom: 22px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>
</head>

<body>
<main class="login-layout">

    <section class="welcome-panel">
        <div class="brand">
            <div class="brand-mark">G</div>
            <div class="brand-name">GrandVista</div>
            <div class="brand-subtitle">Hotel &amp; Resort</div>
            <div class="brand-divider"></div>
        </div>

        <div class="welcome-copy">
            <div class="eyebrow">Welcome to GrandVista</div>
            <h1>Exceptional stays.<br>Effortless <em>management.</em></h1>
            <p>
                Your central space to manage rooms, guests,
                reservations and hotel payments with confidence.
            </p>
        </div>

        <div class="feature-list">
            <div class="feature">
                <span class="feature-icon">&#8962;</span>
                Rooms
            </div>
            <div class="feature">
                <span class="feature-icon">&#9822;</span>
                Guests
            </div>
            <div class="feature">
                <span class="feature-icon">&#9638;</span>
                Reservations
            </div>
            <div class="feature">
                <span class="feature-icon">&#9645;</span>
                Payments
            </div>
        </div>

        <div class="welcome-footer">
            LUXURY HOSPITALITY, SIMPLIFIED.
        </div>
    </section>

    <section class="login-panel">
        

        <div class="login-heading">
            <div class="eyebrow">Administrator Access</div>
            <h2>Welcome back</h2>
            <p>Sign in with your administrator credentials to continue.</p>
            <div class="heading-line"></div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="admin-login.php">
            <div class="field">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">&#9993;</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        autocomplete="username"
                        placeholder="admin@example.com"
                        value="<?= h($_POST['email'] ?? '') ?>"
                        required
                    >
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">&#128274;</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        required
                    >
                </div>
            </div>

            <button class="login-button" type="submit">
                Sign In to Dashboard &nbsp; &rarr;
            </button>
        </form>

        <div class="secure-note">
            <span class="secure-icon">&#9670;</span>
            <div>
                <strong>Restricted administrator access</strong>
                <small>GrandVista Hotel Management System</small>
            </div>
        </div>
    </section>

</main>
</body>
</html>
