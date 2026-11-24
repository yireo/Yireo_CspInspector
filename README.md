# CSP Inspector for Magento 2

<!-- badges.specs.start -->
<div class="github-specs-badges">

![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-csp-inspector)

</div>
<!-- badges.specs.end -->

**Simple CLI tool to inspect the current CSP headers of a specified Magento URL and report back the values - because it is too cumbersome to search for values in the browser.**

**Please note that this tool does NOT report issues with those CSP headers, it only inspects the currently generated HTTP headers. Use other tools like SanSec Watch or the M.Academy CSP Generator to fix your CSP headers.**

### Installation
```bash
composer require --dev yireo/magento2-csp-inspector
bin/magento module:enable Yireo_CspInspector
```

### Usage
Report all policies and the mode of the homepage:
```bash
bin/magento csp:inspect
```

Report all policies and the mode of the cart-page:
```bash
bin/magento csp:inspect checkout/cart
```

Report all policy values for the policy `script-src` on the homepage:
```bash
bin/magento csp:inspect:policy script-src 
```

### Options
Both commands support the following options:

- `--insecure` (`-k`): Skip TLS certificate verification, for instance for a local environment with a self-signed certificate. Certificates are verified by default.
- `--follow-redirects`: Follow HTTP redirects (up to 5) and inspect the final URL. By default, redirects are not followed: Instead, the redirect status and location are reported.

When a response contains both an enforced `Content-Security-Policy` header and a `Content-Security-Policy-Report-Only` header, both sets of policies are reported.

## Current status

<!-- badges.test.start -->
<div class="github-pipeline-badges">

![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CspInspector/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CspInspector/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CspInspector/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CspInspector/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CspInspector/compile.yml?label=compile)

</div>
<!-- badges.test.end -->
