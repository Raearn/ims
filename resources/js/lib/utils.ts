import type { Updater } from '@tanstack/vue-table';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { Ref } from 'vue';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function valueUpdater<T extends Updater<any>>(updaterOrValue: T, ref: Ref) {
    ref.value = typeof updaterOrValue === 'function' ? updaterOrValue(ref.value) : updaterOrValue;
}

/**
 * Compress an image file client-side using the Canvas API.
 * - Scales down to maxDimension on the longest side if needed.
 * - Exports as WebP (better compression than JPEG for all input formats).
 * - Returns the original file unchanged for GIFs (animation would be lost)
 *   or when the compressed output is larger than the original.
 */
export async function compressImage(
    file: File,
    { maxDimension = 1920, quality = 0.82 }: { maxDimension?: number; quality?: number } = {},
): Promise<File> {
    if (file.type === 'image/gif') return file;

    return new Promise((resolve) => {
        const img = new Image();
        const url = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(url);

            let { width, height } = img;
            if (width > maxDimension || height > maxDimension) {
                const ratio = maxDimension / Math.max(width, height);
                width = Math.round(width * ratio);
                height = Math.round(height * ratio);
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                resolve(file);
                return;
            }

            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (!blob || blob.size >= file.size) {
                        resolve(file);
                        return;
                    }
                    const baseName = file.name.replace(/\.[^.]+$/, '');
                    resolve(new File([blob], `${baseName}.webp`, { type: 'image/webp', lastModified: Date.now() }));
                },
                'image/webp',
                quality,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(file);
        };
        img.src = url;
    });
}
