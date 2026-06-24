<script setup lang="ts">
/**
 * MobileQr — renders a client-side QR code encoding the app's public upload URL.
 *
 * The URL is derived from the `appUrl` Inertia shared prop injected by
 * HandleInertiaRequests. A phone scanning this QR opens the same upload page
 * on their device (standalone flow — phone uploads via camera, sees its own result).
 *
 * No LAN-IP detection: the app is served from a public HTTPS domain so APP_URL
 * is the canonical upload URL.
 *
 * REQ-005 / UR-001
 */
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import QRCode from 'qrcode';

// ---------------------------------------------------------------------------
// Shared props
// ---------------------------------------------------------------------------

const page = usePage<{ appUrl?: string }>();

const uploadUrl = computed<string>(() => page.props.appUrl ?? window.location.origin);

// ---------------------------------------------------------------------------
// Canvas ref + QR generation
// ---------------------------------------------------------------------------

const canvasRef = ref<HTMLCanvasElement | null>(null);
const hasError = ref(false);

async function renderQr(): Promise<void> {
    if (!canvasRef.value) {
        return;
    }

    try {
        await QRCode.toCanvas(canvasRef.value, uploadUrl.value, {
            width: 140,
            margin: 1,
            color: {
                dark: '#1e293b', // --color-primary (Deep Slate)
                light: '#ffffff',
            },
            errorCorrectionLevel: 'M',
        });
    } catch {
        hasError.value = true;
    }
}

onMounted(renderQr);
</script>

<template>
    <aside
        class="flex flex-col items-center gap-3 rounded-xl border border-outline-variant bg-surface-lowest px-5 py-5 text-center"
        aria-label="Mobile upload via QR code"
    >
        <!-- QR canvas or error fallback -->
        <div
            class="flex h-[140px] w-[140px] items-center justify-center rounded-lg bg-white"
            aria-hidden="true"
        >
            <canvas
                v-if="!hasError"
                ref="canvasRef"
                role="img"
                :aria-label="`QR code for ${uploadUrl}`"
            />
            <span
                v-else
                class="font-mono text-[11px] uppercase tracking-[0.05em] text-on-surface-variant"
            >
                QR unavailable
            </span>
        </div>

        <!-- Label -->
        <p class="text-sm font-medium text-on-surface">
            Scan to use your phone
        </p>
        <p class="font-mono text-[11px] uppercase tracking-[0.05em] text-outline">
            Upload via camera · same page
        </p>
    </aside>
</template>
