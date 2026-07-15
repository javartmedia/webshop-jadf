<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $metaDescription ?? 'Wenshop Hot Wheels - Premium Hot Wheels Collectibles Store. Jual koleksi Hot Wheels langka, limited edition, dan collector edition.' }}">
    <meta name="keywords" content="hot wheels, koleksi, diecast, mobil miniatur, limited edition, collector edition">
    <title>{{ $metaTitle ?? 'Wenshop Hot Wheels - Premium Collectibles Store' }}</title>
    <link rel="icon" href="{{ $favicon ?? asset('favicon.ico') }}" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Header / Navbar -->
    <header x-data="{ mobileMenuOpen: false, searchOpen: false }" class="bg-white shadow-sm sticky top-0 z-50">
        <!-- Top Bar -->
        <div class="bg-red-600 text-white text-sm py-1.5">
            <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
                <span><i class="fas fa-truck mr-1"></i> Free shipping untuk orders di atas Rp 500.000</span>
                <span><i class="fas fa-phone mr-1"></i> {{ $sitePhone ?? '+62 812-3456-7890' }}</span>
            </div>
        </div>

        <!-- Main Nav -->
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="{{ route('home') }}" class="flex-shrink-0">
                    <img src="{{ $siteLogo ?? asset('images/logo.png') }}" alt="Wenshop Hot Wheels" class="h-10">
                </a>

                <!-- Desktop Navigation -->
                <nav class="hidden lg:flex space-x-8">
                    <a href="{{ route('home') }}" class="text-gray-900 hover:text-red-600 font-medium">Home</a>
                    <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-red-600">Products</a>
                    <a href="{{ route('flash-sale') }}" class="text-gray-700 hover:text-red-600">Flash Sale</a>
                    <a href="{{ route('categories.show', 'hot-wheels-premium') }}" class="text-gray-700 hover:text-red-600">Premium</a>
                    <a href="{{ route('categories.show', 'collector-edition') }}" class="text-gray-700 hover:text-red-600">Collector Edition</a>
                    <a href="{{ route('contact') }}" class="text-gray-700 hover:text-red-600">Contact</a>
                </nav>

                <!-- Right Icons -->
                <div class="flex items-center space-x-4">
                    <!-- Search -->
                    <button @click="searchOpen = !searchOpen" class="text-gray-600 hover:text-red-600">
                        <i class="fas fa-search text-lg"></i>
                    </button>

                    <!-- Wishlist -->
                    <a href="/wishlist" class="text-gray-600 hover:text-red-600 relative">
                        <i class="fas fa-heart text-lg"></i>
                        <span id="wishlist-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </a>

                    <!-- Cart -->
                    <a href="/cart" class="text-gray-600 hover:text-red-600 relative">
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </a>

                    <!-- User Menu -->
                    @auth
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center text-gray-700 hover:text-red-600">
                            <i class="fas fa-user text-lg"></i>
                            <span class="ml-1 hidden sm:inline">{{ Auth::user()->name }}</span>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-50">
                            <a href="/orders" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Orders</a>
                            <a href="/profile" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="/wishlist" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Wishlist</a>
                            @if(Auth::user()->isAdmin())
                            <hr class="my-1">
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-red-600 hover:bg-gray-100">Admin Panel</a>
                            @endif
                            <hr class="my-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</button>
                            </form>
                        </div>
                    </div>
                    @else
                    <a href="{{ route('login') }}" class="text-gray-700 hover:text-red-600">
                        <i class="fas fa-sign-in-alt text-lg"></i>
                    </a>
                    @endauth

                    <!-- Mobile Menu Toggle -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden text-gray-600">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Search Bar (Expandable) -->
            <div x-show="searchOpen" @click.away="searchOpen = false" class="pb-4">
                <form action="{{ route('search') }}" method="GET" class="flex">
                    <input type="text" name="q" placeholder="Search Hot Wheels..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-r-lg hover:bg-red-700">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" @click.away="mobileMenuOpen = false" class="lg:hidden bg-white border-t">
            <div class="px-4 py-3 space-y-2">
                <a href="{{ route('home') }}" class="block py-2 text-gray-900 font-medium">Home</a>
                <a href="{{ route('products.index') }}" class="block py-2 text-gray-700">All Products</a>
                <a href="{{ route('flash-sale') }}" class="block py-2 text-red-600 font-semibold">🔥 Flash Sale</a>
                <a href="#" class="block py-2 text-gray-700">Premium</a>
                <a href="#" class="block py-2 text-gray-700">Collector Edition</a>
                <a href="#" class="block py-2 text-gray-700">Pre-Order</a>
                <a href="{{ route('contact') }}" class="block py-2 text-gray-700">Contact Us</a>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Slider -->
        <section class="relative bg-gradient-to-r from-gray-900 to-red-900 text-white">
            <div class="max-w-7xl mx-auto px-4 py-16 md:py-24">
                <div class="grid md:grid-cols-2 gap-8 items-center">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold mb-4 leading-tight">
                            Premium Hot Wheels Collectibles
                        </h1>
                        <p class="text-lg md:text-xl mb-8 text-gray-300">
                            Temukan koleksi Hot Wheels langka, limited edition, dan collector edition dengan kualitas terbaik.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <a href="{{ route('products.index') }}" class="bg-red-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-red-700 transition">
                                Shop Now
                            </a>
                            <a href="{{ route('flash-sale') }}" class="bg-yellow-500 text-gray-900 px-8 py-3 rounded-lg font-semibold hover:bg-yellow-400 transition">
                                Flash Sale 🔥
                            </a>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <img src="{{ asset('images/hero-hotwheels.png') }}" alt="Hot Wheels Collection" class="rounded-lg shadow-2xl">
                    </div>
                </div>
            </div>
        </section>

        <!-- Flash Sale Banner -->
        @if(isset($activeFlashSale))
        <section class="bg-yellow-400">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <span class="text-2xl font-bold">⚡ FLASH SALE</span>
                        <span class="ml-4 text-lg font-semibold" x-data="countdown('{{ $activeFlashSale->end_time }}')" x-text="timeLeft"></span>
                    </div>
                    <a href="{{ route('flash-sale') }}" class="bg-gray-900 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-800">
                        Lihat Semua
                    </a>
                </div>
            </div>
        </section>
        @endif

        <!-- Featured Products -->
        <section class="max-w-7xl mx-auto px-4 py-12">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Featured Products</h2>
                <a href="{{ route('products.index') }}" class="text-red-600 hover:text-red-700 font-semibold">View All →</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
                @foreach($featuredProducts ?? [] as $product)
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition duration-300 overflow-hidden group">
                    <a href="{{ route('products.show', $product->slug) }}" class="block relative">
                        @if($product->discount_percentage)
                        <span class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                            -{{ $product->discount_percentage }}%
                        </span>
                        @endif
                        @if($product->is_limited_edition)
                        <span class="absolute top-2 right-2 bg-yellow-500 text-gray-900 text-xs font-bold px-2 py-1 rounded">
                            Limited
                        </span>
                        @endif
                        <img src="{{ $product->primaryImage?->image_path ?? asset('images/placeholder.jpg') }}" 
                             alt="{{ $product->name }}" 
                             class="w-full h-48 object-cover group-hover:scale-105 transition duration-300">
                    </a>
                    <div class="p-4">
                        <span class="text-xs text-gray-500 uppercase">{{ $product->category->name ?? 'Hot Wheels' }}</span>
                        <a href="{{ route('products.show', $product->slug) }}" class="block mt-1">
                            <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 hover:text-red-600">{{ $product->name }}</h3>
                        </a>
                        <div class="mt-2 flex items-center justify-between">
                            <div>
                                @if($product->sale_price && $product->sale_price < $product->regular_price)
                                <span class="text-xs text-gray-400 line-through">Rp {{ number_format($product->regular_price) }}</span>
                                <span class="text-lg font-bold text-red-600">Rp {{ number_format($product->current_price) }}</span>
                                @else
                                <span class="text-lg font-bold text-gray-900">Rp {{ number_format($product->current_price) }}</span>
                                @endif
                            </div>
                            <span class="text-xs {{ $product->isInStock() ? 'text-green-600' : 'text-red-600' }}">
                                {{ $product->isInStock() ? 'In Stock' : 'Sold Out' }}
                            </span>
                        </div>
                        @if($product->average_rating > 0)
                        <div class="flex items-center mt-1 text-yellow-400 text-xs">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fas fa-star {{ $i <= $product->average_rating ? '' : 'text-gray-300' }}"></i>
                            @endfor
                            <span class="text-gray-500 ml-1">({{ $product->reviews_count }})</span>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </section>

        <!-- Categories Section -->
        <section class="bg-gray-100 py-12">
            <div class="max-w-7xl mx-auto px-4">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-8">Shop by Category</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($categories ?? [] as $category)
                    <a href="{{ route('categories.show', $category->slug) }}" 
                       class="bg-white rounded-xl p-6 text-center shadow-sm hover:shadow-lg transition group">
                        <div class="text-4xl mb-3 group-hover:scale-110 transition">
                            <i class="fas fa-car text-red-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-900">{{ $category->name }}</h3>
                        <span class="text-sm text-gray-500">{{ $category->product_count }} items</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- New Arrivals -->
        <section class="max-w-7xl mx-auto px-4 py-12">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">New Arrivals</h2>
                <a href="{{ route('products.index', ['sort_by' => 'created_at', 'sort_direction' => 'desc']) }}" class="text-red-600 hover:text-red-700 font-semibold">View All →</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
                @foreach($newArrivals ?? [] as $product)
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition overflow-hidden">
                    <a href="{{ route('products.show', $product->slug) }}">
                        <img src="{{ $product->primaryImage?->image_path ?? asset('images/placeholder.jpg') }}" 
                             alt="{{ $product->name }}" class="w-full h-48 object-cover">
                    </a>
                    <div class="p-4">
                        <span class="text-xs text-gray-500">{{ $product->brand->name ?? '' }}</span>
                        <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 mt-1">{{ $product->name }}</h3>
                        <div class="mt-2">
                            <span class="text-lg font-bold text-gray-900">Rp {{ number_format($product->current_price) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </section>

        <!-- Newsletter -->
        <section class="bg-red-600 text-white py-12">
            <div class="max-w-3xl mx-auto px-4 text-center">
                <h2 class="text-2xl md:text-3xl font-bold mb-4">Stay Updated</h2>
                <p class="mb-6 text-red-100">Subscribe untuk mendapatkan info koleksi terbaru, flash sale, dan penawaran eksklusif.</p>
                <form id="newsletter-form" class="flex max-w-md mx-auto">
                    @csrf
                    <input type="email" name="email" placeholder="Your email address" required
                           class="flex-1 px-4 py-3 rounded-l-lg text-gray-900 focus:outline-none">
                    <button type="submit" class="bg-gray-900 text-white px-6 py-3 rounded-r-lg font-semibold hover:bg-gray-800">
                        Subscribe
                    </button>
                </form>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <img src="{{ $siteLogo ?? asset('images/logo-white.png') }}" alt="Wenshop Hot Wheels" class="h-8 mb-4">
                    <p class="text-sm">Premium Hot Wheels Collectibles Store. Menyediakan koleksi Hot Wheels langka dan limited edition.</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('products.index') }}" class="hover:text-white">All Products</a></li>
                        <li><a href="{{ route('flash-sale') }}" class="hover:text-white">Flash Sale</a></li>
                        <li><a href="#" class="hover:text-white">Pre-Order</a></li>
                        <li><a href="#" class="hover:text-white">Collector Edition</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Customer Service</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('contact') }}" class="hover:text-white">Contact Us</a></li>
                        <li><a href="#" class="hover:text-white">FAQ</a></li>
                        <li><a href="#" class="hover:text-white">Shipping Info</a></li>
                        <li><a href="#" class="hover:text-white">Returns</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Follow Us</h4>
                    <div class="flex space-x-4 text-xl">
                        <a href="{{ $socialFacebook ?? '#' }}" class="hover:text-white"><i class="fab fa-facebook"></i></a>
                        <a href="{{ $socialInstagram ?? '#' }}" class="hover:text-white"><i class="fab fa-instagram"></i></a>
                        <a href="{{ $socialTwitter ?? '#' }}" class="hover:text-white"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-sm">
                <p>&copy; {{ date('Y') }} Wenshop Hot Wheels. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // AlpineJS Countdown Component
        document.addEventListener('alpine:init', () => {
            Alpine.data('countdown', (endTime) => ({
                timeLeft: '',
                init() {
                    this.updateCountdown();
                    setInterval(() => this.updateCountdown(), 1000);
                },
                updateCountdown() {
                    const end = new Date(endTime).getTime();
                    const now = new Date().getTime();
                    const distance = end - now;

                    if (distance < 0) {
                        this.timeLeft = 'EXPIRED';
                        return;
                    }

                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    this.timeLeft = `${hours}h ${minutes}m ${seconds}s`;
                }
            }));
        });

        // Newsletter form submission
        document.getElementById('newsletter-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const response = await fetch('{{ route("newsletter.subscribe") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'Accept': 'application/json',
                    }
                });
                const data = await response.json();
                alert(data.message || 'Subscribed successfully!');
                this.reset();
            } catch (error) {
                alert('Subscription failed. Please try again.');
            }
        });
    </script>
</body>
</html>
