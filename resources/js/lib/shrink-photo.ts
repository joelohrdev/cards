/**
 * Longest edge kept for card photos. The server converts to WebP at this size,
 * so anything larger would only slow the upload down.
 */
export const CARD_PHOTO_MAX_EDGE = 2000;

const PASSTHROUGH_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Scale a photo down to maxEdge on its longest side and re-encode it as JPEG,
 * so a camera photo of several megabytes uploads as a few hundred kilobytes.
 *
 * JPEG rather than WebP because Safari on iPad cannot encode WebP from a canvas
 * and silently returns a much larger PNG; the server does the WebP conversion.
 * Small photos already in a web format, and anything the browser cannot decode,
 * are returned unchanged.
 */
export async function shrinkPhoto(
    file: File,
    maxEdge: number = CARD_PHOTO_MAX_EDGE,
): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(
            1,
            maxEdge / Math.max(bitmap.width, bitmap.height),
        );

        if (
            scale === 1 &&
            file.size < 1_000_000 &&
            PASSTHROUGH_TYPES.includes(file.type)
        ) {
            bitmap.close();

            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas
            .getContext('2d')
            ?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.85),
        );

        if (!blob || blob.size >= file.size) {
            return file;
        }

        const name = `${file.name.replace(/\.[^.]+$/, '') || 'card'}.jpg`;

        return new File([blob], name, { type: 'image/jpeg' });
    } catch {
        return file;
    }
}
