
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";
$error = "";

// Change this to your own private key.
$setupKey = "GrandVista-12345";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $submittedKey = $_POST["setup_key"] ?? "";

    if (trim($setupKey) === "") {
        $error = "Configure your private setup key in the PHP file first.";
    } elseif (!hash_equals($setupKey, $submittedKey)) {
        $error = "Invalid setup key. Please try again.";
    } elseif ($fullName === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid name and email.";
    } elseif (strlen($password) < 10) {
        $error = "Password must be at least 10 characters.";
    } else {
        $conn = null;
        $check = null;
        $stmt = null;

        try {
            require_once __DIR__ . "/config/database.php";
            $conn->set_charset("utf8mb4");

            $check = $conn->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "This email is already registered. Use a different email.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    "INSERT INTO users (full_name, email, password, role)
                     VALUES (?, ?, ?, 'admin')"
                );
                $stmt->bind_param("sss", $fullName, $email, $hash);
                $stmt->execute();

                $message = "Administrator account created successfully!";
            }
        } catch (Throwable $e) {
            error_log("GrandVista admin setup error: " . $e->getMessage());
            $error = "Could not create account. Check the database connection and try again.";
        } finally {
            if ($stmt instanceof mysqli_stmt) $stmt->close();
            if ($check instanceof mysqli_stmt) $check->close();
            if ($conn instanceof mysqli) $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#101c25">
<title>Create Administrator | GrandVista</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #101c25;
    --gold: #d6b477;
    --muted: #748087;
    --border: #e6e5e0;
}

* { box-sizing: border-box; }

html, body { min-height: 100%; }

body {
    margin: 0;
    min-height: 100vh;
    padding: 22px 32px;
    display: grid;
    place-items: center;
    font-family: "DM Sans", sans-serif;
    color: var(--navy);
    background:
        linear-gradient(90deg, rgba(8,19,26,.83), rgba(8,19,26,.40)),
        url("https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=2000&q=85")
        center / cover no-repeat fixed,
        var(--navy);
}

.page-shell {
    width: min(100%, 1160px);
    display: grid;
    grid-template-columns: minmax(0, 1fr) 460px;
    align-items: center;
    gap: 65px;
    animation: enter .55s ease both;
}

.brand-panel { color: #fff; }

.brand {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 43px;
}

.brand-mark {
    width: 54px;
    height: 54px;
    display: grid;
    place-items: center;
    border: 1px solid var(--gold);
    color: var(--gold);
    font: 31px "Playfair Display", serif;
}

.brand-name {
    font: 30px "Playfair Display", serif;
    letter-spacing: .5px;
}

.brand-subtitle {
    margin-top: 4px;
    color: var(--gold);
    font-size: 9px;
    letter-spacing: 3px;
    text-transform: uppercase;
}

.eyebrow {
    color: var(--gold);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
}

h1 {
    margin: 20px 0 18px;
    font: 600 clamp(40px, 4.5vw, 59px)/1.13 "Playfair Display", serif;
}

h1 span {
    display: block;
    color: var(--gold);
    font-style: italic;
}

.brand-description {
    max-width: 440px;
    margin: 0;
    color: rgba(255,255,255,.82);
    font-size: 14px;
    line-height: 1.9;
}

.brand-footer {
    margin-top: 43px;
    color: rgba(255,255,255,.72);
    font-size: 10px;
    letter-spacing: 1.1px;
    line-height: 1.8;
}

.form-card {
    width: 100%;
    padding: 34px 34px 24px;
    border: 1px solid rgba(255,255,255,.75);
    border-radius: 5px;
    background: rgba(255,255,255,.98);
    box-shadow: 0 24px 75px rgba(0,0,0,.32);
}

.form-topline {
    width: 54px;
    height: 3px;
    margin-bottom: 21px;
    background: var(--gold);
}

h2 {
    margin: 0 0 9px;
    font: 600 31px "Playfair Display", serif;
}

.form-intro {
    margin: 0 0 22px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
}

.form-group { margin-bottom: 15px; }

label {
    display: block;
    margin-bottom: 7px;
    color: #36444b;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .7px;
}

.input-wrap { position: relative; }

.input-icon {
    position: absolute;
    top: 50%;
    left: 13px;
    transform: translateY(-50%);
    color: #a18a62;
    font-size: 15px;
    pointer-events: none;
}

input {
    display: block;
    width: 100%;
    height: 46px;
    padding: 0 13px 0 39px;
    border: 1px solid var(--border);
    border-radius: 3px;
    outline: none;
    background: #fff;
    color: var(--navy);
    font: 13px "DM Sans", sans-serif;
    transition: border-color .2s, box-shadow .2s, background .2s;
}

input::placeholder { color: #a0a9ae; }

input:hover { border-color: #c9b58f; }

input:focus {
    border-color: #b99b61;
    box-shadow: 0 0 0 3px rgba(214,180,119,.16);
    background: #fffefa;
}

.note {
    margin: 2px 0 17px;
    color: #7d898e;
    font-size: 10px;
    line-height: 1.7;
}

.submit-btn {
    width: 100%;
    min-height: 49px;
    padding: 13px 16px;
    border: 1px solid var(--gold);
    border-radius: 3px;
    background: var(--navy);
    color: #fff;
    font: 700 11px "DM Sans", sans-serif;
    letter-spacing: 1.1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: background .25s, color .25s, transform .25s;
}

.submit-btn:hover {
    background: var(--gold);
    color: var(--navy);
    transform: translateY(-2px);
}

.notice {
    margin: 0 0 17px;
    padding: 12px 14px;
    border-radius: 3px;
    font-size: 12px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.success {
    border-left: 3px solid #27824e;
    background: #edf8f0;
    color: #176437;
}

.error {
    border-left: 3px solid #bd4545;
    background: #fff0ef;
    color: #a32929;
}

.login-note {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ecebe7;
    color: #879197;
    text-align: center;
    font-size: 10px;
    line-height: 1.8;
}

.login-note strong {
    color: #59656b;
    letter-spacing: 1px;
}

@keyframes enter {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 850px) {
    body { padding: 22px 16px; }

    .page-shell {
        max-width: 500px;
        grid-template-columns: 1fr;
        gap: 22px;
    }

    .brand { margin-bottom: 13px; }

    .brand-mark { width: 43px; height: 43px; font-size: 25px; }
    .brand-name { font-size: 25px; }

    .eyebrow, .brand-description, .brand-footer { display: none; }

    h1 {
        margin: 0 0 4px;
        font-size: 30px;
    }

    h1 span { display: inline; }

    .form-card { padding: 27px 27px 21px; }
}

@media (max-height: 760px) and (min-width: 851px) {
    body { padding-top: 12px; padding-bottom: 12px; }

    .page-shell { gap: 42px; }
    .brand { margin-bottom: 25px; }
    h1 { margin: 13px 0; }
    .brand-footer { margin-top: 22px; }
    .form-card { padding-top: 22px; padding-bottom: 16px; }
    .form-topline { margin-bottom: 13px; }
    .form-intro { margin-bottom: 14px; }
    .form-group { margin-bottom: 10px; }
    input { height: 41px; }
    .note { margin-bottom: 12px; }
    .login-note { margin-top: 12px; padding-top: 9px; }
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
<main class="page-shell">

    <section class="brand-panel">
        <div class="brand">
            <div class="brand-mark">G</div>
            <div>
                <div class="brand-name">GrandVista</div>
                <div class="brand-subtitle">Luxury Hotel &amp; Resorts</div>
            </div>
        </div>

        <div class="eyebrow">Private Administration</div>

        <h1>Luxury Hotel <span>Management.</span></h1>

        <p class="brand-description">
            A smarter way to manage rooms, guests and reservations.
            Welcome to the GrandVista hotel administration portal.
        </p>

        <div class="brand-footer">
            ELEGANT STAYS · EXCEPTIONAL SERVICE · SEAMLESS MANAGEMENT
        </div>
    </section>

    <section class="form-card">
        <div class="form-topline"></div>

        <?php if ($message !== ""): ?>
            <h2>Account created</h2>
            <p class="form-intro">Your administrator account is ready.</p>

            <div class="notice success" role="status">
                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
            </div>

            <p class="note">
                Sign in using this account on your administrator login page.
            </p>

        <?php else: ?>
            <h2>Create Administrator</h2>
            <p class="form-intro">
                Add an administrator to your GrandVista team.
                Complete the details below to continue.
            </p>

            <?php if ($error !== ""): ?>
                <div class="notice error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <div class="form-group">
                    <label for="full_name">FULL NAME</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">&#9786;</span>
                        <input id="full_name" type="text" name="full_name"
                            placeholder="Enter full name" required maxlength="100"
                            autocomplete="name"
                            value="<?= htmlspecialchars($_POST["full_name"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">EMAIL ADDRESS</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">&#9993;</span>
                        <input id="email" type="email" name="email"
                            placeholder="admin@example.com" required maxlength="150"
                            autocomplete="email"
                            value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">PASSWORD</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">&#128274;</span>
                        <input id="password" type="password" name="password"
                            placeholder="At least 10 characters" required minlength="10"
                            autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group">
                    <label for="setup_key">PRIVATE SETUP KEY</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">&#128273;</span>
                        <input id="setup_key" type="password" name="setup_key"
                            placeholder="Enter your setup key" required
                            autocomplete="off">
                    </div>
                </div>

                <p class="note">
                    Use a unique email for each administrator. Keep your setup key private.
                </p>

                <button class="submit-btn" type="submit">
                    Create Admin Account &nbsp; &rarr;
                </button>
            </form>
        <?php endif; ?>

        <div class="login-note">
            <strong>GRANDVISTA</strong><br>
            Secure administrator setup
        </div>
    </section>

</main>
</body>
</html>
