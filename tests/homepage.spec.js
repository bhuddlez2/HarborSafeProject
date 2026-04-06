import { test, expect } from '@playwright/test';
import { skip } from 'node:test';

test.describe('Homepage', () => {

    test('page loads and has correct title', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/Harbor Safe/i);
    });

    test.skip('hero text is visible', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText(/you are not alone/i)).toBeVisible();
    });
    test.skip('Get Help Now button is visible', async ({ page }) => {
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

    test.skip('site loads on mobile', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText('You are not alone.')).toBeVisible();
        await expect(page.getByRole('button', { name: /exit/i })).toBeVisible();
    });

})