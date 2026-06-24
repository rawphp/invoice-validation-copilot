<script setup lang="ts">
/**
 * Upload — the invoice upload page.
 *
 * Renders inside AppLayout. Provides:
 *   • Drag-and-drop zone accepting PDF/PNG/JPG.
 *   • File-picker fallback (hidden <input> triggered by click).
 *   • Client-side type/size feedback BEFORE submit.
 *   • "Validate" primary button that posts the file to POST /validate
 *     via Inertia useForm (forceFormData), showing a loading state while
 *     the pipeline runs.
 *
 * REQ-005 will add a MobileQr component to this page and inject APP_URL
 * via the Inertia middleware — leave the layout open but do NOT build the
 * QR here.
 */
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MobileQr from '@/Components/MobileQr.vue';
import {
    ACCEPTED_MIME_TYPES,
    ACCEPTED_EXTENSIONS,
    MAX_FILE_SIZE_BYTES,
    MAX_FILE_SIZE_LABEL,
} from '@/types/invoice';

// ---------------------------------------------------------------------------
// File state
// ---------------------------------------------------------------------------

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const clientError = ref<string | null>(null);

const form = useForm<{ file: File | null }>({
    file: null,
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function validateFile(file: File): string | null {
    const acceptedTypes = ACCEPTED_MIME_TYPES as readonly string[];

    if (!acceptedTypes.includes(file.type)) {
        return 'Unsupported file type. Please upload a PDF, PNG, or JPEG.';
    }

    if (file.size > MAX_FILE_SIZE_BYTES) {
        return `File is too large. Maximum size is ${MAX_FILE_SIZE_LABEL}.`;
    }

    return null;
}

function selectFile(file: File): void {
    const error = validateFile(file);
    clientError.value = error;

    if (error === null) {
        form.file = file;
    } else {
        form.file = null;
    }
}

function clearFile(): void {
    form.file = null;
    clientError.value = null;

    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

// ---------------------------------------------------------------------------
// Computed
// ---------------------------------------------------------------------------

const hasFile = computed(() => form.file !== null);

const fileLabel = computed(() =>
    form.file ? `${form.file.name} (${formatBytes(form.file.size)})` : null,
);

const canSubmit = computed(
    () => hasFile.value && !form.processing && clientError.value === null,
);

// ---------------------------------------------------------------------------
// Event handlers
// ---------------------------------------------------------------------------

function onFileInputChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (file) {
        selectFile(file);
    }
}

function onDragEnter(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = true;
}

function onDragOver(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = true;
}

function onDragLeave(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = false;
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = false;

    const file = event.dataTransfer?.files?.[0] ?? null;

    if (file) {
        selectFile(file);
    }
}

function openFilePicker(): void {
    fileInputRef.value?.click();
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.post('/validate', {
        forceFormData: true,
    });
}

// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <Head title="Upload Invoice" />

    <AppLayout
        title="Upload Invoice"
        subtitle="Upload an Australian invoice to extract fields, validate, and generate an explanation."
    >
        <div class="mx-auto max-w-2xl">
            <!-- Drop zone -->
            <div
                class="relative rounded-xl border-2 transition-colors duration-150"
                :class="[
                    isDragging
                        ? 'border-secondary bg-indigo-50'
                        : hasFile
                          ? 'border-success bg-success-50'
                          : 'border-outline-variant bg-surface-lowest',
                ]"
                @dragenter="onDragEnter"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
            >
                <!-- Hidden file input -->
                <input
                    ref="fileInputRef"
                    type="file"
                    :accept="ACCEPTED_EXTENSIONS"
                    class="sr-only"
                    aria-label="Select invoice file"
                    @change="onFileInputChange"
                />

                <!-- Drop zone body -->
                <button
                    type="button"
                    class="flex w-full cursor-pointer flex-col items-center gap-4 px-8 py-12 text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2"
                    :class="{ 'cursor-default': hasFile }"
                    :disabled="form.processing"
                    aria-label="Click to browse for an invoice file, or drag and drop here"
                    @click="openFilePicker"
                >
                    <!-- Icon -->
                    <span
                        class="flex h-14 w-14 items-center justify-center rounded-full"
                        :class="[
                            isDragging
                                ? 'bg-secondary text-on-secondary'
                                : hasFile
                                  ? 'bg-success text-on-success'
                                  : 'bg-surface-container text-on-surface-variant',
                        ]"
                        aria-hidden="true"
                    >
                        <!-- Upload icon -->
                        <svg
                            v-if="!hasFile"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
                            />
                        </svg>

                        <!-- Checkmark icon when file selected -->
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.75"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4.5 12.75l6 6 9-13.5"
                            />
                        </svg>
                    </span>

                    <!-- Text content -->
                    <div class="space-y-1">
                        <p
                            v-if="!hasFile"
                            class="text-sm font-medium text-on-surface"
                        >
                            <span class="text-secondary underline">Click to browse</span>
                            &nbsp;or drag and drop
                        </p>
                        <p
                            v-else
                            class="break-all text-sm font-medium text-success"
                        >
                            {{ fileLabel }}
                        </p>
                        <p class="font-mono text-[12px] uppercase tracking-[0.05em] text-on-surface-variant">
                            PDF · PNG · JPEG &nbsp;·&nbsp; Max {{ MAX_FILE_SIZE_LABEL }}
                        </p>
                    </div>
                </button>

                <!-- Clear button -->
                <button
                    v-if="hasFile && !form.processing"
                    type="button"
                    class="absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-high hover:text-on-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary"
                    aria-label="Remove selected file"
                    @click.stop="clearFile"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Client-side error message -->
            <p
                v-if="clientError"
                role="alert"
                class="mt-3 flex items-center gap-2 rounded-md bg-error-container px-4 py-3 text-sm text-on-error-container"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                    />
                </svg>
                {{ clientError }}
            </p>

            <!-- Server-side validation error (Inertia) -->
            <p
                v-if="form.errors.file"
                role="alert"
                class="mt-3 flex items-center gap-2 rounded-md bg-error-container px-4 py-3 text-sm text-on-error-container"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                    />
                </svg>
                {{ form.errors.file }}
            </p>

            <!-- Submit button -->
            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-6 py-3 font-semibold text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="[
                        canSubmit
                            ? 'bg-secondary text-on-secondary hover:bg-secondary-container'
                            : 'bg-surface-high text-on-surface-variant',
                    ]"
                    :disabled="!canSubmit"
                    :aria-busy="form.processing"
                    @click="submit"
                >
                    <!-- Spinner during processing -->
                    <svg
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>
                    {{ form.processing ? 'Validating…' : 'Validate' }}
                </button>
            </div>

            <!-- Accepted format hint -->
            <p class="mt-4 text-center font-mono text-[11px] uppercase tracking-[0.05em] text-outline">
                Australian GST invoices only &nbsp;·&nbsp; AU · GST
            </p>

            <!-- Mobile QR — scan to open the upload page on your phone -->
            <div class="mt-8 flex justify-center">
                <MobileQr />
            </div>
        </div>
    </AppLayout>
</template>
