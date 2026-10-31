import {test, HYVA_THEME} from './lib/csp-inspector';
import {defineCspInspectorTests} from './lib/csp-inspector-tests';

test.describe('CSP inspector (Hyva)', function () {
    defineCspInspectorTests(HYVA_THEME);
});
