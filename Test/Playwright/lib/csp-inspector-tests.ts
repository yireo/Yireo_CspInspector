import {
    test,
    expect,
    configureCsp,
    uniquePath,
    openPage,
    getBrowserPolicies,
    inspect,
    inspectPolicy,
    runCli,
    isNonce,
    CspMode,
} from './csp-inspector';

/**
 * Shared test cases for Luma and Hyva, comparing the CSP headers in the browser with the output of the CLI commands
 */
export function defineCspInspectorTests(theme: string) {
    const modes: Array<{mode: CspMode, label: string}> = [
        {mode: 'strict', label: 'Strict Mode'},
        {mode: 'report-only', label: 'Reporting Mode'},
    ];

    for (const {mode, label} of modes) {
        test(`csp:inspect reports the ${mode} policies of the homepage`, async function ({page, baseURL}) {
            await configureCsp(page, theme, mode);
            const path = uniquePath('');
            const browserPolicies = await getBrowserPolicies(await openPage(page, path), mode);

            const result = inspect(path);
            expect(new URL(result.url).host).toBe(new URL(baseURL!).host);
            expect(result.mode).toBe(label);
            expect(result.policies.sort()).toEqual(Object.keys(browserPolicies).sort());
        });
    }

    test('csp:inspect reports the policies of a custom path', async function ({page}) {
        await configureCsp(page, theme, 'strict');
        const path = uniquePath('checkout/cart');
        const browserPolicies = await getBrowserPolicies(await openPage(page, path), 'strict');

        const result = inspect(path);
        expect(new URL(result.url).pathname).toBe('/checkout/cart');
        expect(result.mode).toBe('Strict Mode');
        expect(result.policies.sort()).toEqual(Object.keys(browserPolicies).sort());
    });

    test('csp:inspect:policy reports the script-src values of the homepage', async function ({page}) {
        await configureCsp(page, theme, 'strict');
        const path = uniquePath('');
        const browserPolicies = await getBrowserPolicies(await openPage(page, path), 'strict');
        expect(browserPolicies['script-src'], 'script-src in browser').toBeDefined();

        const rows = inspectPolicy('script-src', path).filter(row => row.value !== '');
        expect(rows.length).toBeGreaterThan(0);

        for (const row of rows) {
            if (row.value.startsWith("'sha256-")) {
                expect(row.type, row.value).toBe('Hash');
            } else if (row.value.includes("'")) {
                expect(row.type, row.value).toBe('Generic');
            } else {
                expect(row.type, row.value).toBe('Domain');
            }
        }

        const stableValues = (values: string[]) => values
            .filter(value => !isNonce(value) && !value.startsWith("'sha256-"))
            .sort();
        expect(stableValues(rows.map(row => row.value))).toEqual(stableValues(browserPolicies['script-src']));
    });

    test('csp:inspect:policy fails for an unknown policy', async function ({page}) {
        await configureCsp(page, theme, 'strict');
        const path = uniquePath('');

        const result = runCli('csp:inspect:policy', 'foo-src', path);
        expect(result.status).not.toBe(0);
        expect(result.output).toContain('Policy "foo-src" not found');
    });
}
