
<?php
require_once __DIR__ . '/config/database.php';

$roomTypes = [];
$databaseError = false;

try {
    $result = $conn->query("SELECT * FROM room_types");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $roomTypes[] = $row;
        }
    }
} catch (Throwable $e) {
    $databaseError = true;
}

function roomValue($row, $keys, $fallback = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $fallback;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function roomImage($name) {
    $name = strtolower($name);

    if (strpos($name, 'deluxe') !== false) {
        return 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=900&q=85';
    }

    if (strpos($name, 'suite') !== false || strpos($name, 'luxury') !== false) {
        return 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=900&q=85';
    }

    if (strpos($name, 'family') !== false) {
        return 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=900&q=85';
    }

    return 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=900&q=85';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Experience luxury, comfort and exceptional hospitality at GrandVista Hotel.">
<title>GrandVista | Luxury Hotel & Resort</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --gold: #c8a66a;
    --gold-light: #e6cf9c;
    --navy: #101c25;
    --cream: #f8f6f1;
    --text: #343b3e;
    --muted: #747b7d;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; scroll-padding-top: 90px; }
body { margin: 0; font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--cream); }
a { color: inherit; text-decoration: none; }
button, input, select { font: inherit; }
img { display: block; width: 100%; object-fit: cover; }
.navbar {
    min-height: 86px; padding: 16px 7%; display: flex;
    align-items: center; justify-content: space-between; gap: 22px;
    background: var(--navy); color: white; position: sticky;
    top: 0; z-index: 100; border-bottom: 1px solid #ffffff18;
}
.brand { display: flex; align-items: center; gap: 12px; font-size: 16px; letter-spacing: 2px; font-weight: 700; }
.brand-icon { border: 1px solid var(--gold); color: var(--gold); width: 43px; height: 43px; display: grid; place-items: center; font: 30px 'Playfair Display', serif; }
.brand small { display: block; font-size: 9px; letter-spacing: 3px; margin-top: 5px; color: var(--gold-light); }
nav { display: flex; align-items: center; gap: 22px; }
nav a { font-size: 12px; color: #e0e4e5; transition: .25s; }
nav a:hover { color: var(--gold-light); }
nav .nav-book { border: 1px solid var(--gold); padding: 12px 15px; color: var(--gold-light); }
nav .nav-admin { background: var(--gold); color: var(--navy); padding: 13px 16px; font-weight: 700; }
nav .nav-admin:hover { background: var(--gold-light); color: var(--navy); }
.menu-toggle { display: none; background: transparent; color: white; border: 1px solid #ffffff50; padding: 8px 12px; font-size: 21px; cursor: pointer; }
.hero {
    min-height: 650px; height: min(760px, 83vh); position: relative;
    display: flex; align-items: center; padding: 0 10%; color: white;
    background: url('https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=2200&q=90') center 53%/cover;
}
.hero-overlay { position: absolute; inset: 0; background: linear-gradient(90deg,#0c1820e8 0%,#0c1820a8 48%,#0c18202c 100%); }
.hero-content { max-width: 700px; position: relative; z-index: 1; animation: rise .9s ease both; }
.eyebrow { color: var(--gold-light); font-size: 10px; letter-spacing: 3px; font-weight: 700; }
.hero h1 { font: 500 clamp(45px,6.5vw,83px)/1.12 'Playfair Display',serif; margin: 26px 0; letter-spacing: -2px; }
h1 em,h2 em { color: var(--gold); font-weight: 400; }
.hero-content>p { max-width: 490px; line-height: 1.9; font-size: 14px; color: #e1e5e6; }
.hero-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 35px; }
.btn { border: 1px solid transparent; display: inline-flex; justify-content: center; align-items: center; padding: 16px 23px; font-size: 11px; font-weight: 700; cursor: pointer; transition: .25s; }
.btn:hover { transform: translateY(-3px); }
.btn-gold { background: var(--gold); color: var(--navy); }
.btn-gold:hover { background: var(--gold-light); }
.btn-outline { color: white; border-color: #ffffff70; }
.btn-outline:hover { border-color: var(--gold); color: var(--gold-light); }
.btn-dark { background: var(--navy); color: white; }
.btn-dark:hover { background: #273c4b; }
.hero-caption { position: absolute; bottom: 37px; right: 7%; font-size: 9px; letter-spacing: 2px; color: #ffffffb5; }
.scroll-indicator { position: absolute; bottom: 35px; left: 7%; color: white; font-size: 9px; letter-spacing: 2px; }
.booking-wrap { position: relative; z-index: 2; padding: 0 7%; background: white; }
.booking-bar { max-width: 1200px; margin: auto; min-height: 120px; display: grid; grid-template-columns: 1fr 1fr .8fr auto; align-items: center; gap: 20px; }
.booking-field { padding: 12px 0; }
.booking-field label { display: block; font-size: 9px; letter-spacing: 2px; font-weight: 700; margin-bottom: 12px; color: #7b7d7d; }
.booking-field input,.booking-field select { width: 100%; border: none; outline: none; background: transparent; color: var(--navy); font-size: 13px; min-width: 0; }
.form-message { max-width: 1200px; margin: 0 auto; padding: 0 0 16px; color: #375e48; font-size: 12px; }
.form-message:empty { display: none; }
.form-message strong { display: block; font-size: 15px; margin-bottom: 12px; }
.section { padding: 105px 8%; }
.about { display: grid; grid-template-columns: 1fr 1fr; gap: 9%; align-items: center; }
.about-image { position: relative; padding: 0 0 30px 30px; }
.about-image::before { content: ""; position: absolute; inset: 30px 30px 0 0; border: 1px solid var(--gold); }
.about-image img { position: relative; height: 480px; }
.image-label { position: absolute; bottom: 0; right: 0; background: var(--navy); color: white; padding: 18px 22px; display: flex; align-items: center; gap: 13px; }
.image-label strong { color: var(--gold); font: 30px 'Playfair Display',serif; }
.image-label span { font-size: 9px; letter-spacing: 1px; }
.dark-eyebrow { color: #a57e3d; }
h2 { font: 500 clamp(36px,4vw,52px)/1.2 'Playfair Display',serif; color: var(--navy); margin: 20px 0; }
.about-content h2 em,.section-heading h2 em { color: #b58c4b; }
.lead { font: 20px 'Playfair Display',serif; }
.about-content>p:not(.lead) { color: var(--muted); font-size: 13px; line-height: 2; }
.stats { display: flex; gap: 30px; margin: 35px 0; border-top: 1px solid #e3ded3; padding-top: 25px; }
.stats div { display: flex; flex-direction: column; gap: 8px; }
.stats strong { font: 29px 'Playfair Display',serif; color: #b58c4b; }
.stats span { font-size: 10px; color: var(--muted); }
.text-link,.room-link { display: inline-block; border-bottom: 1px solid var(--gold); padding-bottom: 8px; font-size: 11px; font-weight: 700; }
.rooms { background: #f0ede6; }
.section-heading { text-align: center; max-width: 650px; margin: 0 auto 55px; }
.section-heading>p { font-size: 13px; color: var(--muted); line-height: 1.8; }
.room-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 25px; max-width: 1300px; margin: auto; }
.room-card { background: white; transition: transform .35s,box-shadow .35s; }
.room-card:hover { transform: translateY(-7px); box-shadow: 0 20px 45px #18263014; }
.room-image { position: relative; overflow: hidden; }
.room-image img { height: 255px; transition: transform .65s; }
.room-card:hover .room-image img { transform: scale(1.06); }
.room-tag { position: absolute; top: 16px; left: 16px; background: var(--gold); color: var(--navy); font-size: 8px; letter-spacing: 1px; padding: 9px 11px; font-weight: 700; }
.room-info { padding: 25px 23px 30px; }
.room-category { font-size: 8px; letter-spacing: 2px; color: #a57e3d; font-weight: 700; }
.room-info h3 { font: 26px 'Playfair Display',serif; color: var(--navy); margin: 10px 0 0; }
.room-price { color: #a57e3d; font-weight: 700; font-size: 19px; margin: 18px 0 12px; }
.room-price small { color: var(--muted); font-size: 10px; font-weight: 400; }
.room-info>p:not(.room-price) { font-size: 11px; line-height: 1.9; color: var(--muted); min-height: 42px; }
.room-features { display: flex; flex-wrap: wrap; gap: 12px; border-top: 1px solid #eee9e0; padding: 17px 0; margin-top: 15px; font-size: 9px; color: #777; }
.room-link { margin-top: 8px; }
.experience { background: linear-gradient(#101c25e8,#101c25f2),url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1800&q=85') center/cover; color: white; padding: 105px 9%; }
.experience-content { max-width: 1100px; margin: auto; }
.experience h2 { color: white; font-size: clamp(38px,5vw,58px); }
.experience-content>p { max-width: 520px; color: #c7ccce; font-size: 13px; line-height: 1.9; }
.amenities-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 25px; margin-top: 55px; }
.amenities-grid>div { display: flex; flex-direction: column; gap: 12px; border-top: 1px solid #ffffff35; padding-top: 22px; }
.amenities-grid>div>span { font-size: 27px; color: var(--gold); }
.amenities-grid strong { font: 19px 'Playfair Display',serif; }
.amenities-grid small { font-size: 10px; color: #c7ccce; }
.contact { text-align: center; display: flex; flex-direction: column; align-items: center; }
.contact .section-heading { margin-bottom: 30px; }
footer { background: #0b151c; color: #b9c0c3; padding: 40px 8%; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px; }
.footer-brand { color: white; }
footer p,footer>span { font-size: 10px; }
.reveal { opacity: 0; transform: translateY(25px); transition: opacity .75s ease,transform .75s ease; }
.reveal.visible { opacity: 1; transform: translateY(0); }
@keyframes rise { from { opacity: 0; transform: translateY(25px); } to { opacity: 1; transform: translateY(0); } }

@media(max-width:1050px) {
    .navbar { padding: 16px 4%; }
    nav { gap: 12px; }
    .hero { padding: 0 7%; }
    .section { padding: 85px 5%; }
    .about { gap: 5%; }
    .room-grid { gap: 15px; }
    .room-info { padding: 20px 16px 25px; }
    .room-image img { height: 210px; }
}
@media(max-width:900px) {
    .navbar { flex-wrap: wrap; }
    nav { gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
}
@media(max-width:760px) {
    .navbar { min-height: 75px; }
    .brand { font-size: 13px; }
    .menu-toggle { display: block; }
    nav { display: none; position: absolute; top: 75px; left: 0; right: 0; background: var(--navy); padding: 22px 6% 28px; flex-direction: column; align-items: stretch; gap: 0; }
    nav.open { display: flex; }
    nav a { padding: 14px 0; border-bottom: 1px solid #ffffff16; }
    nav .nav-book,nav .nav-admin { text-align: center; margin-top: 12px; padding: 14px; }
    .hero { min-height: 600px; height: 75vh; padding: 0 7%; background-position: 60% center; }
    .hero h1 { font-size: clamp(43px,12vw,66px); }
    .hero-content>p { font-size: 13px; }
    .hero-actions { flex-direction: column; align-items: flex-start; }
    .hero-actions .btn { width: min(100%,270px); }
    .hero-caption { display: none; }
    .booking-wrap { padding: 10px 7% 20px; }
    .booking-bar { grid-template-columns: 1fr 1fr; gap: 8px 20px; padding: 10px 0; }
    .booking-bar .btn { grid-column: 1/-1; }
    .about { grid-template-columns: 1fr; gap: 40px; }
    .about-image img { height: 370px; }
    .stats { gap: 25px; }
    .stats strong { font-size: 25px; }
    .room-grid { grid-template-columns: 1fr; max-width: 480px; }
    .room-image img { height: 270px; }
    .room-info { padding: 25px; }
    .amenities-grid { grid-template-columns: 1fr 1fr; gap: 25px 18px; }
    .experience { padding: 80px 7%; }
    .section { padding: 75px 7%; }
    footer { flex-direction: column; align-items: flex-start; }
}
@media(prefers-reduced-motion:reduce) {
    *,*::before,*::after { scroll-behavior: auto !important; animation: none !important; transition: none !important; }
    .reveal { opacity: 1; transform: none; }
}
</style>
</head>
<body>

<header class="navbar">
    <a href="#home" class="brand">
        <span class="brand-icon">G</span>
        <span>GRANDVISTA<small>HOTEL & RESORT</small></span>
    </a>

    <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navMenu" type="button">☰</button>

    <nav id="navMenu">
        <a href="#home">Home</a>
        <a href="#about">About</a>
        <a href="#rooms">Our Rooms</a>
        <a href="#amenities">Amenities</a>
        <a href="#contact">Contact</a>
        <a href="#booking" class="nav-book">Book Your Stay ↗</a>
        
    </nav>
</header>

<main>
    <section class="hero" id="home">
        <div class="hero-overlay"></div>
        <div class="hero-content reveal">
            <span class="eyebrow">✦ A WORLD OF ELEGANCE ✦</span>
            <h1>Where Luxury<br>Meets <em>Serenity.</em></h1>
            <p>Discover exceptional comfort, timeless elegance and unforgettable moments at GrandVista.</p>
            <div class="hero-actions">
                <a href="#rooms" class="btn btn-gold">Explore Our Rooms ↗</a>
                <a href="#about" class="btn btn-outline">Discover GrandVista</a>
            </div>
        </div>
        <div class="hero-caption">EXCEPTIONAL STAYS. TIMELESS MEMORIES.</div>
        <a href="#about" class="scroll-indicator">SCROLL TO EXPLORE ↓</a>
    </section>

    <section class="booking-wrap" id="booking">
        <form class="booking-bar" id="bookingForm">
            <div class="booking-field">
                <label for="checkin">CHECK IN</label>
                <input type="date" id="checkin" required>
            </div>
            <div class="booking-field">
                <label for="checkout">CHECK OUT</label>
                <input type="date" id="checkout" required>
            </div>
            <div class="booking-field">
                <label for="guests">GUESTS</label>
                <select id="guests">
                    <option value="1">1 Guest</option>
                    <option value="2" selected>2 Guests</option>
                    <option value="3">3 Guests</option>
                    <option value="4">4 Guests</option>
                </select>
            </div>
            <button class="btn btn-dark" type="submit">Check Availability ↗</button>
        </form>

        <div class="form-message" id="bookingMessage" aria-live="polite"></div>
    </section>

    <section class="about section" id="about">
        <div class="about-image reveal">
            <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1000&q=85" alt="Elegant luxury hotel surrounded by beautiful scenery">
            <div class="image-label"><strong>01</strong><span>THE GRANDVISTA EXPERIENCE</span></div>
        </div>
        <div class="about-content reveal">
            <span class="eyebrow dark-eyebrow">WELCOME TO GRANDVISTA</span>
            <h2>More than a stay.<br><em>A feeling.</em></h2>
            <p class="lead">An extraordinary escape designed around you.</p>
            <p>Discover thoughtful hospitality, beautifully designed spaces and exceptional attention to every detail. At GrandVista, every moment is an invitation to slow down and indulge.</p>
            <div class="stats">
                <div><strong>25+</strong><span>Luxury Rooms</span></div>
                <div><strong>5★</strong><span>Guest Experience</span></div>
                <div><strong>24/7</strong><span>Guest Support</span></div>
            </div>
            <a href="#rooms" class="text-link">Discover Our Hospitality ↗</a>
        </div>
    </section>

    <section class="rooms section" id="rooms">
        <div class="section-heading reveal">
            <span class="eyebrow dark-eyebrow">YOUR PRIVATE SANCTUARY</span>
            <h2>Stay in <em>Style.</em></h2>
            <p>Spaces designed for comfort, crafted for unforgettable stays.</p>
        </div>

        <div class="room-grid">
        <?php if ($databaseError): ?>
            <p>Room information could not be loaded. Please check your database connection.</p>
        <?php elseif (count($roomTypes) > 0): ?>
            <?php foreach ($roomTypes as $index => $room): ?>
                <?php
                    $name = roomValue($room, ['type_name', 'room_type', 'room_type_name', 'name', 'title'], 'Guest Room');
                    $description = roomValue($room, ['description', 'details', 'room_description'], 'A refined retreat with elegant interiors and thoughtful comforts.');
                    $price = roomValue($room, ['price_per_night', 'price', 'room_price', 'base_price'], '');
                    $capacity = roomValue($room, ['capacity', 'max_guests', 'guest_capacity', 'persons'], '2');
                    $category = stripos($name, 'family') !== false ? 'FAMILY COLLECTION' : (stripos($name, 'suite') !== false ? 'SIGNATURE COLLECTION' : 'CLASSIC COLLECTION');
                    $bed = stripos($name, 'family') !== false ? 'Twin Beds' : 'King Bed';
                ?>
                <article class="room-card reveal">
                    <div class="room-image">
                        <img src="<?= e(roomImage($name)) ?>" alt="<?= e($name) ?>">
                        <?php if ($index === 0): ?><span class="room-tag">FEATURED ROOM</span><?php endif; ?>
                    </div>
                    <div class="room-info">
                        <div>
                            <span class="room-category"><?= e($category) ?></span>
                            <h3><?= e($name) ?></h3>
                        </div>
                        <p class="room-price">
                            <?= $price !== '' ? '₹' . e(number_format((float)$price)) : 'Contact us' ?>
                            <small>/ night</small>
                        </p>
                        <p><?= e($description) ?></p>
                        <div class="room-features">
                            <span>♧ <?= e($capacity) ?> Guests</span>
                            <span>▧ <?= e($bed) ?></span>
                            <span>❄ AC</span>
                        </div>
                        <a href="#booking" class="room-link">Reserve This Room ↗</a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No room types have been added yet. Please check the room management section.</p>
        <?php endif; ?>
        </div>
    </section>

    <section class="experience" id="amenities">
        <div class="experience-content reveal">
            <span class="eyebrow">THE ART OF HOSPITALITY</span>
            <h2>Every detail.<br><em>Beautifully considered.</em></h2>
            <p>From peaceful mornings to unforgettable evenings, enjoy a stay that feels effortlessly special.</p>
            <div class="amenities-grid">
                <div><span>✧</span><strong>Fine Dining</strong><small>Delight in every flavour</small></div>
                <div><span>◇</span><strong>Wellness & Spa</strong><small>Find your balance</small></div>
                <div><span>⌂</span><strong>Luxury Suites</strong><small>Your personal retreat</small></div>
                <div><span>♧</span><strong>Personal Service</strong><small>Here for every detail</small></div>
            </div>
        </div>
    </section>

    <section class="contact section" id="contact">
        <div class="section-heading reveal">
            <span class="eyebrow dark-eyebrow">WE WOULD LOVE TO HEAR FROM YOU</span>
            <h2>Your next escape <em>starts here.</em></h2>
            <p>Have a question or planning a special stay? Get in touch.</p>
        </div>
        <a href="mailto:info@grandvista.example" class="btn btn-dark">Contact GrandVista ↗</a>
    </section>
</main>

<footer>
    <a href="#home" class="brand footer-brand">
        <span class="brand-icon">G</span>
        <span>GRANDVISTA<small>HOTEL & RESORT</small></span>
    </a>
    <p>Luxury in every moment. Memories in every stay.</p>
    <span>© <?= date('Y') ?> GrandVista. All rights reserved.</span>
    <a href="admin-login.php">Admin Login</a>
</footer>

<script>
const menuToggle = document.getElementById("menuToggle");
const navMenu = document.getElementById("navMenu");
const bookingForm = document.getElementById("bookingForm");
const bookingMessage = document.getElementById("bookingMessage");
const checkin = document.getElementById("checkin");
const checkout = document.getElementById("checkout");
const guestsSelect = document.getElementById("guests");

if (menuToggle && navMenu) {
    menuToggle.addEventListener("click", () => {
        const isOpen = navMenu.classList.toggle("open");
        menuToggle.setAttribute("aria-expanded", String(isOpen));
        menuToggle.textContent = isOpen ? "✕" : "☰";
    });

    navMenu.querySelectorAll("a").forEach(link => {
        link.addEventListener("click", () => {
            navMenu.classList.remove("open");
            menuToggle.setAttribute("aria-expanded", "false");
            menuToggle.textContent = "☰";
        });
    });
}

const today = new Date();
const localToday = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, "0"),
    String(today.getDate()).padStart(2, "0")
].join("-");

checkin.min = localToday;
checkout.min = localToday;

checkin.addEventListener("change", () => {
    checkout.min = checkin.value || localToday;

    if (checkout.value && checkout.value <= checkin.value) {
        checkout.value = "";
    }
});

bookingForm.addEventListener("submit", async event => {
    event.preventDefault();

    if (!checkin.value || !checkout.value) {
        bookingMessage.textContent =
            "Please select your check-in and check-out dates.";
        return;
    }

    if (checkin.value < localToday || checkout.value <= checkin.value) {
        bookingMessage.textContent =
            "Please select valid dates and stay at least one night.";
        return;
    }

    bookingMessage.textContent = "Checking available rooms...";

    const formData = new FormData();
    formData.append("checkin", checkin.value);
    formData.append("checkout", checkout.value);
    formData.append("guests", guestsSelect.value);

    try {
        const response = await fetch("check-availability.php", {
            method: "POST",
            body: formData
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            bookingMessage.textContent =
                data.message || "Unable to check availability. Please try again.";
            return;
        }

        if (!Array.isArray(data.rooms) || data.rooms.length === 0) {
            bookingMessage.textContent =
                "Sorry, no rooms are available for your selected dates. Please try different dates.";
            return;
        }

        const money = amount =>
            new Intl.NumberFormat("en-IN", {
                style: "currency",
                currency: "INR",
                maximumFractionDigits: 2
            }).format(Number(amount));

        const safe = value => String(value ?? "").replace(
            /[&<>"']/g,
            char => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;"
            })[char]
        );

        bookingMessage.innerHTML =
            `<strong>${data.rooms.length} room(s) available for your selected dates!</strong>` +
            `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:16px;">` +
            data.rooms.map(room => `
                <div style="background:#fff;padding:18px;border:1px solid #e5dfd3;border-radius:6px;">
                    <h3 style="margin:0 0 8px;color:#101c25;">
                        ${safe(room.type_name)} — Room ${safe(room.room_number)}
                    </h3>
                    <p style="margin:6px 0;">Price: <strong>${money(room.price_per_night)} / night</strong></p>
                    <p style="margin:6px 0;">Capacity: ${safe(room.capacity)} guest(s)</p>
                    <p style="margin:6px 0;">Bed: ${safe(room.bed_type || "Standard")}</p>
                    <p style="margin:6px 0;color:#375e48;">Available for your selected dates</p>
                </div>
            `).join("") +
            `</div>`;
    } catch (error) {
        bookingMessage.textContent =
            "Unable to check room availability. Please check your connection and try again.";
        console.error("Availability error:", error);
    }
});

const revealElements = document.querySelectorAll(".reveal");

if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealElements.forEach(element => observer.observe(element));
} else {
    revealElements.forEach(element => element.classList.add("visible"));
}
</script>

</body>
</html>
