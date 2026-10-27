<?php
/**
 * Ajax：计费预览（代理上游 /openapi/v1/ai/billing-preview）
 * 前端传入与文档 BillingPreviewRequest 一致的 JSON 字段
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_user(true);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['mode']) || !isset($input['n'])) {
    json_response(array('ok' => false, 'msg' => '参数不完整（mode、n 必填）'), 422);
}

// 仅放行文档允许的字段，防止注入未知字段导致 422
$allowed = array('provider_id','model','mode','workflow','resolution','width','height',
                 'duration','aspect_ratio','transitions','reference_image_count',
                 'reference_video_count','reference_audio_count','size','quality','n');
$payload = array_intersect_key($input, array_flip($allowed));

$api  = new PigApi();
$resp = $api->billingPreview($payload);

if (isset($resp['__http']) && $resp['__http'] === 200) {
    json_response(array('ok' => true, 'data' => $resp['data']));
}
json_response(array('ok' => false, 'msg' => isset($resp['message']) ? $resp['message'] : '计费预览失败'),
              isset($resp['__http']) && $resp['__http'] ? $resp['__http'] : 500);
