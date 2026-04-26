{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{{ $base }}/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>{{ $base }}/games</loc>
    <changefreq>hourly</changefreq>
    <priority>0.9</priority>
  </url>
  @foreach ($products as $product)
  <url>
    <loc>{{ $base }}/product/{{ $product->type }}/{{ $product->slug }}</loc>
    <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  @endforeach
</urlset>
