(function () {
    function reportVarnishHit(path) {
        fetch('/api/activity/cache-hit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path }),
        }).catch(() => {});
    }

    const searchInput = document.getElementById('search-input');
    const suggestions = document.getElementById('search-suggestions');
    let searchTimer = null;

    if (searchInput && suggestions) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim();
            if (q.length < 2) {
                suggestions.hidden = true;
                suggestions.innerHTML = '';
                return;
            }
            searchTimer = setTimeout(() => fetchSuggestions(q), 200);
        });

        document.addEventListener('click', (e) => {
            if (!suggestions.contains(e.target) && e.target !== searchInput) {
                suggestions.hidden = true;
            }
        });
    }

    async function fetchSuggestions(q) {
        const res = await fetch(`/api/search/autocomplete?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        suggestions.innerHTML = '';
        if (!data.results || data.results.length === 0) {
            suggestions.hidden = true;
            return;
        }
        data.results.forEach((item) => {
            const li = document.createElement('li');
            const cats = Array.isArray(item.category_names)
                ? item.category_names.join(', ')
                : (item.category_name || '');
            li.innerHTML = `<strong>${escapeHtml(item.name)}</strong><br><span class="cat">${escapeHtml(cats)}</span>`;
            li.addEventListener('click', () => {
                searchInput.value = item.name;
                suggestions.hidden = true;
            });
            suggestions.appendChild(li);
        });
        suggestions.hidden = false;
    }

    /** Always hit Varnish; do not use the browser HTTP cache (it freezes X-Cache on first MISS/HIT). */
    const varnishFetch = (url, init) => fetch(url, { ...init, cache: 'no-store' });
    const PAGE_LIMIT = 10;

    const cards = [...document.querySelectorAll('.list-card')].filter((c) => c.dataset.categorySlug);
    const MAX_CONCURRENT = 3;
    let inFlight = 0;
    const queue = [];

    function runQueue() {
        while (inFlight < MAX_CONCURRENT && queue.length > 0) {
            const job = queue.shift();
            inFlight += 1;
            job().finally(() => {
                inFlight -= 1;
                runQueue();
            });
        }
    }

    function enqueueLoad(job) {
        queue.push(job);
        runQueue();
    }

    cards.forEach((card) => {
        const slug = card.dataset.categorySlug;
        const listEl = card.querySelector('[data-item-list]');
        const pageInfo = card.querySelector('[data-page-info]');
        const prevBtn = card.querySelector('[data-prev]');
        const nextBtn = card.querySelector('[data-next]');
        const cacheBadge = card.querySelector('[data-cache-badge]');
        const apiPath = `/api/categories/${encodeURIComponent(slug)}/items`;
        let page = 1;
        let loadGeneration = 0;

        cacheBadge.textContent = 'Varnish: …';
        cacheBadge.className = 'cache-badge loading';

        const applyPagination = (currentPage, totalPages) => {
            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;
        };

        const load = async () => {
            const generation = ++loadGeneration;
            cacheBadge.textContent = 'Varnish: …';
            cacheBadge.className = 'cache-badge loading';
            prevBtn.disabled = true;
            nextBtn.disabled = true;

            try {
                const res = await varnishFetch(`${apiPath}?page=${page}&limit=${PAGE_LIMIT}`);
                if (generation !== loadGeneration) {
                    return;
                }
                const data = await res.json();
                if (data.error) {
                    listEl.innerHTML = '<li class="muted">Could not load category</li>';
                    cacheBadge.textContent = 'Varnish: —';
                    cacheBadge.className = 'cache-badge';
                    prevBtn.disabled = page <= 1;
                    nextBtn.disabled = false;
                    return;
                }
                listEl.innerHTML = data.items
                    .map((i) => `<li>${escapeHtml(i.name)}</li>`)
                    .join('');
                page = data.page;
                pageInfo.textContent = `Page ${data.page} / ${data.pages}`;
                applyPagination(data.page, data.pages);

                const cache = res.headers.get('X-Cache') || '—';
                cacheBadge.textContent = `Varnish: ${cache}`;
                cacheBadge.className = 'cache-badge ' + (cache === 'HIT' ? 'hit' : 'miss');
                if (cache === 'HIT') {
                    reportVarnishHit(`${apiPath}?page=${page}&limit=${PAGE_LIMIT}`);
                }
            } catch {
                listEl.innerHTML = '<li class="muted">Could not load category</li>';
                cacheBadge.textContent = 'Varnish: error';
                cacheBadge.className = 'cache-badge';
                prevBtn.disabled = page <= 1;
                nextBtn.disabled = false;
            }
        };

        const scheduleLoad = () => enqueueLoad(load);

        prevBtn.addEventListener('click', () => {
            if (page > 1) {
                page -= 1;
                scheduleLoad();
            }
        });
        nextBtn.addEventListener('click', () => {
            page += 1;
            scheduleLoad();
        });

        scheduleLoad();
    });

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
