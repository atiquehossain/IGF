@php
    $isProduction = app()->environment('production');
    $indexingEnabled = app(\App\Services\SeoIndexingPolicy::class)->indexingAllowed();
@endphp
@if($indexingEnabled)
    <div class="seo2-alert" role="status">
        <strong>Search indexing is enabled.</strong> Public pages marked “Show in search” can be crawled when the website is live.
    </div>
@elseif(!$isProduction)
    <div class="seo2-alert seo2-alert--warning" role="status">
        <strong>Preview environment: every page is marked noindex.</strong> This is an SEO safeguard, not privacy or access control—protect a private preview with authentication, an IP allowlist or a VPN. At production launch, the deployment owner must explicitly set <code>SEO_INDEXING_ENABLED=true</code> after the final review.
    </div>
@else
    <div class="seo2-alert seo2-alert--warning" role="status">
        <strong>Every public page is currently marked noindex.</strong> Crawlers may read that directive so already-known URLs can be removed, but pages will remain ineligible for indexing until the deployment owner enables <code>SEO_INDEXING_ENABLED=true</code> in production.
    </div>
@endif
