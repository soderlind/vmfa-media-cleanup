/**
 * Vitest global setup.
 */

import '@testing-library/jest-dom/vitest';

// Provide a minimal window.vmfaMediaCleanup global.
window.vmfaMediaCleanup = {
	adminUrl: '/wp-admin/',
};
