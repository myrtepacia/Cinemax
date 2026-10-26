<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$email = site_contact_email();

render_head('Privacy Policy', [], [
    'description' => 'What personal data CSFlix collects, why, who sees it, how long it is kept, and your rights under the Philippine Data Privacy Act.',
    'index'       => true,
]);
render_header();
?>
  <main class="terms-page">

<?php render_policy_intro('Privacy Policy', 'This page explains what personal data CSFlix collects, why, who else sees it, how long we keep it and the rights you have under the Philippine Data Privacy Act of 2012 (Republic Act No. 10173).', '2026-10-26'); ?>

    <div class="terms-block">
      <h2>1. Who is responsible for your data</h2>
      <p>The CSFlix project team runs this website and decides how your personal data is used.<?php if ($email !== ''): ?> You can reach us at <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.<?php endif; ?></p>
    </div>

    <div class="terms-block">
      <h2>2. What we collect</h2>
      <ul>
        <li><strong>Your account:</strong> your name, email address, mobile number (only if you give one) and your password. Your password is stored scrambled (Argon2id), so nobody, including us, can read it.</li>
        <li><strong>Email check at sign-up:</strong> a one-time code we email you to prove the address is yours.</li>
        <li><strong>Your bookings:</strong> the film, date, showtime, seats, snacks, amounts, payment status, and when your ticket and snacks were scanned and by which staff account.</li>
        <li><strong>Payments:</strong> your payment is handled by PayMongo. We keep only PayMongo's reference numbers for it. We never see or store your card number, CVC, or e-wallet login.</li>
        <li><strong>Browser storage:</strong> the seats and snacks you picked, so they are still there after you sign in (cleared when you close the tab). These stay on your device and are never sent to us.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>3. Why we use it</h2>
      <ul>
        <li>To create and run your account, take your booking and issue your e-ticket (needed to give you the service you asked for).</li>
        <li>To check your ticket and snack order at the door and counter.</li>
        <li>To email your verification code, password reset links and payment receipts.</li>
        <li>To keep the website and your account safe from fraud and abuse (our legitimate interest).</li>
      </ul>
      <p>We do not use your data for advertising, we do not build profiles of you, and we do not sell your personal data to anyone.</p>
    </div>

    <div class="terms-block">
      <h2>4. Who else sees it</h2>
      <ul>
        <li><strong>PayMongo</strong> (payment processor), to take your payment. Their own privacy policy applies to what you enter on their pages.</li>
        <li><strong>Resend</strong> (email delivery), to send your verification code, password reset links and receipts.</li>
        <li><strong>Google Fonts</strong>: your browser downloads the website's font from Google, which receives your IP address when it does.</li>
        <li><strong>Our web host</strong>, which stores the website and its database.</li>
        <li><strong>CSFlix staff accounts</strong> see bookings to check tickets and prepare snacks.</li>
        <li><strong>Authorities</strong>, only when the law requires it.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>5. How long we keep it</h2>
      <ul>
        <li>Your account and bookings: until you ask us to delete your account.</li>
        <li>Unpaid bookings that ran out of time: their seats are released straight away; the booking record stays with your account.</li>
        <li>Your sign-in session: until you sign out, close the browser, or are idle for <?= e(intdiv(SESSION_IDLE_SECONDS, 3600)) ?> hours.</li>
      </ul>
    </div>

    <div class="terms-block">
      <h2>6. How we protect it</h2>
      <ul>
        <li>The website uses HTTPS, and passwords are stored scrambled.</li>
        <li>Only staff accounts can see bookings, and only for their work.</li>
        <li>Sign-in and other forms are protected against forged requests.</li>
      </ul>
      <p>No website is perfectly secure. If a breach puts your data at risk, we will tell you and, where the law requires, the National Privacy Commission.</p>
    </div>

    <div class="terms-block">
      <h2>7. Your rights</h2>
      <p>Under the Data Privacy Act you have the right to:</p>
      <ul>
        <li>be told how your data is used (this page);</li>
        <li>get a copy of the personal data we hold about you;</li>
        <li>have wrong or incomplete data corrected;</li>
        <li>object to how we use it, or withdraw your consent;</li>
        <li>have your data deleted or blocked, including your whole account;</li>
        <li>receive your data in a format you can take elsewhere;</li>
        <li>be paid damages if you are harmed by wrong use of your data; and</li>
        <li>complain to the National Privacy Commission (<a href="https://privacy.gov.ph" target="_blank" rel="noopener noreferrer">privacy.gov.ph</a>).</li>
      </ul>
      <p>To use any of these rights, email us from the address on your account. We aim to reply within 15 days.</p>
    </div>

    <div class="terms-block">
      <h2>8. Children</h2>
      <p>If you are under 18, please ask a parent or guardian before you create an account or book.</p>
    </div>

    <div class="terms-block">
      <h2>9. Changes to this policy</h2>
      <p>If we change this policy, we will update the date at the top of this page. If a change affects how we use data you already gave us, we will tell you by email or on the website first.</p>
    </div>

<?php render_policy_contact(); ?>

  </main>

<?php render_footer();
