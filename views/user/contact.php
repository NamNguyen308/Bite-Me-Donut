<?php
// contact.php — Bite-Me-Donut Contact Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact Us — Bite-Me Donuts</title>
  <link rel="stylesheet" href="../../public/assets/css/root.css" />
  <link rel="stylesheet" href="../../public/assets/css/contact.css" />
</head>
<body>

  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <!-- ======================================================
       HERO
  ====================================================== -->
  <section class="contact-hero">
    <div class="container">
      <span class="contact-hero__eyebrow">We'd Love to Hear From You</span>
      <h1 class="contact-hero__title">Get in Touch</h1>
      <p class="contact-hero__subtitle">
        Questions, custom orders, feedback, or just want to say hi?<br>
        Drop us a message and we'll get back to you faster than a fresh glaze dries.
      </p>
    </div>
  </section>

  <!-- ======================================================
       MAIN BODY
  ====================================================== -->
  <main class="section">
    <div class="container">

      <div class="contact-layout">

        <!-- ================================================
             LEFT — Contact Information
        ================================================ -->
        <aside class="contact-info" aria-label="Contact information">

          <!-- Intro -->
          <div class="contact-intro">
            <h2 class="contact-intro__title">Say Hello</h2>
            <p class="contact-intro__desc">
              Our team is small, friendly, and powered entirely by sugar. We're usually quick to respond — reach us through any channel that works best for you.
            </p>
          </div>

          <!-- Phone / Zalo -->
          <div class="contact-card">
            <div class="contact-card__icon-wrap" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.38 2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.76a16 16 0 0 0 6.18 6.18l.95-.94a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
            </div>
            <div class="contact-card__body">
              <p class="contact-card__label">Phone &amp; Zalo</p>
              <p class="contact-card__value">
                <a href="tel:+84901234567">0901 234 567</a>
              </p>
              <p class="contact-card__sub">Available daily, 8:00 AM – 7:00 PM (GMT+7)</p>
            </div>
          </div>

          <!-- Email -->
          <div class="contact-card">
            <div class="contact-card__icon-wrap" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
              </svg>
            </div>
            <div class="contact-card__body">
              <p class="contact-card__label">Email</p>
              <p class="contact-card__value">
                <a href="mailto:hello@bitemedonuts.com">hello@bitemedonuts.com</a>
              </p>
              <p class="contact-card__sub">We typically reply within a few hours on business days.</p>
            </div>
          </div>

          <!-- Store address -->
          <div class="contact-card">
            <div class="contact-card__icon-wrap" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            </div>
            <div class="contact-card__body">
              <p class="contact-card__label">Store Address</p>
              <p class="contact-card__value">279 Nguyen Tri Phuong</p>
              <p class="contact-card__sub">Ho Chi Minh City, Vietnam</p>
            </div>
          </div>

          <!-- Business hours -->
          <div class="contact-hours">
            <div class="contact-hours__header">
              <svg class="contact-hours__header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
              <p class="contact-hours__header-title">Business Hours</p>
            </div>
            <div class="contact-hours__row">
              <span class="contact-hours__day">Monday – Friday</span>
              <span class="contact-hours__time">8:00 AM – 7:00 PM</span>
            </div>
            <div class="contact-hours__row">
              <span class="contact-hours__day">Saturday</span>
              <span class="contact-hours__time">8:00 AM – 8:00 PM</span>
            </div>
            <div class="contact-hours__row">
              <span class="contact-hours__day">Sunday</span>
              <span class="contact-hours__time">9:00 AM – 6:00 PM</span>
            </div>
            <div class="contact-hours__row">
              <span class="contact-hours__day">Public Holidays</span>
              <span class="contact-hours__time">10:00 AM – 5:00 PM</span>
            </div>
          </div>

          <!-- Social links -->
          <div class="contact-social">
            <p class="contact-social__title">Follow Us</p>
            <div class="contact-social__links">
              <!-- Facebook -->
              <a class="contact-social__link" href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                </svg>
              </a>
              <!-- Instagram -->
              <a class="contact-social__link" href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                  <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                  <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                </svg>
              </a>
              <!-- TikTok -->
              <a class="contact-social__link" href="https://tiktok.com" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                </svg>
              </a>
              <!-- YouTube -->
              <a class="contact-social__link" href="https://youtube.com" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.97C18.88 4 12 4 12 4s-6.88 0-8.59.45A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.4a2.78 2.78 0 0 0 1.95-1.97A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/>
                  <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/>
                </svg>
              </a>
            </div>
          </div>

        </aside><!-- /.contact-info -->

        <!-- ================================================
             RIGHT — Contact Form
        ================================================ -->
        <div class="contact-form-wrap">
          <div class="contact-form-wrap__header">
            <h2 class="contact-form-wrap__title">Send Us a Message</h2>
            <p class="contact-form-wrap__subtitle">Fill in the form below and we'll get back to you as soon as possible.</p>
          </div>

          <div class="contact-form-wrap__body">

            <!-- Success state (hidden by default, shown by JS) -->
            <div class="contact-success" id="contactSuccess" role="status" aria-live="polite">
              <div class="contact-success__icon-wrap" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                  <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
              </div>
              <h3 class="contact-success__title">Message Sent!</h3>
              <p class="contact-success__desc">
                Thanks for reaching out. We've received your message and will get back to you within a few hours. In the meantime, treat yourself to a donut!
              </p>
              <button class="btn btn--outline" id="contactSuccessReset" type="button">Send Another Message</button>
            </div>

            <!-- Actual form -->
            <form class="contact-form" id="contactForm" novalidate>

              <!-- Name + Email -->
              <div class="contact-form__row">
                <div class="form-group">
                  <label class="form-label" for="contact-name">Full Name <span style="color:var(--color-danger)">*</span></label>
                  <input
                    class="form-input"
                    type="text"
                    id="contact-name"
                    name="name"
                    placeholder="e.g. Nguyen Van A"
                    autocomplete="name"
                    required
                  />
                  <span class="form-error" id="error-name" aria-live="polite"></span>
                </div>

                <div class="form-group">
                  <label class="form-label" for="contact-email">Email Address <span style="color:var(--color-danger)">*</span></label>
                  <input
                    class="form-input"
                    type="email"
                    id="contact-email"
                    name="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    required
                  />
                  <span class="form-error" id="error-email" aria-live="polite"></span>
                </div>
              </div>

              <!-- Phone + Order number -->
              <div class="contact-form__row">
                <div class="form-group">
                  <label class="form-label" for="contact-phone">Phone / Zalo</label>
                  <input
                    class="form-input"
                    type="tel"
                    id="contact-phone"
                    name="phone"
                    placeholder="e.g. 0901 234 567"
                    autocomplete="tel"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label" for="contact-order">Order Number <span class="text-muted">(optional)</span></label>
                  <input
                    class="form-input"
                    type="text"
                    id="contact-order"
                    name="order_number"
                    placeholder="e.g. ORD-20250501"
                  />
                </div>
              </div>

              <!-- Subject tabs -->
              <div class="form-group">
                <label class="form-label">What is this about? <span style="color:var(--color-danger)">*</span></label>
                <div class="contact-subject-tabs" role="group" aria-label="Message subject">
                  <button type="button" class="contact-subject-tab is-active" data-subject="General Inquiry" aria-pressed="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    General Inquiry
                  </button>
                  <button type="button" class="contact-subject-tab" data-subject="Custom Order" aria-pressed="false">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/>
                      <line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/>
                      <line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/>
                      <line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>
                    </svg>
                    Custom Order
                  </button>
                  <button type="button" class="contact-subject-tab" data-subject="Order Issue" aria-pressed="false">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                      <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    Order Issue
                  </button>
                  <button type="button" class="contact-subject-tab" data-subject="Feedback" aria-pressed="false">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    Feedback
                  </button>
                  <button type="button" class="contact-subject-tab" data-subject="Partnership" aria-pressed="false">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                      <circle cx="9" cy="7" r="4"/>
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Partnership
                  </button>
                </div>
                <input type="hidden" id="contact-subject" name="subject" value="General Inquiry" />
                <span class="form-error" id="error-subject" aria-live="polite"></span>
              </div>

              <!-- Rating (shown only for Feedback) -->
              <div class="form-group" id="ratingGroup" style="display:none;">
                <label class="form-label">How would you rate your experience?</label>
                <div class="contact-rating" role="group" aria-label="Rating">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                  <button type="button" class="contact-rating__star" data-value="<?= $i ?>" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </button>
                  <?php endfor; ?>
                </div>
                <input type="hidden" id="contact-rating" name="rating" value="" />
              </div>

              <!-- Message -->
              <div class="form-group">
                <label class="form-label" for="contact-message">Message <span style="color:var(--color-danger)">*</span></label>
                <textarea
                  class="form-input"
                  id="contact-message"
                  name="message"
                  rows="6"
                  placeholder="Tell us how we can help. The more detail you share, the faster we can assist you."
                  maxlength="1000"
                  required
                ></textarea>
                <div class="contact-form__counter" id="charCounter" aria-live="polite">
                  <span id="charCount">0</span> / 1000
                </div>
                <span class="form-error" id="error-message" aria-live="polite"></span>
              </div>

              <!-- Newsletter opt-in -->
              <div class="form-group" style="flex-direction: row; align-items: center; gap: var(--space-3); margin-bottom: 0;">
                <input
                  type="checkbox"
                  id="contact-newsletter"
                  name="newsletter"
                  style="width:18px; height:18px; accent-color: var(--color-primary); cursor:pointer; flex-shrink:0;"
                />
                <label for="contact-newsletter" style="font-size: var(--text-sm); color: var(--color-text-muted); cursor:pointer; margin:0;">
                  Subscribe me to the newsletter for new flavors and promotions.
                </label>
              </div>

              <!-- Submit row -->
              <div class="contact-form__submit-row">
                <p class="contact-form__privacy">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                  </svg>
                  Your data is safe with us. We never spam.
                </p>
                <button class="btn btn--primary btn--lg" type="submit" id="contactSubmitBtn">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;" aria-hidden="true">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                  </svg>
                  Send Message
                </button>
              </div>

            </form><!-- /#contactForm -->

          </div><!-- /.contact-form-wrap__body -->
        </div><!-- /.contact-form-wrap -->

      </div><!-- /.contact-layout -->

      <!-- ================================================
           MAP STRIP
      ================================================ -->
      <!-- <div class="contact-map-strip" aria-label="Store location">
          Replace the placeholder below with a real <iframe> embed from Google Maps:
          <iframe
            src="https://www.google.com/maps/embed?pb=..."
            width="100%"
            height="320"
            style="border:0; display:block;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Bite-Me Donuts store location"
          ></iframe>
        
        <div class="contact-map-strip__overlay">
          <p class="contact-map-strip__overlay-title">Bite-Me Donuts</p>
          <p class="contact-map-strip__overlay-addr">279 Nguyen Tri Phuong<br>Ho Chi Minh City, Vietnam</p>
        </div>
        <div class="contact-map-strip__placeholder" aria-hidden="true">
          <svg class="contact-map-strip__placeholder-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
          <p>Replace this placeholder with a Google Maps embed iframe.</p>
        </div>
      </div> -->

    </div><!-- /.container -->
  </main>

  <?php include __DIR__ . '/../layouts/footer.php'; ?>

  <!-- ======================================================
       CONTACT PAGE JAVASCRIPT
  ====================================================== -->
  <script>
  (function () {
    'use strict';

    /* ---------- Subject tabs ---------- */
    const tabs        = document.querySelectorAll('.contact-subject-tab');
    const subjectInput = document.getElementById('contact-subject');
    const ratingGroup = document.getElementById('ratingGroup');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.classList.remove('is-active');
          t.setAttribute('aria-pressed', 'false');
        });
        tab.classList.add('is-active');
        tab.setAttribute('aria-pressed', 'true');
        subjectInput.value = tab.dataset.subject;

        // Show rating stars only for Feedback
        ratingGroup.style.display = (tab.dataset.subject === 'Feedback') ? '' : 'none';
      });
    });

    /* ---------- Star rating ---------- */
    const stars       = document.querySelectorAll('.contact-rating__star');
    const ratingInput = document.getElementById('contact-rating');
    let currentRating = 0;

    stars.forEach(function (star) {
      star.addEventListener('click', function () {
        currentRating = parseInt(star.dataset.value, 10);
        ratingInput.value = currentRating;
        updateStars(currentRating);
      });

      star.addEventListener('mouseenter', function () {
        updateStars(parseInt(star.dataset.value, 10));
      });
    });

    document.querySelector('.contact-rating').addEventListener('mouseleave', function () {
      updateStars(currentRating);
    });

    function updateStars(rating) {
      stars.forEach(function (s) {
        const val = parseInt(s.dataset.value, 10);
        if (val <= rating) {
          s.classList.add('is-active');
        } else {
          s.classList.remove('is-active');
        }
      });
    }

    /* ---------- Character counter ---------- */
    const textarea   = document.getElementById('contact-message');
    const charCount  = document.getElementById('charCount');
    const counterEl  = document.getElementById('charCounter');

    textarea.addEventListener('input', function () {
      const len = textarea.value.length;
      charCount.textContent = len;
      if (len >= 950) {
        counterEl.classList.add('is-limit');
      } else {
        counterEl.classList.remove('is-limit');
      }
    });

    /* ---------- Inline validation helpers ---------- */
    function showError(id, msg) {
      const el = document.getElementById(id);
      if (el) el.textContent = msg;
    }

    function clearError(id) {
      const el = document.getElementById(id);
      if (el) el.textContent = '';
    }

    function validateEmail(val) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }

    /* Clear errors on input */
    document.getElementById('contact-name').addEventListener('input', function () { clearError('error-name'); });
    document.getElementById('contact-email').addEventListener('input', function () { clearError('error-email'); });
    textarea.addEventListener('input', function () { clearError('error-message'); });

    /* ---------- Form submission ---------- */
    const form        = document.getElementById('contactForm');
    const submitBtn   = document.getElementById('contactSubmitBtn');
    const successBox  = document.getElementById('contactSuccess');
    const resetBtn    = document.getElementById('contactSuccessReset');

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const name    = document.getElementById('contact-name').value.trim();
      const email   = document.getElementById('contact-email').value.trim();
      const message = textarea.value.trim();
      let valid     = true;

      clearError('error-name');
      clearError('error-email');
      clearError('error-message');

      if (!name) {
        showError('error-name', 'Please enter your full name.');
        valid = false;
      }

      if (!email) {
        showError('error-email', 'Please enter your email address.');
        valid = false;
      } else if (!validateEmail(email)) {
        showError('error-email', 'Please enter a valid email address.');
        valid = false;
      }

      if (!message) {
        showError('error-message', 'Please write a message before sending.');
        valid = false;
      } else if (message.length < 10) {
        showError('error-message', 'Your message is too short — please give us a bit more detail.');
        valid = false;
      }

      if (!valid) return;

      // Simulate submission (replace with actual fetch/AJAX call)
      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending…';

      setTimeout(function () {
        form.style.display = 'none';
        successBox.classList.add('is-visible');
      }, 1000);
    });

    /* Reset back to blank form */
    resetBtn.addEventListener('click', function () {
      form.reset();
      currentRating = 0;
      updateStars(0);
      ratingInput.value = '';
      charCount.textContent = '0';
      counterEl.classList.remove('is-limit');
      ratingGroup.style.display = 'none';

      tabs.forEach(function (t) {
        t.classList.remove('is-active');
        t.setAttribute('aria-pressed', 'false');
      });
      tabs[0].classList.add('is-active');
      tabs[0].setAttribute('aria-pressed', 'true');
      subjectInput.value = tabs[0].dataset.subject;

      submitBtn.disabled = false;
      submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Message';

      successBox.classList.remove('is-visible');
      form.style.display = '';
    });

  })();
  </script>

</body>
</html>