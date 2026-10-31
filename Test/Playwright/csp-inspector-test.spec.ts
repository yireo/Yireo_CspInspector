import {test, LUMA_THEME} from './lib/csp-inspector';
import {defineCspInspectorTests} from './lib/csp-inspector-tests';

test.describe('CSP inspector (Luma)', function () {
    defineCspInspectorTests(LUMA_THEME);
});
