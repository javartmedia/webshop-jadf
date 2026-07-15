<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Slider;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Product::with(['images', 'category', 'brand'])
            ->active()
            ->inStock()
            ->featured()
            ->latest()
            ->limit(20)
            ->get();

        $newArrivals = Product::with(['images', 'category', 'brand'])
            ->active()
            ->inStock()
            ->latest()
            ->limit(15)
            ->get();

        $categories = Category::withCount(['products' => function ($q) {
            $q->active();
        }])
        ->active()
        ->root()
        ->orderBy('sort_order')
        ->get();

        $sliders = Slider::active()->orderBy('sort_order')->get();

        $activeFlashSale = FlashSale::with(['products' => function ($q) {
            $q->active()->inStock()->limit(10);
        }])
        ->where('is_active', true)
        ->where('start_time', '<=', now())
        ->where('end_time', '>=', now())
        ->first();

        $settings = Setting::getAllPublicSettings();

        return view('frontend.home', compact(
            'featuredProducts',
            'newArrivals',
            'categories',
            'sliders',
            'activeFlashSale',
            'settings'
        ));
    }

    public function page(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->firstOrFail();
        return view('frontend.page', compact('page'));
    }

    public function contact()
    {
        return view('frontend.contact');
    }

    public function sendContact(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        \App\Models\ContactMessage::create($validated);

        return back()->with('success', 'Pesan Anda telah terkirim. Kami akan menghubungi Anda segera.');
    }

    public function newsletterSubscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:newsletter_subscribers,email'],
        ]);

        \App\Models\NewsletterSubscriber::create([
            'email' => $validated['email'],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih telah berlangganan newsletter kami!',
        ]);
    }
}
