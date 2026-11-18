/**
 * DANATRAIL ULTRA-FAST SPECULATIVE PREFETCHER & PROGRESS BAR
 * - Native Speculation Rules API (prerenders in background)
 * - Predictive Hover/Touch Prefetching (loads page HTML into browser cache on hover 40ms)
 * - Clean native navigation (0ms from cache) - preserves 100% of all CSS styles & scripts!
 * - Top shimmer progress bar on navigation
 */

(function () {
    'use strict';

    if (window.__DanatrailInstantNavLoaded) return;
    window.__DanatrailInstantNavLoaded = true;

    // 1. Native Speculation Rules API (Chrome/Edge 108+)
    if (HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules')) {
        const specScript = document.createElement('script');
        specScript.type = 'speculationrules';
        specScript.textContent = JSON.stringify({
            prefetch: [
                {
                    source: "document",
                    where: {
                        and: [
                            { href_matches: "/*" },
                            { not: { href_matches: "/*logout*" } },
                            { not: { href_matches: "/*_aksi.php*" } },
                            { not: { href_matches: "/*.mp4*" } },
                            { not: { href_matches: "/*.pdf*" } }
                        ]
                    },
                    eagerness: "moderate"
                }
            ]
        });
        document.head.appendChild(specScript);
    }

    // 2. Prefetch Cache Map
    const prefetchedUrls = new Set();

    function isEligibleLink(anchor) {
        if (!anchor || anchor.tagName !== 'A') return false;
        if (anchor.hasAttribute('download')) return false;
        if (anchor.getAttribute('target') && anchor.getAttribute('target') !== '_self') return false;
        
        const href = anchor.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return false;
        }

        if (href.includes('_aksi.php') || href.includes('logout=1') || href.endsWith('.pdf') || href.endsWith('.mp4')) {
            return false;
        }

        try {
            const url = new URL(anchor.href, window.location.href);
            return url.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function prefetchUrl(url) {
        const cleanUrl = url.split('#')[0];
        if (prefetchedUrls.has(cleanUrl)) return;
        prefetchedUrls.add(cleanUrl);

        // Gunakan link prefetch tag jika didukung browser
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = cleanUrl;
        link.as = 'document';
        document.head.appendChild(link);
    }

    // Hover 35ms intent & touchstart prefetch
    let hoverTimer = null;
    document.addEventListener('mouseover', function (e) {
        const anchor = e.target.closest('a');
        if (isEligibleLink(anchor)) {
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => {
                prefetchUrl(anchor.href);
            }, 35);
        }
    }, { passive: true });

    document.addEventListener('touchstart', function (e) {
        const anchor = e.target.closest('a');
        if (isEligibleLink(anchor)) {
            prefetchUrl(anchor.href);
        }
    }, { passive: true });

    // 3. Top Progress Bar on Link Click
    let progressBar = document.getElementById('instant-nav-progress');
    if (!progressBar) {
        progressBar = document.createElement('div');
        progressBar.id = 'instant-nav-progress';
        document.body.appendChild(progressBar);
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        const anchor = e.target.closest('a');
        if (isEligibleLink(anchor)) {
            const href = anchor.getAttribute('href');
            if (href && !href.startsWith('#')) {
                if (progressBar) {
                    progressBar.classList.remove('done');
                    progressBar.classList.add('active');
                    progressBar.style.width = '75%';
                }
            }
        }
    });

    window.addEventListener('pageshow', function () {
        if (progressBar) {
            progressBar.style.width = '100%';
            setTimeout(() => {
                progressBar.classList.add('done');
                setTimeout(() => {
                    progressBar.classList.remove('active', 'done');
                    progressBar.style.width = '0%';
                }, 200);
            }, 50);
        }
    });

})();
