import { test, expect } from '@playwright/test';

// ─── HELPER: dismiss safety modal if present ──────────────────────────────────
async function dismissModalIfPresent(page) {
    try {
        const modal = page.getByRole('dialog');
        await modal.waitFor({ timeout: 2000 });
        // Try common dismiss patterns
        const dismissButton = page.getByRole('button', { name: /i understand|close|dismiss|continue|got it/i });
        if (await dismissButton.isVisible()) {
            await dismissButton.click();
        }
    } catch {
        // No modal present, continue
    }
}

// ─── HOMEPAGE ────────────────────────────────────────────────────────────────
test.describe('Homepage', () => {

    test('page loads with correct title', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/Harbor Safe/i);
    });

    test('hero text is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText(/you are not alone/i).first()).toBeVisible();
    });

    test('Get Help Now button is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('button', { name: /get help now/i })).toBeVisible();
    });

    test('crisis hotline number is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('link', { name: /call.*476.*3886/i }).first()).toBeVisible();
    });

    test('navbar is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('navigation')).toBeVisible();
    });

    test('navbar has all required links', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('link', { name: /^home$/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /^about$/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /get support/i }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: /give support/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /resources/i }).first()).toBeVisible();
    });

    test('HSHAC logo is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByAltText(/harbor safe house and advocacy center logo/i)).toBeVisible();
    });

    test('survivor stories section is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText(/we are here to help you/i)).toBeVisible();
    });

    test('services section is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText(/comprehensive support for survivors/i)).toBeVisible();
    });

    test('emergency shelter service card is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText(/emergency shelter/i)).toBeVisible();
    });

    test('footer is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('contentinfo')).toBeVisible();
    });

    test('footer crisis hotline is visible', async ({ page }) => {
        await page.goto('/');
        const footer = page.getByRole('contentinfo');
        await expect(footer.getByText(/476.*3886/)).toBeVisible();
    });

    test('social media links are present in footer', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('link', { name: /facebook/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /instagram/i })).toBeVisible();
    });

});

// ─── EXIT BUTTON ─────────────────────────────────────────────────────────────
test.describe('Exit Button (Critical Safety Feature)', () => {

    test('exit button is visible on homepage', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('button', { name: /safe exit|exit/i })).toBeVisible();
    });

    test('exit button is visible on about page', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByRole('button', { name: /safe exit|exit/i })).toBeVisible();
    });

    test('exit button is visible on get support page', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByRole('button', { name: /safe exit|exit/i })).toBeVisible();
    });

    test('exit button is visible on resources page', async ({ page }) => {
        await page.goto('/resources/');
        await expect(page.getByRole('button', { name: /safe exit|exit/i })).toBeVisible();
    });

    test('exit button is enabled on homepage', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('button', { name: /safe exit|exit/i })).toBeEnabled();
    });

    test('exit button navigates away from site', async ({ page }) => {
        await page.goto('/');
        await page.locator('#exit-button').click();
        await expect(page).not.toHaveURL(/localhost|onlinehome|hshac/i);
    });

});

// ─── NAVIGATION ──────────────────────────────────────────────────────────────
test.describe('Navigation', () => {

    test('can navigate to about page from homepage', async ({ page }) => {
        await page.goto('/');
        await dismissModalIfPresent(page);
        await page.getByRole('link', { name: /^about$/i }).click();
        await expect(page).toHaveURL(/\/about/);
        await expect(page.getByRole('heading', { name: /who we are/i })).toBeVisible();
    });

    test('can navigate to get support page from homepage', async ({ page }) => {
        await page.goto('/');
        await dismissModalIfPresent(page);
        await page.getByRole('link', { name: /get support/i }).first().click();
        await expect(page).toHaveURL(/\/get-support/);
        await expect(page.getByRole('heading', { name: /you are not alone/i })).toBeVisible();
    });

    test('can navigate to resources page from homepage', async ({ page }) => {
        await page.goto('/');
        await dismissModalIfPresent(page);
        await page.getByRole('link', { name: /^resources$/i }).click();
        await expect(page).toHaveURL(/\/resources/);
        await expect(page.getByRole('heading', { name: /^resources$/i })).toBeVisible();
    });

    test('can navigate back to homepage from about page', async ({ page }) => {
        await page.goto('/about/');
        await page.getByRole('link', { name: /^home$/i }).click();
        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByText(/you are not alone/i).first()).toBeVisible();
    });

    test('can navigate to get support from about page', async ({ page }) => {
        await page.goto('/about/');
        await page.getByRole('link', { name: /get support/i }).first().click();
        await expect(page).toHaveURL(/\/get-support/);
    });

    test('can navigate to resources from get support page', async ({ page }) => {
        await page.goto('/get-support/');
        await page.getByRole('link', { name: /^resources$/i }).click();
        await expect(page).toHaveURL(/\/resources/);
    });

});

// ─── ABOUT PAGE ──────────────────────────────────────────────────────────────
test.describe('About Page', () => {

    test('page loads correctly', async ({ page }) => {
        await page.goto('/about/');
        await expect(page).toHaveTitle(/Harbor Safe/i);
    });

    test('Who We Are heading is visible', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByRole('heading', { name: /who we are/i })).toBeVisible();
    });

    test('mission statement is visible', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByText(/empowering and supporting survivors/i)).toBeVisible();
    });

    test('FRA partnership section is visible', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByText(/HSHAC operates as a program under Family Resource Agency/i)).toBeVisible();
    });

    test('partners section heading is visible', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByRole('heading', { name: /our partners/i })).toBeVisible();
    });

    test('United Way partner is listed with link', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByRole('link', { name: /united way/i })).toBeVisible();
    });

    test('Tennessee Coalition partner is listed with link', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByRole('link', { name: /tennessee coalition/i })).toBeVisible();
    });

    test('Legal Aid partner is listed with link', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByRole('link', { name: /legal aid/i })).toBeVisible();
    });

    test('The Caring Place partner is listed with link', async ({ page }) => {
        await page.goto('/about/');
        await expect(page.getByRole('link', { name: /the caring place/i })).toBeVisible();
    });

    test('partner links open in new tab', async ({ page }) => {
        await page.goto('/about/');
        const unitedWayLink = page.getByRole('link', { name: /united way/i });
        await expect(unitedWayLink).toHaveAttribute('target', '_blank');
    });

});

// ─── GET SUPPORT PAGE ────────────────────────────────────────────────────────
test.describe('Get Support Page', () => {

    test('page loads correctly', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page).toHaveTitle(/Harbor Safe/i);
    });

    test('You Are Not Alone heading is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByRole('heading', { name: /you are not alone/i })).toBeVisible();
    });

    test('crisis hotline call link is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByRole('link', { name: /call.*476.*3886/i }).first()).toBeVisible();
    });

    test('crisis hotline text link is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByRole('link', { name: /text.*715.*9614/i })).toBeVisible();
    });

    test('Crisis Counseling service is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByText('Crisis Counseling', { exact: true })).toBeVisible();
    });

    test('Support Groups service is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByText('Support Groups', { exact: true })).toBeVisible();
    });

    test('Court Advocacy service is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByText(/court advocacy/i)).toBeVisible();
    });

    test('Community Education service is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByText(/community education/i)).toBeVisible();
    });

    test('Contact Us CTA button is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByRole('link', { name: /contact us/i })).toBeVisible();
    });

    test('Contact Us button opens in new tab', async ({ page }) => {
        await page.goto('/get-support/');
        const contactLink = page.getByRole('link', { name: /contact us/i });
        await expect(contactLink).toHaveAttribute('target', '_blank');
    });

    test('We are here when ready section is visible', async ({ page }) => {
        await page.goto('/get-support/');
        await expect(page.getByText(/we're here when you're ready/i)).toBeVisible();
    });

});

// ─── RESOURCES PAGE ──────────────────────────────────────────────────────────
test.describe('Resources Page', () => {

    test('page loads correctly', async ({ page }) => {
        await page.goto('/resources/');
        await expect(page).toHaveTitle(/Harbor Safe/i);
    });

    test('Resources heading is visible', async ({ page }) => {
        await page.goto('/resources/');
        await expect(page.getByRole('heading', { name: /^resources$/i })).toBeVisible();
    });

    test('category accordions are visible', async ({ page }) => {
        await page.goto('/resources/');
        await expect(page.getByRole('button', { name: /science of hope/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /legal/i }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: /things to know/i })).toBeVisible();
    });

    test('clicking a category expands it', async ({ page }) => {
        await page.goto('/resources/');
        const accordion = page.getByRole('button', { name: /science of hope/i });
        await accordion.click();
        await expect(accordion).toHaveAttribute('aria-expanded', 'true');
    });

    test('clicking an open category closes it', async ({ page }) => {
        await page.goto('/resources/');
        const accordion = page.getByRole('button', { name: /science of hope/i });
        await accordion.click();
        await accordion.click();
        await expect(accordion).toHaveAttribute('aria-expanded', 'false');
    });

    test('Science of Hope section expands', async ({ page }) => {
        await page.goto('/resources/');
        await page.getByRole('button', { name: /science of hope/i }).click();
        await expect(page.getByText(/hope is not pretending/i)).toBeVisible();
    });

    test('Adult Hope Scale heading is visible in Science of Hope', async ({ page }) => {
        await page.goto('/resources/');
        await page.getByRole('button', { name: /science of hope/i }).click();
        await expect(page.getByText('The Adult Hope Scale', { exact: true })).toBeVisible();
    });

    test('Hope Scale calculate button is disabled until all answered', async ({ page }) => {
        await page.goto('/resources/');
        await page.getByRole('button', { name: /science of hope/i }).click();
        await expect(page.getByRole('button', { name: /0 of 8 answered/i })).toBeDisabled();
    });

});

// ─── GIVE SUPPORT PAGE ───────────────────────────────────────────────────────
test.describe('Give Support Page', () => {

    test.skip('page loads correctly — page not yet complete', async ({ page }) => {
        await page.goto('/give-support/');
        await expect(page).toHaveTitle(/Harbor Safe/i);
    });

    test.skip('Give Support heading is visible — page not yet complete', async ({ page }) => {
        await page.goto('/give-support/');
        await expect(page.getByRole('heading', { name: /give support/i })).toBeVisible();
    });

});

// ─── MOBILE ──────────────────────────────────────────────────────────────────
test.describe('Mobile', () => {

    test('homepage loads on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');
        await expect(page.getByText(/you are not alone/i).first()).toBeVisible();
    });

    test('exit button is visible on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');
        await expect(page.locator('#exit-button')).toBeVisible();
    });

    test('HSHAC branding is visible on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');
        // On mobile the centered HSHAC span is shown
        await expect(page.getByText('HSHAC').nth(1)).toBeVisible();
    });

    test('get support page loads on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/get-support/');
        await expect(page.getByRole('heading', { name: /you are not alone/i })).toBeVisible();
    });

    test('resources page loads on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/resources/');
        await expect(page.getByRole('heading', { name: /^resources$/i })).toBeVisible();
    });

});

// ─── 404 PAGE ────────────────────────────────────────────────────────────────
test.describe('404 Page', () => {

    test('shows 404 for unknown routes', async ({ page }) => {
        await page.goto('/this-page-does-not-exist/');
        await expect(page.getByText(/404|not found/i)).toBeVisible();
    });

});
