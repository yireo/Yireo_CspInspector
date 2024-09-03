# CSP Inspector for Magento 2
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
