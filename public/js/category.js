(function () {
    function reportVarnishHit(path) {
        fetch('/api/activity/cache-hit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path }),
        }).catch(() => {});
    }

    const root = document.querySelector('.category-list-page');
    if (!root) return;

    const slug = root.dataset.categorySlug;
    const listEl = root.querySelector('[data-item-list]');
    const pageInfo = root.querySelector('[data-page-info]');
    const prevBtn = root.querySelector('[data-prev]');
    const nextBtn = root.querySelector('[data-next]');
    const cacheBadge = root.querySelector('[data-cache-badge]');
    const limit = 10;
    const storageKey = `category-page-${slug}`;
    let page = Math.max(1, parseInt(sessionStorage.getItem(storageKey) || '1', 10) || 1);
    let loadGeneration = 0;

    const applyPagination = (currentPage, totalPages) => {
        prevBtn.disabled = currentPage <= 1;
        nextBtn.disabled = currentPage >= totalPages;
    };

    async function load() {
        const generation = ++loadGeneration;
        cacheBadge.textContent = 'Varnish: …';
        cacheBadge.className = 'cache-badge loading';
        prevBtn.disabled = true;
        nextBtn.disabled = true;

        const res = await fetch(
            `/api/categories/${encodeURIComponent(slug)}/items?page=${page}&limit=${limit}`,
            { cache: 'no-store' }
        );
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
        sessionStorage.setItem(storageKey, String(page));
        pageInfo.textContent = `Page ${data.page} / ${data.pages} (${data.total} items)`;
        applyPagination(data.page, data.pages);

        const cache = res.headers.get('X-Cache') || '—';
        cacheBadge.textContent = `Varnish: ${cache}`;
        cacheBadge.className = 'cache-badge ' + (cache === 'HIT' ? 'hit' : 'miss');
        if (cache === 'HIT') {
            reportVarnishHit(`/api/categories/${encodeURIComponent(slug)}/items?page=${page}&limit=${limit}`);
        }
    }

    prevBtn.addEventListener('click', () => {
        if (page > 1) {
            page -= 1;
            load();
        }
    });

    nextBtn.addEventListener('click', () => {
        page += 1;
        load();
    });

    cacheBadge.textContent = 'Varnish: …';
    cacheBadge.className = 'cache-badge loading';
    load();

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
