import {test, expect, Page, Response} from '@playwright/test';
import {spawnSync} from 'child_process';
import {existsSync} from 'fs';

export const HYVA_THEME = 'Hyva/default-csp';
export const LUMA_THEME = 'Magento/luma';

export type CspMode = 'strict' | 'report-only';
export type Policies = Record<string, string[]>;
export type CliResult = {status: number, output: string};

const token = () => process.env.TEST_TOKEN;

/**
 * The Loki_FunctionalTests configure endpoint resets the theme to Magento/luma when `theme` is missing,
 * so the theme is passed with every call.
 */
export async function configureCsp(page: Page, theme: string, mode: CspMode) {
    const response = await page.request.post('/loki_functional_tests/index/configure?token=' + token(), {
        form: {
            config: JSON.stringify({
                theme,
                config: {
                    'csp/mode/storefront/report_only': mode === 'report-only' ? 1 : 0,
                },
            }),
        },
    });

    const body = await response.text();
    let data: any;
    try {
        data = JSON.parse(body);
    } catch (error) {
        throw new Error(`Configure endpoint returned no JSON (status ${response.status()}): ${body.slice(0, 500)}`);
    }

    if (data.error) {
        throw new Error('Configure error: ' + data.error);
    }

    const themeErrors: string[] = data.result?.theme?.errors ?? [];
    test.skip(themeErrors.some(error => error.includes('not found')), `Theme "${theme}" is not installed`);
    expect(themeErrors, 'Configure theme errors').toEqual([]);
}

/**
 * Relative path with a unique query string, so neither the browser nor the CLI gets a page from the full page cache
 */
export function uniquePath(path: string): string {
    return path + '?csp_inspector=' + Date.now();
}

export async function openPage(page: Page, path: string): Promise<Response> {
    const response = await page.goto('/' + path);
    expect(response, `Response of /${path}`).not.toBeNull();
    expect(response!.status()).toBe(200);
    return response!;
}

export async function getBrowserPolicies(response: Response, mode: CspMode): Promise<Policies> {
    const headerName = mode === 'report-only' ? 'content-security-policy-report-only' : 'content-security-policy';
    const otherHeaderName = mode === 'report-only' ? 'content-security-policy' : 'content-security-policy-report-only';
    const headers = await response.headersArray();

    expect(headers.filter(header => header.name.toLowerCase() === otherHeaderName), otherHeaderName).toEqual([]);

    const values = headers
        .filter(header => header.name.toLowerCase() === headerName)
        .map(header => header.value);
    expect(values.length, `${headerName} header`).toBeGreaterThan(0);

    const policies: Policies = {};
    for (const directive of values.join(';').split(';')) {
        const parts = directive.trim().split(/\s+/).filter(part => part !== '');
        const name = parts.shift();
        if (name) {
            policies[name] = parts;
        }
    }

    return policies;
}

/**
 * Command to run bin/magento with: MAGENTO_CLI, the PHP container of the GitHub Actions workflow, or the local PHP binary
 */
function getMagentoCli(): string[] | null {
    if (process.env.MAGENTO_CLI) {
        return process.env.MAGENTO_CLI.split(/\s+/).filter(part => part !== '');
    }

    if (process.env.PHP_CONTAINER) {
        return ['docker', 'exec', '-u', 'root', process.env.PHP_CONTAINER, 'php', '/tmp/magento/bin/magento'];
    }

    const magentoRoot = process.env.MAGENTO_ROOT;
    if (magentoRoot && existsSync(magentoRoot + '/bin/magento') && spawnSync('php', ['-v']).status === 0) {
        return ['php', magentoRoot + '/bin/magento'];
    }

    return null;
}

export function runCli(...args: string[]): CliResult {
    const cli = getMagentoCli();
    test.skip(cli === null, 'No way to run bin/magento: set MAGENTO_CLI or MAGENTO_ROOT');

    const [command, ...commandArgs] = cli!;
    const result = spawnSync(command, [...commandArgs, ...args, '--no-ansi'], {encoding: 'utf8', timeout: 60_000});
    if (result.error) {
        throw result.error;
    }

    return {status: result.status ?? 1, output: (result.stdout ?? '') + (result.stderr ?? '')};
}

/**
 * Parse a Symfony Console table into rows, where a row without a first cell continues the previous row
 */
export function parseTable(output: string): string[][] {
    const rows: string[][] = [];
    for (const line of output.split('\n')) {
        const trimmedLine = line.trim();
        if (!trimmedLine.startsWith('|')) {
            continue;
        }

        const cells = trimmedLine.replace(/^\||\|$/g, '').split('|').map(cell => cell.trim());
        if (cells[0] === '' && rows.length > 0) {
            rows[rows.length - 1].push(...cells.slice(1));
            continue;
        }

        rows.push(cells);
    }

    return rows;
}

export function inspect(path: string): {url: string, mode: string, policies: string[]} {
    const result = runCli('csp:inspect', path);
    expect(result.status, result.output).toBe(0);

    const rows = Object.fromEntries(parseTable(result.output).map(([name, ...values]) => [name, values]));
    expect(Object.keys(rows), result.output).toEqual(['URL', 'Mode', 'Policies']);

    return {
        url: rows['URL'][0],
        mode: rows['Mode'][0],
        policies: rows['Policies'].filter(policy => policy !== ''),
    };
}

export function inspectPolicy(policy: string, path: string): Array<{type: string, value: string}> {
    const result = runCli('csp:inspect:policy', policy, path);
    expect(result.status, result.output).toBe(0);

    return parseTable(result.output).map(([type, value]) => ({type, value}));
}

export function isNonce(value: string): boolean {
    return value.startsWith("'nonce-");
}

export {test, expect};
