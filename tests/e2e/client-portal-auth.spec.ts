import {
    createAndLogInClient,
    dismissCookieConsent,
    loginWithEmailPassword,
    type PortalClient,
} from './client-portal-helpers';
import { getCompany } from './api-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createMagicLoginUrl,
    getContactPasswordResetToken,
} from './portal-auth-helpers';

test.describe('Client portal authentication', () => {
    test('logs in with email and password when portal passwords are enabled', async ({
        api,
        page,
    }) => {
        const marker = uniqueName('password-login');
        const client = await api.createEntity<PortalClient>('clients', {
            name: marker,
            settings: {
                enable_client_portal: true,
                enable_client_portal_password: true,
            },
            contacts: [
                {
                    first_name: 'Password',
                    last_name: 'Login',
                    email: `${marker}@example.test`,
                    password: 'Portal123!',
                },
            ],
        });

        await loginWithEmailPassword(
            page,
            client.contacts[0].email,
            'Portal123!',
        );

        await expect(page).toHaveURL(/\/client\/(dashboard|invoices)(?:\/|$)/);
        await expect(page.locator('main')).toBeVisible();
    });

    test('shows validation errors for invalid login credentials', async ({
        page,
    }) => {
        await page.goto('/client/login');
        await page.locator('#email').fill('missing-user@example.test');
        await page.locator('#password').fill('wrong-password');
        await page.locator('#loginBtn').click();

        await expect(page).toHaveURL(/\/client\/login(?:\/|$|\?)/);
        await expect(page.locator('.validation-fail')).toBeVisible();
    });

    test('renders forgot password link on the login page', async ({ page }) => {
        await page.goto('/client/login');
        await dismissCookieConsent(page);
        await expect(
            page.getByRole('link', { name: 'Forgot your password?' }),
        ).toHaveAttribute('href', /\/client\/password\/reset/);
    });

    test('completes password reset using the contact reset token', async ({
        api,
        page,
    }) => {
        test.setTimeout(90_000);

        const marker = uniqueName('password-reset');
        const email = `${marker}@example.test`;
        const client = await api.createEntity<PortalClient>('clients', {
            name: marker,
            settings: {
                enable_client_portal: true,
                enable_client_portal_password: true,
            },
            contacts: [
                {
                    first_name: 'Reset',
                    last_name: 'User',
                    email,
                    password: 'Original123!',
                },
            ],
        });

        await page.goto('/client/password/reset');
        await dismissCookieConsent(page);
        await page.locator('#email').fill(email);
        await page.getByRole('button', { name: /Next step|Send/i }).click();
        await expect(page.locator('main, body')).toBeVisible();

        let token: string;
        try {
            token = getContactPasswordResetToken(email);
        } catch (error) {
            test.skip(
                true,
                `Password reset token helper unavailable: ${(error as Error).message}`,
            );
            return;
        }

        await page.goto(
            `/client/password/reset/${token}?email=${encodeURIComponent(email)}`,
        );
        await expect(page.locator('#password')).toBeVisible();
        await page.locator('#password').fill('NewPortal123!');
        await page.locator('#password_confirmation').fill('NewPortal123!');
        await page.getByRole('button', { name: /Complete|Reset/i }).click();

        await loginWithEmailPassword(page, email, 'NewPortal123!');
        await expect(page).toHaveURL(/\/client\/(dashboard|invoices)(?:\/|$)/);
        expect(client.id).toBeTruthy();
    });

    test('logs in through a magic link', async ({ api, page }) => {
        test.setTimeout(90_000);

        const marker = uniqueName('magic-link');
        const email = `${marker}@example.test`;
        await api.createEntity<PortalClient>('clients', {
            name: marker,
            settings: {
                enable_client_portal: true,
                enable_client_portal_password: false,
            },
            contacts: [
                {
                    first_name: 'Magic',
                    last_name: 'Link',
                    email,
                },
            ],
        });

        let magicUrl: string;
        try {
            magicUrl = createMagicLoginUrl(email);
        } catch (error) {
            test.skip(
                true,
                `Magic link helper unavailable: ${(error as Error).message}`,
            );
            return;
        }

        const response = await page.goto(magicUrl);
        expect(response?.ok()).toBe(true);
        await expect(page).toHaveURL(/\/client\/(dashboard|invoices)(?:\/|$)/);
        await expect(page.locator('main')).toBeVisible();
    });

    test('shows validation errors for missing required registration fields', async ({
        companyGuard: company_guard,
        page,
    }) => {
        const company = await company_guard.update({ client_can_register: true });
        await page.goto(`/client/register/${company.company_key}`);
        await dismissCookieConsent(page);
        await expect(page.locator('#register-form')).toBeVisible();

        if (await page.locator('.cf-turnstile').count()) {
            test.skip(true, 'Cloudflare Turnstile widget is present on registration');
        }

        await page.locator('#register-form button.button-primary').click();
        await expect(page.locator('#register-form .validation-fail')).toHaveCount(4);
        await expect(page.locator('#first_name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
    });

    test('registers a new client when self-registration is enabled', async ({
        api,
        companyGuard,
        page,
    }) => {
        test.setTimeout(90_000);

        const company = await companyGuard.update({ client_can_register: true });
        const marker = uniqueName('self-register');
        const email = `${marker}@gmail.com`;

        await page.goto(`/client/register/${company.company_key}`);
        await dismissCookieConsent(page);
        await expect(page.locator('#register-form')).toBeVisible();

        if (await page.locator('.cf-turnstile').count()) {
            test.skip(true, 'Cloudflare Turnstile widget is present on registration');
        }

        await page.locator('#first_name').fill('Self');
        await page.locator('#last_name').fill('Register');
        if (await page.locator('#name').isVisible().catch(() => false)) {
            await page.locator('#name').fill(`${marker}-co`);
        }
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('PortalRegister123!');
        await page.locator('#password_confirmation').fill('PortalRegister123!');

        await page.locator('#register-form button.button-primary').click();

        if (await page.getByText(/Captcha failed/i).isVisible().catch(() => false)) {
            test.skip(true, 'Cloudflare Turnstile captcha is enabled on registration');
        }

        await expect(page).toHaveURL(/\/client\/(dashboard|invoices)(?:\/|$)/, {
            timeout: 30_000,
        });
        await expect(page.getByRole('main')).toBeVisible();

        const registered_response = await api.context.request.get(`/api/v1/clients?email=${encodeURIComponent(email)}`);
        expect(registered_response.ok()).toBe(true);
        const registered_clients = (await registered_response.json()).data as PortalClient[];
        for (const registered_client of registered_clients) {
            api.trackEntity('clients', registered_client.id);
        }
        expect(registered_clients).toHaveLength(1);
        expect(registered_clients[0].contacts[0].email).toBe(email);

        // Ensure company API still works after registration.
        await expect(getCompany(api.context)).resolves.toBeTruthy();
    });
});
