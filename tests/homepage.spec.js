import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Homepage', () => {

    test('page loads and has correct title', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/Harbor Safe/i);
    });

    test('hero text is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText(/you are not alone/i)).toBeVisible();
    });

    test('Get Help Now button is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('button', { name: /get help now/i })).toBeVisible();
    });

    test('navbar is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('navigation')).toBeVisible();
    });

    test('navbar has correct links', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('link', { name: /home/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /about/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /get support/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /give support/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /resources/i })).toBeVisible();
    });

    test('EXIT button is visible and clickable', async ({ page }) => {
        await page.goto('/');
        const exitButton = page.getByRole('button', { name: /exit/i });
        await expect(exitButton).toBeVisible();
        await expect(exitButton).toBeEnabled();
    });

    test('site loads on mobile', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText('You are not alone.')).toBeVisible();
        await expect(page.getByRole('button', { name: /exit/i })).toBeVisible();
    });

    // --- ACCESSIBILITY TESTS (axe-core) ---

    test('passes WCAG AA with safety modal open', async ({ page }) => {
        await page.goto('/');
        
        // Wait for the modal/dialog to load in (essential for hydrated apps)
        await page.waitForSelector('[role="dialog"]');

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze();

        expect(results.violations).toEqual([]);
    });

    test('passes WCAG AA after safety modal dismissed', async ({ page }) => {
        await page.goto('/');
        
        // Find the "I understand" button in the modal and click it
        await page.getByText('I understand').click();

        // Run the scan again to ensure the underlying page is accessible
        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze();

        expect(results.violations).toEqual([]);
    });

});