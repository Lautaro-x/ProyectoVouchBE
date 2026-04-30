<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $base = rtrim(env('FRONTEND_URL', 'http://localhost:4200'), '/');

        $xml = Cache::remember('sitemap_xml', 86400, function () use ($base) {
            $products = Product::select('type', 'slug', 'updated_at')->get();
            return view('sitemap', compact('products', 'base'))->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
