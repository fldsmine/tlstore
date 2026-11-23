// Admin backoffice entry. Livewire 4 bundles Alpine and fires `alpine:init`;
// we only register the two plugins the confirm dialog / toasts / drawers use.
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(focus);
});

// Lock <body> scroll while a modal/drawer marked [data-lock-scroll] is open.
let lockedScrollY = 0;
const syncScrollLock = () => {
    const root = document.documentElement;
    const body = document.body;
    const locked = !!document.querySelector('[data-lock-scroll]');

    if (locked && !root.hasAttribute('data-scroll-locked')) {
        lockedScrollY = window.scrollY || root.scrollTop || 0;
        const scrollbarWidth = window.innerWidth - root.clientWidth;
        root.setAttribute('data-scroll-locked', '');
        body.style.position = 'fixed';
        body.style.top = `-${lockedScrollY}px`;
        body.style.left = '0';
        body.style.right = '0';
        if (scrollbarWidth > 0) body.style.paddingRight = scrollbarWidth + 'px';
    } else if (!locked && root.hasAttribute('data-scroll-locked')) {
        root.removeAttribute('data-scroll-locked');
        body.style.position = '';
        body.style.top = '';
        body.style.left = '';
        body.style.right = '';
        body.style.paddingRight = '';
        window.scrollTo({ top: lockedScrollY, left: 0, behavior: 'instant' });
    }
};
const observer = new MutationObserver(syncScrollLock);
observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['data-lock-scroll'],
});
document.addEventListener('livewire:navigated', syncScrollLock);
