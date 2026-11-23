<header class="site-header">
  <div class="container nav">
    <a class="brand" href="#home" aria-label="Nurture Calls home"><span class="brand-mark">N</span><span class="brand-name">Nurture calls</span></a>
    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open navigation menu" aria-expanded="false">☰</button>
    <nav class="nav-links" id="navLinks" aria-label="Main navigation">
      <a href="#home">Home</a><a href="#about">About us</a>
      <div class="nav-product"><button type="button" id="productToggle" aria-expanded="false">Product <span aria-hidden="true">⌄</span></button>
        <div class="product-menu" id="productMenu">@foreach ($products as $p)<a href="{{ route('digital.checkout', $p['slug']) }}">{{ $p['name'] }}</a>@endforeach</div>
      </div>
      <a href="#services">Devices</a><a href="#contact">Contact us</a>
    </nav>
    <a class="nav-cta" href="#services">Get Now <span class="download-icon">↓</span></a>
  </div>
</header>
<main>
  <section class="hero" id="home"><div class="container"><div class="hero-content">
    <span class="eyebrow">Your digital business starts here</span>
    <h1>The Digital Communication Business Guide on <span>Nurture calls</span></h1>
    <p>Turn your communication skills into valuable business opportunities. Connect with potential clients, build strong professional relationships, share your ideas confidently, and unlock new ways to grow your network, increase your income, and achieve success.</p>
    <div class="hero-actions"><a class="btn" href="{{ isset($products[0]) ? route('digital.checkout', $products[0]['slug']) : '#services' }}">Get your copy now <span>→</span></a><a class="text-link" href="#services">Explore the guides <span>↓</span></a></div>
  </div></div></section>
  <section class="section" id="services"><div class="container">
    <div class="section-intro" id="about"><span class="pill">Discover our Digital Business</span><h2 class="section-heading">Discover our Practical on <span>Nurture calls</span></h2><p>Discover 7 practical digital business opportunities you can explore using communication, technology, and the internet.</p></div>
    <div class="services-grid">
      @foreach ($products as $item)
        @php
          $name = strtolower($item['name']);
          $imageUrl = ($item['image'] ?? null);
        @endphp
        <a class="service-card" href="{{ route('digital.checkout', $item['slug']) }}">
          <div class="service-image-wrap">@if ($imageUrl)<img class="service-image" src="{{ $imageUrl }}" alt="{{ $item['image_alt'] ?? $item['name'] }}" loading="lazy">@else<div class="image-placeholder" aria-hidden="true">✦</div>@endif</div>
          <h3>{{ $item['name'] }}</h3><p class="service-meta">{!! $item['tagline'] !!}</p>
          <div class="price-row"><span class="price">{{ $item['formatted_price'] }}</span>
		  @if (!empty($item['discount']))<span class="old-price">₦{{ $item['actual_price']}}</span>@endif
		  </div>
        </a>
      @endforeach
    </div>
  </div></section>
  <section class="connection" id="contact"><div class="container">
    <div class="connection-head"><span class="pill">One-Time Payment · Instant PDF Download</span><h2>Your next digital business could start here!</h2><p>Learn how these businesses work, how to find potential clients, and how to get started.</p></div>
    <div class="social-orbit" aria-label="Connect with Nurture Calls on social media"><svg class="orbit-lines" viewBox="0 0 900 255" preserveAspectRatio="none" aria-hidden="true"><g fill="none" stroke="#284562" stroke-width="2"><path d="M18 72 H90 Q100 72 100 82 V110 H250 Q260 110 260 100 V48 H350"/><path d="M18 183 H90 Q100 183 100 173 V145 H250 Q260 145 260 155 V207 H350"/><path d="M882 72 H810 Q800 72 800 82 V110 H650 Q640 110 640 100 V48 H550"/><path d="M882 183 H810 Q800 183 800 173 V145 H650 Q640 145 640 155 V207 H550"/></g><g stroke="#ffd35c" stroke-width="2" fill="none" stroke-linecap="round"><path d="M100 95 V124"/><path d="M260 108 H302"/><path d="M640 147 V177"/><path d="M800 132 V161"/></g></svg>
      <a class="social telegram" href="https://t.me/" aria-label="Telegram">➤</a><a class="social facebook" href="https://facebook.com/" aria-label="Facebook">f</a><a class="social whatsapp" href="https://whatsapp.com/" aria-label="WhatsApp">◉</a><div class="hub"><div class="hub-inner">✦</div></div><a class="social youtube" href="https://youtube.com/" aria-label="YouTube">▶</a><a class="social instagram" href="https://instagram.com/" aria-label="Instagram">◎</a><a class="social tiktok" href="https://tiktok.com/" aria-label="TikTok">♪</a>
    </div><div class="footer-bottom"><span>© {{ date('Y') }} Nurture Calls. All rights reserved.</span><div class="footer-links"><a href="#about">About us</a><a href="#services">Our guides</a><a href="#contact">Contact</a></div><span>Built for your next opportunity.</span></div>
  </div></section>
</main><div class="toast" id="toast" role="status" aria-live="polite"></div>
