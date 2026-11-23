@php
  $related = collect(\App\Support\ProductCatalog::products())->where('slug', '!=', $product['slug'])->shuffle()->take(3)->values();
@endphp
<header class="site-header"><div class="container nav">
  <a class="brand" href="{{ route('digital.index') }}" aria-label="Nurture Calls home"><span class="brand-mark">N</span><span class="brand-name">Nurture calls</span></a>
  <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open navigation menu" aria-expanded="false">☰</button>
  <nav class="nav-links" id="navLinks" aria-label="Main navigation"><a href="{{ route('digital.index') }}">Home</a><a href="{{ route('digital.index') }}#about">About us</a><div class="nav-product"><button type="button" id="productToggle" aria-expanded="false">Product <span>⌄</span></button><div class="product-menu" id="productMenu">@foreach (\App\Support\ProductCatalog::products() as $p)<a href="{{ route('digital.checkout', $p['slug']) }}">{{ $p['name'] }}</a>@endforeach</div></div><a href="{{ route('digital.index') }}#services">Devices</a><a href="{{ route('digital.index') }}#contact">Contact us</a></nav>
  <button class="nav-cta" type="button" data-open-order>Get Now <span class="download-icon">↓</span><span class="cart-counter" id="cartCounter">0</span></button>
</div></header>
<main>
  <section class="product-page"><div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('digital.index') }}">Home</a><span>›</span><a href="{{ route('digital.index') }}#services">Product</a><span>›</span><strong>{{ $product['name'] }}</strong></nav>
    <div class="product-layout">
      <div class="gallery">
	  <div class="thumbs">

        @foreach (['image_2_url','image_3_url','image_4_url'] as $k => $key) 
			@if (!empty($product[$key]))
				<button class="thumb @if(!$k) is-active @endif" type="button" data-image="{{ $product[$key] }}" aria-label="Product preview"><img src="{{ $product[$key] }}" alt=""></button>
			@else
				@if(!$k) 
					<button class="thumb" type="button" data-image="" aria-label="Product"><span>PDF</span></button>
				@endif
			@endif 
		@endforeach
      </div>
	  
	  <div class="main-image">
		@if ($product['image'])<img id="mainProductImage" src="{{ $product['image'] }}" alt="{{ $product['image_alt'] ?? $product['name'] }}">@else<div class="image-placeholder">PDF GUIDE</div>@endif
        @if (!empty($product['discount']))<span class="sale-tag">{{ rtrim(rtrim(number_format($product['discount'],2),'0'),'.') }}% Off</span>@endif
      </div>
	  </div>
      <div class="product-info"><h1>{{ $product['name'] }}</h1>
        <div class="rating"><span class="stars" aria-label="5 out of 5 stars">★★★★★</span><span>5.0 (20 Reviews)</span></div>
        <div class="price-line"><strong>{{ $product['formatted_price'] }}</strong>@if (!empty($product['discount']))<span class="old-price">₦{{ $product['actual_price']}}</span>@endif</div>
        <hr class="rule">
        @if ($product['description'])<p class="description">{{ $product['description'] }}</p>@elseif($product['tagline'])<p class="description">{{ $product['tagline'] }}</p>@else<p class="description">Explore this practical digital business guide from Nurture Calls and learn the skills, tools, and steps to get started.</p>@endif
        @if ($product['tagline'] && $product['description'])<p class="product-tagline">{{ $product['tagline'] }}</p>@endif

		@if ($product['features'] !== [])
			<br/>
			@foreach ($product['features'] as $feature)
				<div class="meta-line">✔️ &nbsp;{{ $feature }}</div>
			@endforeach
		@endif
        <div class="purchase-row"><div class="quantity" aria-label="Quantity"><button type="button" data-quantity="-1" aria-label="Decrease quantity">−</button><output id="quantityValue">1</output><button type="button" data-quantity="1" aria-label="Increase quantity">+</button></div><button class="cart-button" type="button" id="addToCart">Add to Cart</button><button class="wishlist-button" type="button" id="wishlist" aria-label="Add to wishlist">♡</button></div>
        <div class="order-box" id="order-box"><button type="button" class="btn order-toggle" data-open-order aria-haspopup="dialog" aria-controls="orderDialog">Get a Copy Now <span aria-hidden="true">→</span></button></div>
        <div class="payment-box"><div class="payment-logos"><span>VISA</span><span>●●</span><span>G Pay</span><span>AMEX</span><span>PayPal</span></div><p>Guaranteed Safe Checkout</p></div>
        <ul class="product-benefits"><li>↯ <span>Fast Download</span></li><li>♧ <span>One-Time Payment | Instant PDF Download</span></li></ul>
        <div class="share"><span>Share</span><div><button type="button" aria-label="Share on Facebook">f</button><button type="button" aria-label="Share on X">♥</button><button type="button" aria-label="Share on LinkedIn">in</button></div></div>
      </div>
    </div>
  </div></section>
  <div class="order-backdrop @if ($errors->any()) is-open @endif" id="orderDialog" role="dialog" aria-modal="true" aria-labelledby="orderTitle" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}" tabindex="-1">
    <section class="order-panel"><button class="close-order" type="button" aria-label="Close order form">×</button><h2 id="orderTitle">Complete your order</h2><p>Enter your details so we can send your digital guide.</p>
      <form method="POST" action="{{ route('digital.start', $product['slug']) }}" id="order-form">@csrf<input type="hidden" name="quantity" id="orderQuantity" value="1">
        <label for="full-name">Full name</label><input id="full-name" type="text" name="full_name" value="{{ old('full_name') }}" autocomplete="name" required placeholder="Your full name">
        @error('full_name')<span class="field-error">{{ $message }}</span>@enderror
        <label for="phone">Phone number</label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" required placeholder="+234 800 000 0000">
        @error('phone')<span class="field-error">{{ $message }}</span>@enderror
        <label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@example.com">
        @error('email')<span class="field-error">{{ $message }}</span>@enderror

        <fieldset style="margin:18px 0 4px;padding:0;border:0;">
          <legend style="margin-bottom:10px;font-size:14px;font-weight:700;color:#242529;">Choose a payment provider <span style="color:#c53b43;">*</span></legend>
          <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <label style="flex:1 1 145px;display:flex;align-items:center;gap:10px;min-height:52px;margin:0;padding:12px 14px;border:1px solid #d6d8db;border-radius:12px;background:#fff;color:#242529;font-size:14px;font-weight:600;cursor:pointer;">
              <input type="radio" name="provider" value="paystack" disabled required @checked(old('provider') === 'paystack') style="width:18px;height:18px;margin:0;accent-color:#216bc0;">
              <span>Paystack<br><small>locked</small></span>
              <span aria-hidden="true" style="margin-left:auto;width:28px;height:28px;display:grid;place-items:center;border-radius:50%;background:#fff;color:#fff;font-size:16px;font-weight:800;">🔒</span>
            </label>
            <label style="flex:1 1 145px;display:flex;align-items:center;gap:10px;min-height:52px;margin:0;padding:12px 14px;border:1px solid #d6d8db;border-radius:12px;background:#fff;color:#242529;font-size:14px;font-weight:600;cursor:pointer;">
              <input type="radio" name="provider" value="korapay" required @checked(old('provider') === 'korapay') style="width:18px;height:18px;margin:0;accent-color:#216bc0;">
              <span>KoraPay</span>
              <span aria-hidden="true" style="margin-left:auto;width:28px;height:28px;display:grid;place-items:center;border-radius:50%;background:#fff;color:#fff;font-size:15px;font-weight:800;">💶</span>
            </label>
          </div>
          @error('provider')<span class="field-error" style="display:block;margin-top:7px;">{{ $message }}</span>@enderror
        </fieldset>

        <div class="order-summary"><span>{{ $product['name'] }}<small>Qty <b id="summaryQuantity">1</b> · One-time payment · Instant PDF</small></span><strong>{{ $product['formatted_price'] }}</strong></div>
        <button type="submit" class="btn place-order">Place order</button><p class="form-note">After successful payment, your PDF guide will be sent to the email address you provided.</p>
      </form>
    </section>
  </div>
  @if ($related->isNotEmpty())<section class="related"><div class="container"><h2>Related Product For you</h2><div class="related-grid">
    @foreach ($related as $item)
      <a class="related-card" href="{{ route('digital.checkout', $item['slug']) }}">
		@if ($item['image'])
		  <img src="{{ $item['image'] }}" alt="{{ $item['image_alt'] }}" loading="lazy" >
		@else
		  <div style="width:100%;height:100%;display:grid;place-items:center;color:#858991;"><i class="ri-file-download-line" style="font-size:40px;"></i></div>
		@endif
		<h3>{{ $item['name'] }}</h3>
		<p>{!! $item['tagline'] !!}</p>
		<strong>{{ $item['formatted_price'] }}</strong>
		@if (!empty($item['discount']))<span class="old-price">₦{{ $item['actual_price']}}</span>@endif
    @endforeach
  </div><a class="btn view-all" href="{{ route('digital.index') }}#services">View All</a></div></section>@endif
  <section class="connection" id="contact"><div class="container"><div class="connection-head"><span class="pill">One-Time Payment · Instant PDF Download</span><h2>Your next digital business could start here!</h2><p>Learn how these businesses work, how to find potential clients, and how to get started.</p></div>
    <div class="social-orbit" aria-label="Connect with Nurture Calls on social media"><svg class="orbit-lines" viewBox="0 0 900 255" preserveAspectRatio="none" aria-hidden="true"><g fill="none" stroke="#284562" stroke-width="2"><path d="M18 72 H90 Q100 72 100 82 V110 H250 Q260 110 260 100 V48 H350"/><path d="M18 183 H90 Q100 183 100 173 V145 H250 Q260 145 260 155 V207 H350"/><path d="M882 72 H810 Q800 72 800 82 V110 H650 Q640 110 640 100 V48 H550"/><path d="M882 183 H810 Q800 183 800 173 V145 H650 Q640 145 640 155 V207 H550"/></g></svg><span class="social telegram">➤</span><span class="social facebook">f</span><span class="social whatsapp">◉</span><div class="hub"><div class="hub-inner">✦</div></div><span class="social youtube">▶</span><span class="social instagram">◎</span><span class="social tiktok">♪</span></div>
    <div class="footer-bottom"><span>© {{ date('Y') }} Nurture Calls. All rights reserved.</span><a href="{{ route('digital.index') }}">Back to all guides</a></div>
  </div></section>
</main><div class="toast" id="toast" role="status" aria-live="polite"></div>
