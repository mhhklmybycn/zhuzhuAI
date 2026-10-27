<?php
/**
 * Ajax：获取模型能力列表（代理上游 /openapi/v1/ai/models）
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_user(true);

$api  = new PigApi();
$resp = $api->getModels();

if (isset($resp['__http']) && $resp['__http'] === 200) {
    json_response(array('ok' => true, 'data' => $resp['data']));
}
json_response(array('ok' => false, 'msg' => isset($resp['message']) ? $resp['message'] : '模型列表获取失败'),
              isset($resp['__http']) && $resp['__http'] ? $resp['__http'] : 500);
