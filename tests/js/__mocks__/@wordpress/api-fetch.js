/**
 * Mock for @wordpress/api-fetch.
 *
 * Default export is a vi.fn() that resolves to {}.
 * Tests can override per-test with mockResolvedValue / mockImplementation.
 */

import { vi } from 'vitest';

const apiFetch = vi.fn().mockResolvedValue( {} );

export default apiFetch;
