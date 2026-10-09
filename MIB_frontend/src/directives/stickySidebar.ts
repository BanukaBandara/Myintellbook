import type { Directive } from 'vue';

/**
 * v-sticky-sidebar: two-way sticky sidebar (LinkedIn / X desktop style).
 *
 * The sidebar is `position: sticky` and this directive moves its `top` with the scroll:
 *   top = clamp(top - scrollDelta, viewportHeight - sidebarHeight - bottomGap, topGap)
 *
 * - Scrolling down, `top` drops as fast as the page moves, so the sidebar scrolls with the feed
 *   until its bottom edge reaches the viewport bottom; there `top` hits its minimum and it locks.
 * - Scrolling up, `top` rises until it reaches `topGap`, so the sidebar travels back until its
 *   top edge reaches the viewport top, then locks there.
 * - A sidebar shorter than the viewport simply sticks at the top (the minimum equals topGap).
 *
 * It works against the nearest scrolling ancestor (here .app-content-scroll), or the window.
 * Requirements on the layout: no overflow on ancestors between the sidebar and that scroller,
 * and the row must not stretch the sidebar (align-items: flex-start / Bootstrap align-items-start).
 */
type Gaps = { top?: number; bottom?: number };
type Scroller = HTMLElement | Window;

const cleanups = new WeakMap<HTMLElement, () => void>();

function findScroller(el: HTMLElement): Scroller {
    for (let node = el.parentElement; node; node = node.parentElement) {
        if (/(auto|scroll|overlay)/.test(getComputedStyle(node).overflowY)) return node;
    }
    return window;
}

export const vStickySidebar: Directive<HTMLElement, Gaps | undefined> = {
    mounted(el, binding) {
        const topGap = binding.value?.top ?? 20;
        const bottomGap = binding.value?.bottom ?? 20;
        const scroller = findScroller(el);
        const isWindow = scroller === window;
        const scrollTop = () => (isWindow ? window.scrollY : (scroller as HTMLElement).scrollTop);
        const viewportHeight = () => (isWindow ? window.innerHeight : (scroller as HTMLElement).clientHeight);

        el.style.position = 'sticky';
        el.style.alignSelf = 'flex-start';

        let top = topGap;
        let lastScrollTop = scrollTop();
        let frame = 0;

        const apply = (delta: number) => {
            // Hidden (e.g. d-none on small screens): nothing to position.
            if (el.offsetParent === null && !isWindow) return;
            const minTop = Math.min(topGap, viewportHeight() - el.offsetHeight - bottomGap);
            top = Math.min(topGap, Math.max(minTop, top - delta));
            el.style.top = `${Math.round(top)}px`;
        };

        const onScroll = () => {
            if (frame) return;
            frame = requestAnimationFrame(() => {
                frame = 0;
                const current = scrollTop();
                apply(current - lastScrollTop);
                lastScrollTop = current;
            });
        };

        // Content loading in, or the window resizing, changes the limits: re-clamp without moving.
        const resizeObserver = new ResizeObserver(() => apply(0));
        resizeObserver.observe(el);
        if (!isWindow) resizeObserver.observe(scroller as HTMLElement);

        scroller.addEventListener('scroll', onScroll, { passive: true });
        apply(0);

        cleanups.set(el, () => {
            scroller.removeEventListener('scroll', onScroll);
            resizeObserver.disconnect();
            if (frame) cancelAnimationFrame(frame);
        });
    },

    unmounted(el) {
        cleanups.get(el)?.();
        cleanups.delete(el);
    },
};
