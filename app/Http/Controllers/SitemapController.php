<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $products = Product::select('type', 'slug', 'updated_at')->get();
        $base     = rtrim(env('FRONTEND_URL', 'http://localhost:4200'), '/');

        $xml = view('sitemap', compact('products', 'base'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
