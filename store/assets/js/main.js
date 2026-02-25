/* ============================================================
   Palians Store — Main JavaScript
   ============================================================ */

(function () {
  'use strict';

  // === Mobile Navigation Toggle ===
  const navToggle = document.getElementById('navToggle');
  const navMenu = document.getElementById('navMenu');
  if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
      navMenu.classList.toggle('open');
      navToggle.classList.toggle('active');
    });
    document.addEventListener('click', (e) => {
      if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
        navMenu.classList.remove('open');
        navToggle.classList.remove('active');
      }
    });
  }

  // === License Price Updater (product.php) ===
  function initLicenseSelector() {
    const radios = document.querySelectorAll('input[name="license_type"]');
    const priceDisplay = document.getElementById('selectedPrice');
    const buyBtn = document.getElementById('buyNowBtn');

    if (!radios.length) return;

    radios.forEach((radio) => {
      radio.addEventListener('change', () => {
        const price = radio.dataset.price;
        const license = radio.value;

        if (priceDisplay) {
          priceDisplay.textContent = '₹' + parseInt(price).toLocaleString('en-IN');
        }

        if (buyBtn) {
          const url = new URL(buyBtn.href);
          url.searchParams.set('license_type', license);
          url.searchParams.set('amount', price);
          buyBtn.href = url.toString();
        }
      });
    });

    // Trigger on first checked
    const checked = document.querySelector('input[name="license_type"]:checked');
    if (checked) checked.dispatchEvent(new Event('change'));
  }

  // === Razorpay Checkout Handler ===
  function initRazorpayCheckout() {
    const form = document.getElementById('checkoutForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (!validateCheckoutForm(form)) return;

      const submitBtn = form.querySelector('[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Processing…';

      const formData = new FormData(form);
      formData.set('ajax', '1');

      try {
        const res = await fetch(form.action, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.error) {
          showAlert(data.error, 'error');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Proceed to Pay';
          return;
        }

        const options = {
          key: data.razorpay_key,
          amount: data.amount,
          currency: 'INR',
          name: 'Palians',
          description: data.product_name,
          order_id: data.razorpay_order_id,
          prefill: {
            name: formData.get('buyer_name'),
            email: formData.get('buyer_email'),
            contact: formData.get('buyer_phone') || '',
          },
          theme: { color: '#3b82f6' },
          handler: function (response) {
            // Submit payment verification
            const verifyForm = document.createElement('form');
            verifyForm.method = 'POST';
            verifyForm.action = data.verify_url;
            const fields = {
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_order_id:   response.razorpay_order_id,
              razorpay_signature:  response.razorpay_signature,
              local_order_id:      data.local_order_id,
              csrf_token:          formData.get('csrf_token'),
            };
            Object.entries(fields).forEach(([k, v]) => {
              const input = document.createElement('input');
              input.type = 'hidden';
              input.name = k;
              input.value = v;
              verifyForm.appendChild(input);
            });
            document.body.appendChild(verifyForm);
            verifyForm.submit();
          },
          modal: {
            ondismiss: function () {
              submitBtn.disabled = false;
              submitBtn.textContent = 'Proceed to Pay';
            },
          },
        };

        const rzp = new Razorpay(options);
        rzp.open();
      } catch (err) {
        showAlert('Something went wrong. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Proceed to Pay';
      }
    });
  }

  // === Form Validation ===
  function validateCheckoutForm(form) {
    let valid = true;

    // Clear previous errors
    form.querySelectorAll('.form-error').forEach((el) => el.remove());
    form.querySelectorAll('.form-control.error').forEach((el) => el.classList.remove('error'));

    const name = form.querySelector('[name="buyer_name"]');
    const email = form.querySelector('[name="buyer_email"]');

    if (name && name.value.trim().length < 2) {
      showFieldError(name, 'Please enter your full name.');
      valid = false;
    }

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      showFieldError(email, 'Please enter a valid email address.');
      valid = false;
    }

    return valid;
  }

  function showFieldError(field, message) {
    field.classList.add('error');
    const err = document.createElement('span');
    err.className = 'form-error';
    err.textContent = message;
    field.parentNode.appendChild(err);
  }

  function showAlert(message, type = 'error') {
    const existing = document.querySelector('.alert.dynamic');
    if (existing) existing.remove();

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} dynamic`;
    alert.innerHTML = `<i class="bi bi-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ${message}`;

    const form = document.getElementById('checkoutForm');
    if (form) form.prepend(alert);

    setTimeout(() => alert.remove(), 6000);
  }

  // === Screenshot Gallery Lightbox ===
  function initGallery() {
    const mainImg = document.getElementById('galleryMain');
    const thumbs = document.querySelectorAll('.gallery-thumb');
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxClose = document.getElementById('lightboxClose');

    if (!mainImg || !thumbs.length) return;

    thumbs.forEach((thumb) => {
      thumb.addEventListener('click', () => {
        const src = thumb.dataset.src;
        mainImg.src = src;
        thumbs.forEach((t) => t.classList.remove('active'));
        thumb.classList.add('active');
      });
    });

    if (mainImg && lightbox && lightboxImg) {
      mainImg.addEventListener('click', () => {
        lightboxImg.src = mainImg.src;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
      });
    }

    if (lightboxClose) {
      lightboxClose.addEventListener('click', closeLightbox);
    }

    if (lightbox) {
      lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
      });
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeLightbox();
    });

    function closeLightbox() {
      if (lightbox) {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
      }
    }
  }

  // === Tab Switcher ===
  function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        tabBtns.forEach((b) => b.classList.remove('active'));
        tabContents.forEach((c) => c.classList.remove('active'));
        btn.classList.add('active');
        const content = document.getElementById('tab-' + target);
        if (content) content.classList.add('active');
      });
    });
  }

  // === Smooth Scroll ===
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener('click', (e) => {
        const target = document.querySelector(anchor.getAttribute('href'));
        if (target) {
          e.preventDefault();
          const navH = 72;
          const top = target.getBoundingClientRect().top + window.scrollY - navH;
          window.scrollTo({ top, behavior: 'smooth' });
        }
      });
    });
  }

  // === Particle Animation (Hero) ===
  function initParticles() {
    const container = document.getElementById('particles');
    if (!container) return;

    const count = 20;
    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 4 + 2;
      p.style.cssText = [
        `width: ${size}px`,
        `height: ${size}px`,
        `left: ${Math.random() * 100}%`,
        `animation-duration: ${Math.random() * 15 + 10}s`,
        `animation-delay: ${Math.random() * 10}s`,
        `opacity: ${Math.random() * 0.5 + 0.1}`,
      ].join(';');
      container.appendChild(p);
    }
  }

  // === Admin: Confirm Delete ===
  function initConfirmActions() {
    document.querySelectorAll('[data-confirm]').forEach((el) => {
      el.addEventListener('click', (e) => {
        if (!confirm(el.dataset.confirm || 'Are you sure?')) {
          e.preventDefault();
        }
      });
    });
  }

  // === Admin: Auto slug from name ===
  function initAutoSlug() {
    const nameField = document.getElementById('productName');
    const slugField = document.getElementById('productSlug');

    if (nameField && slugField) {
      nameField.addEventListener('input', () => {
        if (!slugField.dataset.manual) {
          slugField.value = nameField.value
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s-]+/g, '-')
            .replace(/^-|-$/g, '');
        }
      });
      slugField.addEventListener('input', () => {
        slugField.dataset.manual = '1';
      });
    }
  }

  // === Admin: Modal ===
  function initModals() {
    document.querySelectorAll('[data-modal-open]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const modal = document.getElementById(btn.dataset.modalOpen);
        if (modal) modal.classList.add('active');
      });
    });
    document.querySelectorAll('[data-modal-close], .modal-overlay').forEach((el) => {
      el.addEventListener('click', (e) => {
        if (e.target === el) {
          document.querySelectorAll('.modal-overlay').forEach((m) => m.classList.remove('active'));
        }
      });
    });
    document.querySelectorAll('.modal-close').forEach((btn) => {
      btn.addEventListener('click', () => {
        btn.closest('.modal-overlay').classList.remove('active');
      });
    });
  }

  // === Init All ===
  document.addEventListener('DOMContentLoaded', () => {
    initLicenseSelector();
    initRazorpayCheckout();
    initGallery();
    initTabs();
    initSmoothScroll();
    initParticles();
    initConfirmActions();
    initAutoSlug();
    initModals();
  });
})();
