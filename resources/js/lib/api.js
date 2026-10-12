export async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const error = new Error(`HTTP ${response.status}`);
        error.status = response.status;

        throw error;
    }

    return response.json();
}

/**
 * Loads an image URL into memory first so an <img> swap never flashes.
 * Resolves with the same url when the frame could not be fetched.
 */
export function preloadImage(url) {
    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve(url);
        image.onerror = () => resolve(null);
        image.src = url;
    });
}
