import { onBeforeUnmount, onMounted, watch } from 'vue';
import { ui } from '../stores/ui';

const DEFAULT_INTERVAL = 30000;

/**
 * Poling data senyap selama halaman aktif & tab terlihat.
 * - Refresh segera saat tab kembali fokus.
 * - Poling berhenti saat tab disembunyikan / auto-refresh dimatikan.
 */
export function useAutoRefresh(fn, interval = DEFAULT_INTERVAL) {
    let timer = null;

    const start = () => {
        stop();
        if (ui.autoRefresh && document.visibilityState !== 'hidden') {
            timer = setInterval(() => {
                if (ui.autoRefresh && document.visibilityState !== 'hidden') {
                    fn();
                }
            }, interval);
        }
    };

    const stop = () => {
        if (timer) clearInterval(timer);
        timer = null;
    };

    const onVisibility = () => {
        if (document.visibilityState === 'visible') {
            if (ui.autoRefresh) fn();
            start();
        } else {
            stop();
        }
    };

    watch(
        () => ui.autoRefresh,
        (value) => (value ? start() : stop())
    );

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibility);
        start();
    });

    onBeforeUnmount(() => {
        stop();
        document.removeEventListener('visibilitychange', onVisibility);
    });
}