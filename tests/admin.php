<?php
require __DIR__ . '/payop.php';
require __DIR__ . '/../admin/controller/payment/payop.php';
require __DIR__ . '/../admin/model/payment/payop.php';
class AdminApi extends \Opencart\Admin\Model\Extension\Payop\Payment\Payop {
    public array $response = ['status' => 200, 'error' => 0, 'body' => '{"data":[{"identifier":101,"title":"Crypto"},{"identifier":"202","title":"Revolut"}]}'];
    public array $requests = [];
    protected function requestMethods(string $url, string $jwt): array { $this->requests[] = [$url, $jwt]; return $this->response; }
}
class AdminHarness extends \Opencart\Admin\Controller\Extension\Payop\Payment\Payop {
    public array $languages = [['language_id' => 1, 'name' => 'English'], ['language_id' => 2, 'name' => 'Ukrainian']];
    public function __construct() {
        $this->config = new Config();
        $this->request = (object)['post' => ['payment_payop_public_id' => 'public', 'payment_payop_secret_key' => 'secret', 'payment_payop_project_id' => 'project/id', 'payment_payop_jwt_token' => 'jwt-test'], 'server' => ['REQUEST_METHOD' => 'POST']];
        $this->config->values['payment_payop_project_id'] = 'project/id';
        $this->model_extension_payop_payment_payop = new AdminApi();
        $this->language = new class { public function get($key) { return $key; } };
        $this->user = new class { public bool $allowed = true; public function hasPermission($permission, $route) { return $this->allowed; } };
        $this->load = new class { public function model($name) {} public function language($name) {} };
        $this->response = new class {
            public array $headers = []; public string $output = '';
            public function addHeader($value) { $this->headers[] = $value; }
            public function setOutput($value) { $this->output = $value; }
        };
    }
    public function submit(array $buttons): bool {
        $this->error = [];
        // Match OpenCart Request::clean, rather than passing unescaped JSON to the controller.
        $this->request->post['payment_payop_buttons_json'] = htmlspecialchars(json_encode((object)$buttons), ENT_COMPAT, 'UTF-8');
        return $this->validate($this->languages);
    }
    public function validateCurrent(): bool { $this->error = []; return $this->validate($this->languages); }
    public function errors(): array { return $this->error; }
}
function adminButton($method = '', $enabled = 1): array {
    return ['enabled' => $enabled, 'integration_type' => $method === '' ? 'hosted_page' : 'payment_method', 'payment_method' => $method,
        'language' => [1 => ['name' => 'Crypto "name" & <test>', 'description' => 'Description &quot; remains literal'], 2 => ['name' => 'Криптовалюта', 'description' => 'Опис']], 'revolut_fields' => 0];
}
$id = 'button_0000000000000001';
$tests = [];
$tests['create, edit, disable and remove multilingual buttons'] = function () use ($id) {
    $h = new AdminHarness(); $button = adminButton('101');
    check($h->submit([$id => $button]), 'Valid button rejected');
    check($h->request->post['payment_payop_buttons'][$id] === $button, 'JSON escaping corrupted translations');
    $h->config->values['payment_payop_buttons'] = [$id => $button];
    $button['language'][1]['name'] = 'Edited'; $button['enabled'] = 0;
    $h->model_extension_payop_payment_payop->response['status'] = 401;
    check($h->submit([$id => $button]), 'Editing unchanged saved method required valid JWT');
    check($h->request->post['payment_payop_buttons'][$id]['enabled'] === 0, 'Disable not saved');
    check($h->submit([]) && $h->request->post['payment_payop_buttons'] === [], 'Deletion not saved');
};
$tests['conditional validation and server method membership'] = function () use ($id) {
    $h = new AdminHarness(); $h->request->post['payment_payop_jwt_token'] = '';
    check($h->submit([$id => adminButton()]), 'Hosted Page requires JWT');
    $bad = adminButton(); $bad['integration_type'] = 'payment_method';
    check(!$h->submit([$id => $bad]), 'Missing method accepted');
    $h = new AdminHarness(); check(!$h->submit([$id => adminButton('999')]), 'Unavailable method accepted');
    $bad = adminButton(); $bad['language'][2]['name'] = '';
    check(!$h->submit([$id => $bad]), 'Missing translated enabled name accepted');
    $bad['enabled'] = 0; check($h->submit([$id => $bad]), 'Disabled draft name required');
    $bad = adminButton('101'); $bad['integration_type'] = 'hosted_page';
    check($h->submit([$id => $bad]) && $h->request->post['payment_payop_buttons'][$id]['payment_method'] === '', 'Hosted Page retained forced method');
};
$tests['invalid JWT/API refresh never overwrites saved settings'] = function () use ($id) {
    foreach ([401, 403, 422, 500] as $status) {
        $h = new AdminHarness(); $original = [$id => adminButton('101')]; $h->config->values['payment_payop_buttons'] = $original;
        $h->model_extension_payop_payment_payop->response['status'] = $status; $h->methods();
        check(json_decode($h->response->output, true)['error'] !== '', 'Missing refresh error');
        check($h->config->values['payment_payop_buttons'] === $original, 'Refresh overwrote buttons');
        check($h->submit($original), 'Existing method could not be saved during API failure');
        check(!$h->submit([$id => adminButton('202')]), 'New method accepted during API failure');
    }
};
$tests['permission and malformed JSON protect settings'] = function () use ($id) {
    $h = new AdminHarness(); $h->user->allowed = false; $h->methods();
    check(!$h->model_extension_payop_payment_payop->requests && str_contains($h->response->headers[1], '403'), 'No-permission API access');
    check(!$h->submit([$id => adminButton()]), 'Unauthorized settings accepted');
    $h = new AdminHarness(); $h->config->values['payment_payop_buttons'] = [$id => adminButton()];
    $h->request->post['payment_payop_buttons_json'] = '{';
    check(!$h->validateCurrent(), 'Malformed JSON accepted');
    check($h->request->post['payment_payop_buttons'] === $h->config->values['payment_payop_buttons'], 'Malformed JSON lost rows');
    unset($h->request->post['payment_payop_buttons_json']);
    check($h->validateCurrent() && $h->request->post['payment_payop_buttons'] === $h->config->values['payment_payop_buttons'], 'Legacy form deleted rows');
};
$tests['method catalogue URL, Bearer auth, empty list and bad responses'] = function () {
    $api = new AdminApi(); $result = $api->getPaymentMethods('project/id', 'jwt-test');
    check($result['methods'] === [['identifier' => '101', 'title' => 'Crypto'], ['identifier' => '202', 'title' => 'Revolut']], 'Catalogue parsing failed');
    check($api->requests === [['https://api.payop.com/v1/instrument-settings/payment-methods/available-for-application/project%2Fid', 'jwt-test']], 'Wrong project endpoint/auth');
    foreach ([['error' => 28], ['body' => '{'], ['body' => '{}'], ['body' => '{"data":[{"identifier":true}]}'], ['status' => 401]] as $bad) {
        $api = new AdminApi(); $api->response = array_replace($api->response, $bad);
        check($api->getPaymentMethods('p', 'j')['error'] !== '', 'Invalid API response accepted');
    }
    $api = new AdminApi(); $api->response['body'] = '{"data":[]}'; check($api->getPaymentMethods('p', 'j')['error'] === '', 'Empty project list rejected');
    $api = new AdminApi(); check($api->getPaymentMethods('p', '')['error'] !== '' && !$api->requests, 'Missing JWT sent to API');
    check($api->getPaymentMethods('p', "a\r\nb")['error'] !== '' && !$api->requests, 'Header injection sent');
};
$tests['settings save preserves existing unrelated module fields'] = function () {
    $h = new AdminHarness();
    $h->document = new class { public function setTitle($title) {} };
    $h->model_localisation_language = new class { public function getLanguages() { return [['language_id' => 1], ['language_id' => 2]]; } };
    $h->model_setting_setting = new class {
        public array $saved = [];
        public function getSetting($code) { return ['payment_payop_geo_zone_id' => 7, 'payment_payop_sort_order' => 3]; }
        public function editSetting($code, $settings) { $this->saved = $settings; }
    };
    $h->url = new class { public function link($route, $args, $secure) { return 'https://store.example/admin'; } };
    $h->session = (object)['data' => ['user_token' => 'test-token']];
    $h->response = new class { public string $redirect = ''; public function redirect($url) { $this->redirect = $url; } };
    $h->request->post['payment_payop_buttons_json'] = '{}';
    $h->index();
    $saved = $h->model_setting_setting->saved;
    check($saved['payment_payop_geo_zone_id'] === 7 && $saved['payment_payop_sort_order'] === 3, 'Saving removed existing settings');
    check($saved['payment_payop_buttons'] === [] && !isset($saved['payment_payop_buttons_json']), 'Wrong stored JSON representation');
    check($h->response->redirect !== '', 'Successful save did not redirect');
};
$tests['large form bypasses max_input_vars with one JSON field'] = function () {
    $h = new AdminHarness(); $buttons = [];
    for ($i = 0; $i < 300; $i++) $buttons['button_' . sprintf('%016x', $i)] = adminButton();
    check($h->submit($buttons) && count($h->request->post['payment_payop_buttons']) === 300, 'Large button list truncated');
};
foreach ($tests as $name => $test) { $test(); echo "PASS $name\n"; }
echo count($tests) . " admin groups passed\n";
