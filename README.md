# CSP Inspector for Magento 2
**Simple CLI tool to inspect the CSP headers of a specified Magento URL and report back the values - because it is too cumbersome to search for values in the browser**

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
