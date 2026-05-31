(function () {
    const feed = document.getElementById('activity-feed');
    const countEl = document.getElementById('activity-count');
    const autoRefresh = document.getElementById('auto-refresh');
    if (!feed) return;

    const knownIds = new Set(
        Array.from(feed.querySelectorAll('.activity-item')).map((el) => el.dataset.id)
    );

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatTime(iso) {
        try {
            const d = new Date(iso);
            return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        } catch {
            return iso;
        }
    }

    function renderEntry(entry) {
        const ctx = entry.context && Object.keys(entry.context).length
            ? `<details class="activity-context"><summary>Details</summary><pre>${escapeHtml(JSON.stringify(entry.context, null, 2))}</pre></details>`
            : '';

        return `<li class="activity-item activity-${escapeHtml(entry.source)}" data-id="${escapeHtml(entry.id)}">
            <div class="activity-meta">
                <span class="badge badge-${escapeHtml(entry.source)}">${escapeHtml(entry.source)}</span>
                <code class="activity-action">${escapeHtml(entry.action)}</code>
                <time datetime="${escapeHtml(entry.at)}">${formatTime(entry.at)}</time>
            </div>
            <p class="activity-message">${escapeHtml(entry.message)}</p>
            ${ctx}
        </li>`;
    }

    async function refresh() {
        try {
            const res = await fetch('/api/activity?limit=80');
            const data = await res.json();
            if (countEl) {
                countEl.textContent = `${data.total} events`;
            }

            const empty = document.getElementById('activity-empty');
            if (empty) empty.remove();

            data.entries.forEach((entry) => {
                if (knownIds.has(entry.id)) return;
                knownIds.add(entry.id);
                feed.insertAdjacentHTML('afterbegin', renderEntry(entry));
            });

            while (feed.children.length > 80) {
                const last = feed.lastElementChild;
                if (last && last.dataset.id) knownIds.delete(last.dataset.id);
                last?.remove();
            }
        } catch (e) {
            console.warn('Activity refresh failed', e);
        }
    }

    if (autoRefresh) {
        setInterval(() => {
            if (autoRefresh.checked) refresh();
        }, 3000);
    }
})();
