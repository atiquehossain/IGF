<section class="seo2-card seo2-redirects" id="redirects" aria-labelledby="seo2-redirects-title">
    <header class="seo2-card__head"><div><h2 id="seo2-redirects-title">Redirect old addresses</h2><p>Send an old or changed address to the correct live page without losing visitors.</p></div><a class="seo2-btn" href="{{ $redirectTrash ? route('seo.redirects.index') : route('seo.redirects.index', ['redirect_trash' => 1]) }}">{{ $redirectTrash ? 'Active redirects' : 'Redirect trash' }}</a></header>
    <div class="seo2-card__body">
        @if(!$redirectTrash && $canCreateRedirects)
        <form class="seo2-redirect-form" method="POST" action="{{ route('seo.redirects.store') }}" data-redirect-create>
            @csrf<input type="hidden" name="is_active" value="1">
            <label class="seo2-field"><span>Old address</span><input name="from_path" placeholder="/old-page" required><small class="seo2-help">Start with / and enter only the old path.</small></label>
            <label class="seo2-field"><span>Language scope</span><select name="locale"><option value="">All languages (global)</option>@foreach($locales as $redirectLocale)<option value="{{ $redirectLocale->id }}" @selected($redirectLocale->id === $locale)>{{ $redirectLocale->native_name }} only</option>@endforeach</select><small class="seo2-help">Choose one language when translated pages can use different addresses.</small></label>
            <label class="seo2-field"><span>Send visitors to</span><select data-redirect-destination><option value="">Choose a page or enter an address</option>@foreach($redirectDestinations as $destination)<option value="{{ $destination['path'] }}">{{ $destination['label'] }} — {{ $destination['path'] }}</option>@endforeach</select><input name="to_url" placeholder="/new-page" required aria-label="Redirect destination address"></label>
            <label class="seo2-field"><span>Redirect type</span><select name="status_code"><option value="301">Permanent move (recommended)</option><option value="302">Temporary move</option></select></label>
            <button class="seo2-btn seo2-btn--primary" type="submit">Create redirect</button>
        </form>
        <p class="seo2-redirect-help"><strong>Permanent</strong> is right when a page address changed for good. The system blocks protected paths, loops and redirect chains.</p>
        @endif

        <div class="seo2-table-wrap"><table class="seo2-table"><caption class="seo2-sr">{{ $redirectTrash ? 'Deleted redirects' : 'Configured redirects' }}</caption><thead><tr><th>Old address</th><th>Language</th><th>Destination</th><th>Type</th><th>Traffic</th><th>Status &amp; actions</th></tr></thead><tbody>
        @forelse($redirects as $redirect)
            <tr><td><strong>{{ $redirect->from_path }}</strong></td><td><span class="seo2-chip seo2-chip--neutral">{{ $redirect->locale ? strtoupper($redirect->locale) : 'Global' }}</span></td><td>{{ $redirect->to_url }}</td><td>{{ in_array($redirect->status_code, [301,308], true) ? 'Permanent' : 'Temporary' }}</td><td>{{ number_format($redirect->hits) }} visits @if($redirect->last_hit_at)<small class="seo2-help">Last {{ $redirect->last_hit_at->diffForHumans() }}</small>@endif</td><td><div class="seo2-row-actions">
                @if($redirectTrash)
                    @if($canCreateRedirects)
                    <form method="POST" action="{{ route('seo.redirects.store') }}">@csrf<input type="hidden" name="action" value="restore"><input type="hidden" name="redirect_id" value="{{ $redirect->id }}"><button class="seo2-btn" type="submit">Restore disabled</button></form>
                    @else
                    <span class="seo2-chip seo2-chip--neutral">Deleted</span>
                    @endif
                @else
                    <span class="seo2-chip {{ $redirect->is_active ? '' : 'seo2-chip--neutral' }}">{{ $redirect->is_active ? 'Active' : 'Paused' }}</span>
                    <a class="seo2-btn" href="{{ url($redirect->from_path) }}{{ $redirect->locale && $redirect->locale !== config('app.fallback_locale') ? '?' . config('seo.locale_query_parameter', 'lang') . '=' . urlencode($redirect->locale) : '' }}" target="_blank" rel="noopener">Test</a>
                    @if($canCreateRedirects)
                    <button class="seo2-btn" type="button" data-redirect-edit="{{ $redirect->id }}">Edit</button>
                    <form method="POST" action="{{ route('seo.redirects.store') }}">@csrf<input type="hidden" name="action" value="toggle"><input type="hidden" name="redirect_id" value="{{ $redirect->id }}"><input type="hidden" name="is_active" value="{{ $redirect->is_active ? 0 : 1 }}"><button class="seo2-btn" type="submit">{{ $redirect->is_active ? 'Pause' : 'Enable' }}</button></form>
                    @endif
                    @if($canDestroyRedirects)
                    <form method="POST" action="{{ route('seo.redirects.destroy', $redirect) }}" onsubmit="return confirm('Move this redirect to trash and stop it?')">@csrf @method('DELETE')<button class="seo2-btn seo2-btn--danger" type="submit">Trash</button></form>
                    @endif
                @endif
            </div></td></tr>
            @if(!$redirectTrash && $canCreateRedirects)<tr><td colspan="6" style="padding:0"><form class="seo2-inline-edit" method="POST" action="{{ route('seo.redirects.store') }}" data-redirect-editor="{{ $redirect->id }}">@csrf<input type="hidden" name="redirect_id" value="{{ $redirect->id }}"><input type="hidden" name="is_active" value="{{ $redirect->is_active ? 1 : 0 }}"><label class="seo2-field"><span>Old address</span><input name="from_path" value="{{ $redirect->from_path }}" required></label><label class="seo2-field"><span>Language scope</span><select name="locale"><option value="" @selected(!$redirect->locale)>All languages (global)</option>@foreach($locales as $redirectLocale)<option value="{{ $redirectLocale->id }}" @selected($redirect->locale === $redirectLocale->id)>{{ $redirectLocale->native_name }} only</option>@endforeach</select></label><label class="seo2-field"><span>Destination</span><input name="to_url" value="{{ $redirect->to_url }}" required></label><label class="seo2-field"><span>Type</span><select name="status_code"><option value="301" @selected($redirect->status_code === 301)>Permanent</option><option value="302" @selected($redirect->status_code === 302)>Temporary</option><option value="307" @selected($redirect->status_code === 307)>Temporary (preserve method)</option><option value="308" @selected($redirect->status_code === 308)>Permanent (preserve method)</option></select></label><div class="seo2-actions"><button class="seo2-btn seo2-btn--primary" type="submit">Save</button><button class="seo2-btn" type="button" data-redirect-cancel>Cancel</button></div></form></td></tr>@endif
        @empty<tr><td colspan="6" class="seo2-empty">{{ $redirectTrash ? 'Redirect trash is empty.' : 'No redirects configured yet.' }}</td></tr>@endforelse
        </tbody></table></div>
        {{ $redirects->links() }}
    </div>
</section>
