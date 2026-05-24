<?php
// policies.php — Bite-Me-Donut Store Policies Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Our Policies — Bite-Me Donuts</title>
  <link rel="stylesheet" href="../../public/assets/css/root.css" />
  <link rel="stylesheet" href="../../public/assets/css/policies.css" />
</head>
<body>

  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <!-- ======================================================
       HERO
  ====================================================== -->
  <section class="policies-hero">
    <div class="container">
      <span class="policies-hero__eyebrow">Transparency &amp; Trust</span>
      <h1 class="policies-hero__title">Our Policies</h1>
      <p class="policies-hero__subtitle">
        Honest, simple, and sweet — just like our donuts.<br>
        Everything you need to know about how we handle your orders, privacy, and more.
      </p>
    </div>
  </section>

  <!-- ======================================================
       MAIN PAGE BODY
  ====================================================== -->
  <main class="section">
    <div class="container">

      <!-- Last updated bar -->
      <!-- <div class="policies-updated-bar">
        <svg class="policies-updated-bar__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <span>Last updated: <strong>May 2025</strong> &mdash; These policies apply to all orders placed via our website and in-store.</span>
      </div> -->

      <!-- 2-column layout: Quick Nav (left) + Main content (right) -->
      <div class="policies-layout">

        <!-- ================================================
             LEFT: QUICK NAVIGATION
        ================================================ -->
        <nav class="policies-toc" aria-label="Policy sections">
          <div class="policies-toc__header">
            <p class="policies-toc__title">Quick Navigation</p>
          </div>
          <ul class="policies-toc__list" role="list">
            <li class="policies-toc__item">
              <span class="policies-toc__num">01</span>
              <a class="policies-toc__link" href="#ordering">Ordering &amp; Payment</a>
            </li>
            <li class="policies-toc__item">
              <span class="policies-toc__num">02</span>
              <a class="policies-toc__link" href="#delivery">Delivery &amp; Pickup</a>
            </li>
            <li class="policies-toc__item">
              <span class="policies-toc__num">03</span>
              <a class="policies-toc__link" href="#freshness">Freshness Guarantee</a>
            </li>
            <div class="policies-toc__divider"></div>
            <li class="policies-toc__item">
              <span class="policies-toc__num">04</span>
              <a class="policies-toc__link" href="#refunds">Returns &amp; Refunds</a>
            </li>
            <li class="policies-toc__item">
              <span class="policies-toc__num">05</span>
              <a class="policies-toc__link" href="#allergies">Allergies &amp; Dietary</a>
            </li>
            <li class="policies-toc__item">
              <span class="policies-toc__num">06</span>
              <a class="policies-toc__link" href="#custom-orders">Custom Orders</a>
            </li>
            <div class="policies-toc__divider"></div>
            <li class="policies-toc__item">
              <span class="policies-toc__num">07</span>
              <a class="policies-toc__link" href="#privacy">Privacy Policy</a>
            </li>
            <li class="policies-toc__item">
              <span class="policies-toc__num">08</span>
              <a class="policies-toc__link" href="#contact">Contact Us</a>
            </li>
          </ul>
        </nav>

        <!-- ================================================
             RIGHT: MAIN CONTENT
        ================================================ -->
        <div class="policies-main">

          <!-- 01. Ordering & Payment -->
          <section class="policy-section" id="ordering">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Shopping cart icon -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                  <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 01</span>
                <h2 class="policy-section__title">Ordering &amp; Payment</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                We want ordering your donuts to be as delightful as eating them. Orders placed online are confirmed instantly via email. Please double-check your order before submitting — we start preparing batches shortly after confirmation.
              </p>

              <div class="policy-items">
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Credit card -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Accepted Payment Methods</p>
                    <p class="policy-item__desc">We accept Visa, Mastercard, domestic ATM cards, and cash on delivery (COD). All online transactions are processed securely.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Clock -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Order Cut-off Times</p>
                    <p class="policy-item__desc">Same-day orders must be placed before <strong>10:00 AM</strong>. Orders placed after this time will be scheduled for the next available day.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Edit / pencil -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Order Modifications</p>
                    <p class="policy-item__desc">Changes to an existing order may be requested within <strong>30 minutes</strong> of placing it. After that, we cannot guarantee changes as baking may have already begun.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Package / box -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                      <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Minimum Order</p>
                    <p class="policy-item__desc">There is no minimum order for in-store pickup. For delivery, a minimum of <strong>6 donuts</strong> is required to ensure freshness during transport.</p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- 02. Delivery & Pickup -->
          <section class="policy-section" id="delivery">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Truck -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                  <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 02</span>
                <h2 class="policy-section__title">Delivery &amp; Pickup</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                Whether you prefer to swing by the shop or have us bring the sweetness to your door, we've got you covered. We currently offer in-store pickup and local delivery within our service area.
              </p>

              <div class="policy-items">
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Store / shop -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">In-Store Pickup</p>
                    <p class="policy-item__desc">Ready within <strong>2–3 hours</strong> of ordering (subject to current demand). You'll receive a notification when your box is ready.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Map pin -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Delivery Area</p>
                    <p class="policy-item__desc">We deliver within a <strong>10 km radius</strong> of our store. Enter your address at checkout — we'll confirm availability before you pay.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Clock -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Delivery Timeframe</p>
                    <p class="policy-item__desc">Delivery slots are available between <strong>9:00 AM – 6:00 PM</strong>, Monday through Sunday. Estimated delivery time is 45–90 minutes depending on distance and order volume.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Dollar sign / tag -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Delivery Fee</p>
                    <p class="policy-item__desc">A flat delivery fee of <strong>15,000 VND</strong> applies. Free delivery on orders of 12 donuts or more!</p>
                  </div>
                </div>
              </div>

              <div class="policy-highlight">
                <svg class="policy-highlight__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
                <p class="policy-highlight__text">
                  We package every order in our signature pink box with cushioned inserts — so your donuts arrive looking as gorgeous as they taste. No squished glazes, ever!
                </p>
              </div>
            </div>
          </section>

          <!-- 03. Freshness Guarantee -->
          <section class="policy-section" id="freshness">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Star / sparkle -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 03</span>
                <h2 class="policy-section__title">Freshness Guarantee</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                All our donuts are made fresh daily using real ingredients — no preservatives, no shortcuts, and absolutely no day-old stock. We bake in small batches to ensure every piece meets our quality standard.
              </p>
              <p>
                <strong>Best consumed on the day of purchase.</strong> Donuts can be stored at room temperature for up to 24 hours in an airtight container. We do not recommend refrigerating — it dries out the dough and dulls the glaze.
              </p>

              <ul class="policy-list">
                <li>Cream-filled donuts must be consumed within <strong>12 hours</strong> and kept cool.</li>
                <li>Glazed and sprinkled donuts stay fresh for up to <strong>24 hours</strong> at room temperature.</li>
                <li>Specialty donuts (matcha, ube, seasonal flavors) may vary — check the product page for specific guidance.</li>
                <li>We do not sell or deliver any donut baked more than 6 hours prior to the delivery time.</li>
              </ul>

              <div class="policy-highlight">
                <svg class="policy-highlight__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <p class="policy-highlight__text">
                  Our Freshness Promise: If a donut doesn't meet your quality expectations upon arrival, contact us within 2 hours and we'll make it right — no questions asked.
                </p>
              </div>
            </div>
          </section>

          <!-- 04. Returns & Refunds -->
          <section class="policy-section" id="refunds">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Refresh / return arrows -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
                  <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 04</span>
                <h2 class="policy-section__title">Returns &amp; Refunds</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                Because our products are fresh food, we are unable to accept returns once an order has been picked up or delivered. However, your satisfaction is our top priority — here's how we handle issues:
              </p>

              <div class="policy-items">
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Alert circle -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Wrong or Missing Items</p>
                    <p class="policy-item__desc">Contact us within <strong>2 hours</strong> of delivery with a photo. We'll issue a replacement order or store credit promptly.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Shield check -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Quality Issues</p>
                    <p class="policy-item__desc">If your donut arrived damaged or did not meet quality standards, send us a photo within 2 hours. We'll review and offer a full replacement or refund.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- X circle -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Order Cancellations</p>
                    <p class="policy-item__desc">Cancellations are accepted within <strong>30 minutes</strong> of placing the order for a full refund. After that, orders are already in production and cannot be refunded.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Bank / building -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="3" y1="22" x2="21" y2="22"/><line x1="6" y1="18" x2="6" y2="11"/>
                      <line x1="10" y1="18" x2="10" y2="11"/><line x1="14" y1="18" x2="14" y2="11"/>
                      <line x1="18" y1="18" x2="18" y2="11"/><polygon points="12 2 20 7 4 7"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Refund Processing</p>
                    <p class="policy-item__desc">Approved refunds are issued within <strong>3–5 business days</strong> back to your original payment method, or instantly as store credit if preferred.</p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- 05. Allergies & Dietary -->
          <section class="policy-section" id="allergies">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Leaf -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M2 22c1.25-.987 2.27-1.975 3.9-2.2a5.56 5.56 0 0 1 3.8 1.5 4 4 0 0 0 6.187-2.353 3.5 3.5 0 0 0 3.69-5.116A3.5 3.5 0 0 0 20.95 8 3.5 3.5 0 1 0 16 3.05a3.5 3.5 0 0 0-5.831 1.373 3.5 3.5 0 0 0-5.116 3.69 4 4 0 0 0-2.348 6.155C3.499 15.42 4 17.002 4 19"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 05</span>
                <h2 class="policy-section__title">Allergies &amp; Dietary Info</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                We take food allergies seriously. All our donuts are produced in a shared kitchen environment that handles <strong>gluten, eggs, dairy, tree nuts, and soy</strong>. Cross-contamination is possible.
              </p>
              <p>
                We label each product with common allergens on our website and in-store menus. If you have a severe allergy, please contact us before ordering so we can advise on the safest options.
              </p>

              <ul class="policy-list">
                <li>Products marked <strong>Vegan</strong> contain no animal-derived ingredients but are made in a shared kitchen.</li>
                <li>Products marked <strong>Gluten-Friendly</strong> use gluten-free flour, but we cannot guarantee zero cross-contact.</li>
                <li>Sugar-reduced options are available — see our menu for details.</li>
                <li>We do not use artificial dyes; all colors come from natural sources (beet, matcha, turmeric, etc.).</li>
              </ul>

              <div class="policy-highlight">
                <svg class="policy-highlight__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <p class="policy-highlight__text">
                  If you or someone in your household has a life-threatening allergy, please speak with our team directly at hello@bitemedonuts.com before placing an order.
                </p>
              </div>
            </div>
          </section>

          <!-- 06. Custom Orders -->
          <section class="policy-section" id="custom-orders">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Sliders / customize -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/>
                  <line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/>
                  <line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/>
                  <line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 06</span>
                <h2 class="policy-section__title">Custom Orders</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                Planning a birthday, wedding, or office party? We love creating custom donut boxes and towers! Here's everything you need to know before placing a custom order:
              </p>

              <div class="policy-items">
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Calendar -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                      <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Lead Time</p>
                    <p class="policy-item__desc">Custom orders require a minimum of <strong>72 hours</strong> advance notice. For large events (50+ donuts), please give us at least <strong>5 business days</strong>.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Message circle -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Consultation</p>
                    <p class="policy-item__desc">We offer a free 15-minute consultation via chat or phone to nail down flavors, designs, box styles, and quantities before quoting.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Percent / deposit -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Deposit</p>
                    <p class="policy-item__desc">A <strong>50% non-refundable deposit</strong> is required to confirm a custom order. The remaining balance is due upon delivery or pickup.</p>
                  </div>
                </div>
                <div class="policy-item">
                  <div class="policy-item__icon" aria-hidden="true">
                    <!-- Refresh / changes -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
                      <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                    </svg>
                  </div>
                  <div class="policy-item__content">
                    <p class="policy-item__title">Design Changes</p>
                    <p class="policy-item__desc">Design adjustments can be made up to <strong>48 hours</strong> before the scheduled pickup/delivery time at no extra charge. Changes requested after that may incur a revision fee.</p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- 07. Privacy Policy -->
          <section class="policy-section" id="privacy">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Lock -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 07</span>
                <h2 class="policy-section__title">Privacy Policy</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                Your privacy matters to us. We collect only the information necessary to process your orders and improve your experience. We will never sell, rent, or share your personal data with third parties for marketing purposes.
              </p>

              <p><strong>Information we collect:</strong></p>
              <ul class="policy-list">
                <li>Name, phone number, and email address for order communication.</li>
                <li>Delivery address to fulfill local delivery orders.</li>
                <li>Order history to help you reorder your favorites quickly.</li>
                <li>Payment details are processed securely by our payment provider — we do not store card numbers on our servers.</li>
              </ul>

              <p><strong>How we use your data:</strong></p>
              <ul class="policy-list">
                <li>To process and deliver your orders.</li>
                <li>To send order confirmations and status updates.</li>
                <li>To notify you of promotions and new flavors (only if you opt in).</li>
                <li>To improve our website and service based on usage patterns (anonymized analytics only).</li>
              </ul>

              <div class="policy-highlight">
                <svg class="policy-highlight__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <p class="policy-highlight__text">
                  You may request to access, correct, or delete your personal data at any time by emailing us at privacy@bitemedonuts.com. We'll respond within 5 business days.
                </p>
              </div>

              <p>
                We use cookies on this website to maintain your session and remember your cart. You can disable cookies in your browser settings, though some features may not function correctly.
              </p>
            </div>
          </section>

          <!-- 08. Contact -->
          <section class="policy-section" id="contact">
            <div class="policy-section__header">
              <div class="policy-section__icon" aria-hidden="true">
                <!-- Mail -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </div>
              <div>
                <span class="policy-section__number">Policy 08</span>
                <h2 class="policy-section__title">Contact Us</h2>
              </div>
            </div>
            <div class="policy-section__body">
              <p>
                Still have questions? Our team is friendly, responsive, and genuinely happy to help. Reach us through any of the channels below — we typically reply within a few hours during business hours.
              </p>
              <p>
                <strong>Business hours:</strong> Monday – Sunday, 8:00 AM – 7:00 PM (GMT+7)
              </p>
            </div>
          </section>

          <!-- Bottom CTA -->
          <div class="policies-cta">
            <h2 class="policies-cta__title">Ready to Order?</h2>
            <p class="policies-cta__desc">Now that you know how we roll — let's get you some donuts.</p>
            <a href="../../views/user/products.php" class="btn btn--outline btn--lg">Shop Our Menu</a>
          </div>

        </div><!-- /.policies-main -->

      </div><!-- /.policies-layout -->

    </div><!-- /.container -->
  </main>

  <?php include __DIR__ . '/../layouts/footer.php'; ?>

</body>
</html>
