@php
    $isProduction = app()->environment('production');
    $indexingEnabled = $isProduction && (bool) config('seo.robots.indexing_enabled');
@endphp
@if($indexingEnabled)
    <div class="seo2-alert" role="status">
        <strong>Search indexing is enabled.</strong> Public pages marked “Show in search” can be crawled when the website is live.
    </div>
@elseif(!$isProduction)
    <div class="seo2-alert seo2-alert--warning" role="status">
        <strong>Preview environment: search indexing is blocked here.</strong> That protects local and staging copies. At production launch, the deployment owner must explicitly set <code>SEO_INDEXING_ENABLED=true</code> after the final review.
    </div>
@else
    <div class="seo2-alert seo2-alert--warning" role="status">
        <strong>Search indexing is currently blocked.</strong> Your settings are saved and previewable, but search engines will remain blocked until the deployment owner enables <code>SEO_INDEXING_ENABLED=true</code> in production.
    </div>
@endif
