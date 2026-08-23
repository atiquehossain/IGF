@extends('admin.layouts.master')

@section('content')
<style>
    .igf-dashboard{--dash-card:#fff;--dash-line:rgba(25,28,29,.07);width:100%;max-width:1600px;margin:0 auto;padding:34px 32px 60px;color:#191c1d}
    .dash-heading{margin-bottom:27px}.dash-heading h1{margin:0 0 6px;font:600 clamp(34px,3.2vw,48px)/1.15 'Literata',serif;letter-spacing:-.015em;color:#191c1d}.dash-heading p{margin:0;color:#5e5d66;font-size:16px;line-height:1.55}
    .dash-quick{margin-bottom:24px}.dash-quick-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:12px}.dash-quick-head h2{margin:0;font:600 20px/1.3 'Literata',serif}.dash-enquiries{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:7px;color:#5e5d66;font-size:11px}.dash-enquiries>span{font-weight:750}.dash-enquiries a{display:inline-flex;min-height:44px;align-items:center;gap:6px;padding:7px 10px;border:1px solid #efc7a6;border-radius:999px;background:#fff8f2;color:#7f3908;font-weight:800;text-decoration:none!important}.dash-enquiries a:hover{border-color:#ff7500;background:#fff1e6}.dash-enquiries strong{display:grid;min-width:20px;height:20px;place-content:center;border-radius:999px;background:#9c4500;color:#fff;font-size:10px}.dash-enquiries--clear{color:#287240;font-weight:800}.dash-quick-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.dash-quick-link{display:grid;grid-template-columns:39px minmax(0,1fr);gap:12px;align-items:start;min-height:44px;padding:16px;border:1px solid var(--dash-line);border-radius:11px;background:#fff;color:#191c1d;text-decoration:none!important;transition:border-color .18s,transform .18s,box-shadow .18s}.dash-quick-link:hover{border-color:#ffb47a;color:#9c4500;transform:translateY(-2px);box-shadow:0 9px 22px rgba(36,36,43,.06)}.dash-quick-link:focus-visible{outline:3px solid rgba(255,117,0,.28)!important;outline-offset:2px}.dash-quick-link>i{display:grid;width:39px;height:39px;place-content:center;border-radius:9px;background:#fff1e6;color:#ff7500}.dash-quick-link strong,.dash-quick-link small{display:block}.dash-quick-link strong{margin-bottom:4px;font-size:12px}.dash-quick-link small{color:#68666b;font-size:10px;line-height:1.45}
    .dash-health{display:grid;gap:8px;margin:0 0 22px}.dash-health-item{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:13px 16px;border:1px solid #f0c9a9;border-left:4px solid #ff7500;border-radius:8px;background:#fff8f2}.dash-health-item strong,.dash-health-item small{display:block}.dash-health-item strong{margin-bottom:3px;font-size:12px}.dash-health-item small{color:#68666b;font-size:11px}.dash-health-item a{display:inline-flex;min-height:44px;align-items:center;flex:0 0 auto;color:#9c4500;font-size:11px;font-weight:850;text-decoration:none}
    .dash-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px;margin-bottom:22px}.dash-card{border:1px solid var(--dash-line);border-radius:12px;background:var(--dash-card);box-shadow:none}.dash-metric{position:relative;min-height:157px;padding:22px;overflow:hidden;transition:box-shadow .2s,transform .2s}.dash-metric:after{position:absolute;top:-24px;right:-24px;width:102px;height:102px;border-radius:50%;background:rgba(255,117,0,.055);content:""}.dash-metric:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(36,36,43,.07)}
    .dash-metric-top{position:relative;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:18px}.dash-label{color:#5e5d66;font-size:11px;font-weight:800;letter-spacing:.075em;line-height:1.15;text-transform:uppercase}.dash-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 35px;width:35px;height:35px;border-radius:8px;background:rgba(255,117,0,.1);color:#ff7500;font-size:15px}.dash-icon--danger{background:rgba(186,26,26,.09);color:#ba1a1a}.dash-value{position:relative;z-index:1;margin-bottom:7px;font:600 27px/1.1 'Literata',serif;color:#191c1d}.dash-delta{position:relative;z-index:1;display:flex;align-items:center;gap:6px;color:#9c4500;font-size:12px;line-height:1.3}.dash-delta--danger{color:#ba1a1a}.dash-delta--neutral{color:#5e5d66}
    .dash-body{display:grid;grid-template-columns:minmax(0,2fr) minmax(300px,1fr);gap:22px}.dash-chart{display:flex;flex-direction:column;min-height:506px;padding:23px 24px 22px}.dash-card-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:24px}.dash-card-head h2{margin:0;font:600 19px/1.3 'Literata',serif}.dash-period{padding:7px 11px;border:1px solid var(--dash-line);border-radius:8px;background:#f8f9fa;color:#5e5d66;font-size:11px;font-weight:700}
    .dash-chart-plot{position:relative;display:flex;flex:1;min-height:365px;margin:6px 6px 26px 36px;padding:18px 10px 0;border-bottom:1px solid #dddfe0;border-left:1px solid #dddfe0}.dash-y-axis{position:absolute;top:0;bottom:0;left:-42px;display:flex;flex-direction:column;justify-content:space-between;width:34px;padding:0 0 1px;color:#747478;font-size:10px;text-align:right}.dash-bars{display:flex;align-items:flex-end;gap:11px;width:100%;height:100%}.dash-bar-wrap{position:relative;display:flex;align-items:flex-end;flex:1;height:100%}.dash-bar{position:relative;width:100%;min-height:1px;border-radius:3px 3px 0 0;background:#ffd9c2;transition:filter .18s,transform .18s;transform-origin:bottom}.dash-bar-wrap:nth-child(2) .dash-bar{background:#ffc49f}.dash-bar-wrap:nth-child(3) .dash-bar{background:#ff882c}.dash-bar-wrap:nth-child(4) .dash-bar{background:#ffb684}.dash-bar-wrap:nth-child(5) .dash-bar{background:#ff9e5a}.dash-bar-wrap:nth-child(6) .dash-bar{background:#ff7d1a}.dash-bar-wrap:nth-child(7) .dash-bar{background:linear-gradient(180deg,#ff7500,#f06a00);box-shadow:0 0 12px rgba(255,117,0,.18)}.dash-bar:hover{filter:saturate(1.12);transform:scaleY(1.01)}.dash-tooltip{position:absolute;bottom:calc(100% + 7px);left:50%;z-index:2;padding:5px 7px;border-radius:5px;background:#2e3132;color:#fff;font-size:10px;white-space:nowrap;opacity:0;pointer-events:none;transform:translateX(-50%);transition:opacity .18s}.dash-bar:hover .dash-tooltip{opacity:1}.dash-x-label{position:absolute;right:0;bottom:-26px;left:0;color:#747478;font-size:10px;text-align:center}
    .dash-side{display:flex;flex-direction:column;gap:22px}.dash-localization{padding:23px 24px}.dash-localization h2,.dash-activity h2{margin:0;font:600 19px/1.3 'Literata',serif}.locale-list{display:grid;gap:18px;margin-top:19px}.locale-meta{display:flex;justify-content:space-between;gap:15px;margin-bottom:6px;font-size:12px}.locale-meta strong{font-weight:700}.locale-meta span{color:#5e5d66}.locale-track{height:8px;overflow:hidden;border-radius:999px;background:#e7e8e9}.locale-fill{height:100%;border-radius:inherit;background:#ff7500}.locale-item:nth-child(even) .locale-fill{background:#f07f2d}.dash-secondary-button{display:flex;align-items:center;justify-content:center;width:100%;margin-top:21px;padding:9px 12px;border:1px solid #8c7163;border-radius:8px;background:#fff;color:#191c1d;font-size:11px;font-weight:800;text-decoration:none!important}.dash-secondary-button:hover{background:#f3f4f5;color:#9c4500}
    .dash-activity{flex:1;padding:23px 24px}.dash-card-head--activity{margin-bottom:18px}.dash-card-head--activity a{color:#ff7500;font-size:11px;font-weight:800;text-decoration:none}.activity-list{position:relative;display:grid;gap:14px}.activity-list:before{position:absolute;top:17px;bottom:17px;left:16px;width:1px;background:#e1e3e4;content:""}.activity-item{position:relative;display:grid;grid-template-columns:33px minmax(0,1fr);gap:12px;align-items:start}.activity-icon{position:relative;z-index:1;display:flex;align-items:center;justify-content:center;width:33px;height:33px;border:1px solid var(--dash-line);border-radius:50%;background:#fff;color:#ff7500;font-size:12px}.activity-copy{padding:11px 12px;border:1px solid var(--dash-line);border-radius:8px;background:#f8f9fa}.activity-copy strong{display:block;margin-bottom:4px;font-size:12px}.activity-copy p{margin:0;color:#5e5d66;font-size:11px;line-height:1.4}.activity-copy time{display:block;margin-top:8px;color:#77777c;font-size:9px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.activity-empty{padding:30px 10px;color:#5e5d66;font-size:13px;text-align:center}
    @media(max-width:1200px){.dash-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dash-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.dash-body{grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr)}}
    @media(max-width:900px){.igf-dashboard{padding:28px 22px 50px}.dash-body{grid-template-columns:1fr}.dash-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.dash-chart{min-height:460px}}
    @media(max-width:640px){.igf-dashboard{padding:24px 15px 42px}.dash-heading{margin-bottom:21px}.dash-heading p{font-size:14px}.dash-quick-head{align-items:start;flex-direction:column;gap:8px}.dash-enquiries{justify-content:flex-start}.dash-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.dash-quick-link{grid-template-columns:34px minmax(0,1fr);gap:8px;padding:12px}.dash-quick-link>i{width:34px;height:34px}.dash-quick-link small{display:none}.dash-metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.dash-metric{min-height:126px;padding:15px}.dash-value{font-size:23px}.dash-delta{font-size:10px}.dash-body{gap:14px}.dash-side{display:flex}.dash-chart{min-height:350px;padding:17px 12px}.dash-chart-plot{min-height:230px;margin-left:31px}.dash-bars{gap:5px}.dash-localization,.dash-activity{padding:19px}}
</style>

<main class="igf-dashboard">
    <header class="dash-heading">
        <h1>Overview</h1>
        <p>Choose a common task below or review what needs attention today.</p>
    </header>

    @if($quickActions->isNotEmpty() || $metrics['new_enquiries'] > 0)
        <section class="dash-quick" aria-labelledby="quick-actions-title">
            <div class="dash-quick-head">
                <h2 id="quick-actions-title">{{ $quickActions->isNotEmpty() ? 'What would you like to do?' : 'Items needing review' }}</h2>
                @if($metrics['new_enquiries'] > 0)
                    <nav class="dash-enquiries" aria-label="New public enquiries">
                        <span>{{ number_format($metrics['new_enquiries']) }} {{ $metrics['new_enquiries'] === 1 ? 'item needs' : 'items need' }} review:</span>
                        @forelse($enquiryActions as $enquiry)
                            <a href="{{ route($enquiry['route']) }}"><strong>{{ number_format($enquiry['count']) }}</strong>{{ $enquiry['label'] }}</a>
                        @empty
                            <span>Ask an authorised teammate to open the enquiry inbox.</span>
                        @endforelse
                    </nav>
                @else
                    <span class="dash-enquiries--clear"><i class="fa fa-check-circle-o" aria-hidden="true"></i> No new enquiries</span>
                @endif
            </div>
            @if($quickActions->isNotEmpty())
                <div class="dash-quick-grid">
                    @foreach($quickActions as $action)
                        <a class="dash-quick-link" href="{{ route($action['route']) }}">
                            <i class="fa {{ $action['icon'] }}" aria-hidden="true"></i>
                            <span><strong>{{ $action['label'] }}</strong><small>{{ $action['help'] }}</small></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($siteHealth->isNotEmpty())
        <section class="dash-health" aria-label="Website setup alerts">
            @foreach($siteHealth as $item)
                <article class="dash-health-item">
                    <span><strong>{{ $item['title'] }}</strong><small>{{ $item['detail'] }}</small></span>
                    <a href="{{ route($item['route'], $item['parameters']) }}">{{ $item['action'] }} &rarr;</a>
                </article>
            @endforeach
        </section>
    @endif

    <section class="dash-metrics" aria-label="Foundation metrics">
        <article class="dash-card dash-metric">
            <div class="dash-metric-top"><span class="dash-label">Donations Today</span><span class="dash-icon"><i class="fa fa-heart-o" aria-hidden="true"></i></span></div>
            <div class="dash-value">৳{{ number_format($metrics['donations_today']) }}</div>
            <div class="dash-delta"><i class="fa {{ $metrics['donation_change'] >= 0 ? 'fa-line-chart' : 'fa-level-down' }}" aria-hidden="true"></i><span>{{ $metrics['donation_change'] >= 0 ? '+' : '' }}{{ $metrics['donation_change'] }}% from yesterday</span></div>
        </article>
        <article class="dash-card dash-metric">
            <div class="dash-metric-top"><span class="dash-label">Successful Gifts</span><span class="dash-icon"><i class="fa fa-refresh" aria-hidden="true"></i></span></div>
            <div class="dash-value">{{ number_format($metrics['successful_gifts']) }}</div>
            <div class="dash-delta"><i class="fa fa-line-chart" aria-hidden="true"></i><span>+{{ number_format($metrics['successful_this_month']) }} this month</span></div>
        </article>
        <article class="dash-card dash-metric">
            <div class="dash-metric-top"><span class="dash-label">Pending Gateways</span><span class="dash-icon dash-icon--danger"><i class="fa fa-exclamation-circle" aria-hidden="true"></i></span></div>
            <div class="dash-value">{{ number_format($metrics['pending_gateways']) }}</div>
            <div class="dash-delta {{ $metrics['pending_gateways'] ? 'dash-delta--danger' : 'dash-delta--neutral' }}"><i class="fa {{ $metrics['pending_gateways'] ? 'fa-warning' : 'fa-check-circle-o' }}" aria-hidden="true"></i><span>{{ $metrics['pending_gateways'] ? 'Requires attention' : 'All gateways clear' }}</span></div>
        </article>
        <article class="dash-card dash-metric">
            <div class="dash-metric-top"><span class="dash-label">Volunteers</span><span class="dash-icon"><i class="fa fa-users" aria-hidden="true"></i></span></div>
            <div class="dash-value">{{ number_format($metrics['volunteers']) }}</div>
            <div class="dash-delta dash-delta--neutral"><i class="fa fa-check-circle-o" aria-hidden="true"></i><span>Active and approved</span></div>
        </article>
    </section>

    <section class="dash-body">
        <article class="dash-card dash-chart">
            <div class="dash-card-head"><h2>Revenue Trends</h2><span class="dash-period">Last 7 Months</span></div>
            <div class="dash-chart-plot" role="img" aria-label="Donation revenue over the last seven months">
                <div class="dash-y-axis"><span>৳{{ number_format($maxRevenue) }}</span><span>৳{{ number_format($maxRevenue / 2) }}</span><span>৳0</span></div>
                <div class="dash-bars">
                    @foreach ($monthlyRevenue as $month)
                        <div class="dash-bar-wrap">
                            <div class="dash-bar" style="height:{{ $month['height'] }}%">
                                <span class="dash-tooltip">৳{{ number_format($month['amount']) }}</span>
                            </div>
                            <span class="dash-x-label">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </article>

        <div class="dash-side">
            <article class="dash-card dash-localization">
                <h2>Localization Tracker</h2>
                <div class="locale-list">
                    @foreach ($localization as $locale)
                        <div class="locale-item">
                            <div class="locale-meta"><strong>{{ $locale['label'] }} ({{ strtoupper($locale['locale']) }})</strong><span>{{ $locale['percent'] }}% Complete</span></div>
                            <div class="locale-track" role="progressbar" aria-label="{{ $locale['label'] }} content completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $locale['percent'] }}"><div class="locale-fill" style="width:{{ $locale['percent'] }}%"></div></div>
                        </div>
                    @endforeach
                </div>
                @if($canReviewTranslations)<a class="dash-secondary-button" href="{{ route('translations.index') }}">Review Missing Translations</a>@endif
            </article>

            <article class="dash-card dash-activity">
                <div class="dash-card-head dash-card-head--activity"><h2>Recent Activity</h2>@if($canReviewPages)<a href="{{ route('page.index') }}">View All</a>@endif</div>
                @if ($recentActivity->isEmpty())
                    <div class="activity-empty">Activity will appear here as your team publishes content and receives donations.</div>
                @else
                    <div class="activity-list">
                        @foreach ($recentActivity as $activity)
                            <div class="activity-item">
                                <span class="activity-icon"><i class="fa {{ $activity['icon'] }}" aria-hidden="true"></i></span>
                                <div class="activity-copy">
                                    <strong>{{ $activity['title'] }}</strong>
                                    <p>{{ $activity['detail'] }}</p>
                                    <time datetime="{{ $activity['at']->toIso8601String() }}">{{ $activity['at']->diffForHumans() }}</time>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        </div>
    </section>
</main>
@endsection
