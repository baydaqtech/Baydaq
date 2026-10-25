/** Laravel's XSRF-TOKEN cookie, sent back as a header on JSON requests made outside Inertia. */
function xsrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

  return match ? decodeURIComponent(match[1]) : '';
}

/**
 * POST to a JSON endpoint of the app (multipart when the body is FormData). Resolves with the
 * parsed body; rejects with the server's message for 4xx/5xx answers.
 */
export async function postJson<T>(url: string, body: FormData | Record<string, unknown>): Promise<T> {
  const isForm = body instanceof FormData;
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrfToken(),
      ...(isForm ? {} : { 'Content-Type': 'application/json' }),
    },
    body: isForm ? body : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const firstError = data.errors ? Object.values(data.errors as Record<string, string[]>)[0]?.[0] : undefined;
    throw new Error(firstError ?? data.message ?? response.statusText);
  }

  return data as T;
}
