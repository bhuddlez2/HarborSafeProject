const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000';

export async function apiRequest(path, { method = 'GET', body, errorMessage, ...rest } = {}) {
    const response = await fetch(`${API_URL}${path}`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
        ...rest,
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message || errorMessage || `Request to ${path} failed`);
    }

    return response.json();
}

export { API_URL };
