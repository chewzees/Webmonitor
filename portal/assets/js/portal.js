(function () {
    'use strict';

    const data = window.PORTAL_DATA || {};
    const profile = data.profile || {
        username: 'Shaun',
        bio: 'Here Is My Project ✨',
        avatar: 'assets/img/pfp.png'
    };
    let links = Array.isArray(data.links) ? data.links : [];
    let currentSort = 'default';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function sanitizeUrl(url) {
        try {
            const parsed = new URL(String(url || ''), window.location.href);
            if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                return parsed.href;
            }
        } catch (_) {
            /* ignore */
        }
        return '#';
    }

    function renderProfile() {
        const avatarEl = document.querySelector('.profile-avatar');
        if (!avatarEl) return;

        if (profile.avatar) {
            avatarEl.innerHTML = `<img src="${escapeHtml(profile.avatar)}" alt="${escapeHtml(profile.username)} avatar">`;
        } else {
            avatarEl.innerHTML = '<i class="fas fa-user" aria-hidden="true"></i>';
        }

        const title = document.querySelector('.profile h1');
        const bio = document.querySelector('.profile p');
        if (title) title.textContent = profile.username;
        if (bio) bio.textContent = profile.bio;
    }

    function renderLinks(filteredLinks) {
        const list = document.getElementById('linksList');
        if (!list) return;

        list.innerHTML = '';

        if (filteredLinks.length === 0) {
            list.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <p>No links found</p>
                </div>
            `;
            return;
        }

        filteredLinks.forEach((link, index) => {
            const item = document.createElement('a');
            item.className = 'link-item';
            item.href = sanitizeUrl(link.url);
            item.target = '_blank';
            item.rel = 'noopener noreferrer';
            item.setAttribute('role', 'listitem');
            item.style.animationDelay = `${(index % 12) * 0.05}s`;
            item.innerHTML = `
                <div class="link-icon"><i class="${escapeHtml(link.icon)}" aria-hidden="true"></i></div>
                <div class="link-content">
                    <h3>${escapeHtml(link.title)}</h3>
                    <p>${escapeHtml(link.description)}</p>
                </div>
                <div class="link-arrow"><i class="fas fa-arrow-right" aria-hidden="true"></i></div>
            `;
            list.appendChild(item);
        });
    }

    function getSortedLinks(items) {
        const sortedItems = [...items];
        if (currentSort === 'title') {
            return sortedItems.sort((a, b) =>
                (a.title || '').localeCompare(b.title || '', undefined, { sensitivity: 'base' })
            );
        }
        if (currentSort === 'newest') {
            return sortedItems.sort(
                (a, b) => Number(b.createdAt || 0) - Number(a.createdAt || 0)
            );
        }
        return sortedItems.sort(
            (a, b) => Number(a.sortOrder || 0) - Number(b.sortOrder || 0)
        );
    }

    function filterLinks() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = (searchInput?.value || '').trim().toLowerCase();

        if (!searchTerm) {
            renderLinks(getSortedLinks(links));
            return;
        }

        const filtered = links.filter(link =>
            (link.title || '').toLowerCase().includes(searchTerm) ||
            (link.description || '').toLowerCase().includes(searchTerm) ||
            (link.url || '').toLowerCase().includes(searchTerm)
        );
        renderLinks(getSortedLinks(filtered));
    }

    function sortLinks() {
        const sortSelect = document.getElementById('sortSelect');
        currentSort = sortSelect ? sortSelect.value : 'default';
        filterLinks();
    }

    // Keep global names so any leftover inline handlers still work
    window.filterLinks = filterLinks;
    window.sortLinks = sortLinks;
    window.renderLinks = renderLinks;
    window.renderProfile = renderProfile;
    window.getSortedLinks = getSortedLinks;

    const profileSection = document.getElementById('profileSection');
    let profileClickCount = 0;
    let profileResetTimer;
    const profileResetDelay = 1000;

    if (profileSection) {
        profileSection.addEventListener('click', () => {
            profileClickCount += 1;
            clearTimeout(profileResetTimer);
            profileResetTimer = setTimeout(() => {
                profileClickCount = 0;
            }, profileResetDelay);

            if (profileClickCount >= 5) {
                clearTimeout(profileResetTimer);
                profileClickCount = 0;
                window.location.href = 'add-project.php';
            }
        });
    }

    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    if (searchInput) searchInput.addEventListener('input', filterLinks);
    if (sortSelect) sortSelect.addEventListener('change', sortLinks);

    renderProfile();
    renderLinks(getSortedLinks(links));
})();
