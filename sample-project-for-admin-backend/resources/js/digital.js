document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.querySelector('#menuToggle');
  const navLinks = document.querySelector('#navLinks');
  const productToggle = document.querySelector('#productToggle');
  const productMenu = document.querySelector('#productMenu');
  const toast = document.querySelector('#toast');
  let toastTimer;

  menuToggle?.addEventListener('click', () => {
    const isOpen = navLinks?.classList.toggle('open') ?? false;
    menuToggle.setAttribute('aria-expanded', String(isOpen));
    menuToggle.textContent = isOpen ? '×' : '☰';
  });

  productToggle?.addEventListener('click', () => {
    const isOpen = productMenu?.classList.toggle('open') ?? false;
    productToggle.setAttribute('aria-expanded', String(isOpen));
  });

  navLinks?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
    navLinks.classList.remove('open');
    menuToggle?.setAttribute('aria-expanded', 'false');
    if (menuToggle) menuToggle.textContent = '☰';
    productMenu?.classList.remove('open');
    productToggle?.setAttribute('aria-expanded', 'false');
  }));

  const notify = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2800);
  };

  document.querySelectorAll('.thumb[data-image]').forEach((thumb) => thumb.addEventListener('click', () => {
    const mainImage = document.querySelector('#mainProductImage');
    if (mainImage) mainImage.src = thumb.dataset.image;
    document.querySelectorAll('.thumb').forEach((item) => item.classList.toggle('is-active', item === thumb));
  }));

  let quantity = 1;
  const quantityOutput = document.querySelector('#quantityValue');
  const quantityInput = document.querySelector('#orderQuantity');
  const summaryQuantity = document.querySelector('#summaryQuantity');
  document.querySelectorAll('[data-quantity]').forEach((button) => button.addEventListener('click', () => {
    quantity = Math.max(1, Math.min(10, quantity + Number(button.dataset.quantity)));
    if (quantityOutput) quantityOutput.textContent = String(quantity);
    if (quantityInput) quantityInput.value = String(quantity);
    if (summaryQuantity) summaryQuantity.textContent = String(quantity);
  }));

  const cartBadge = document.querySelector('#cartCounter');
  let cartCount = Number(sessionStorage.getItem('nurtureDigitalCart') || 0);
  if (cartBadge) cartBadge.textContent = String(cartCount);
  document.querySelector('#addToCart')?.addEventListener('click', () => {
    cartCount += quantity;
    sessionStorage.setItem('nurtureDigitalCart', String(cartCount));
    if (cartBadge) cartBadge.textContent = String(cartCount);
    notify(`${quantity} guide${quantity === 1 ? '' : 's'} added to your cart.`);
  });

  document.querySelector('#wishlist')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const liked = button.classList.toggle('is-liked');
    button.textContent = liked ? '♥' : '♡';
    notify(liked ? 'Added to your wishlist.' : 'Removed from your wishlist.');
  });

  const orderDialog = document.querySelector('#orderDialog');
  let lastOrderTrigger = null;
  const openOrderDialog = (event) => {
    if (!orderDialog) return;
    lastOrderTrigger = event.currentTarget;
    orderDialog.classList.add('is-open');
    orderDialog.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    window.setTimeout(() => document.querySelector('#full-name')?.focus(), 40);
  };
  const closeOrderDialog = () => {
    if (!orderDialog) return;
    orderDialog.classList.remove('is-open');
    orderDialog.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    lastOrderTrigger?.focus();
  };
  if (orderDialog?.classList.contains('is-open')) {
    document.body.style.overflow = 'hidden';
    window.setTimeout(() => document.querySelector('#full-name')?.focus(), 40);
  }
  document.querySelectorAll('[data-open-order]').forEach((button) => button.addEventListener('click', openOrderDialog));
  document.querySelector('.close-order')?.addEventListener('click', closeOrderDialog);
  orderDialog?.addEventListener('click', (event) => {
    if (event.target === orderDialog) closeOrderDialog();
  });
  document.addEventListener('keydown', (event) => {
    if (!orderDialog?.classList.contains('is-open')) return;
    if (event.key === 'Escape') closeOrderDialog();
    if (event.key === 'Tab') {
      const focusable = [...orderDialog.querySelectorAll('button, input, a[href], [tabindex]:not([tabindex="-1"])')].filter((node) => !node.disabled);
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
  });

  document.querySelectorAll('.share button').forEach((button) => button.addEventListener('click', async () => {
    const platform = button.getAttribute('aria-label')?.replace('Share on ', '') || 'your network';
    if (navigator.share) {
      try { await navigator.share({ title: document.title, url: window.location.href }); } catch (_) { /* User cancelled share sheet. */ }
    } else if (navigator.clipboard) {
      await navigator.clipboard.writeText(window.location.href);
      notify(`Product link copied to share on ${platform}.`);
    }
  }));
});
