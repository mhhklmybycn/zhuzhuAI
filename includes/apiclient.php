<?php
/**
 * 猪猪 AI 统一图像与视频 API 客户端
 * 文档：猪猪-ai-openapi.json（OpenAPI 3.0.3）
 * 认证方式：请求头 X-API-Key
 * 注意：所有接口返回包络 {code, message, data}；错误返回 {code, message, http_status}
 */
class PigApi
{
    /** API 基础地址（生产环境） */
    const BASE_URL = 'https://www.pigsec.cn';

    /** @var string API 密钥 */
    private $apiKey;

    /**
     * @param string|null $apiKey 不传则从站点设置中读取
     */
    public function __construct($apiKey = null)
    {
        if ($apiKey === null) {
            $s = site_settings();
            $apiKey = $s['api_key'];
        }
        $this->apiKey = $apiKey;
    }

    /* ---------------- 能力发现 ---------------- */

    /** 获取已启用的图像与视频模型能力列表 */
    public function getModels()
    {
        return $this->request('GET', '/openapi/v1/ai/models');
    }

    /**
     * 计费预览（积分消耗与免费额度）
     * @param array $payload BillingPreviewRequest，字段见文档
     * @return array 含 points_cost、free_available、quote_id、retail_quote 等
     */
    public function billingPreview($payload)
    {
        return $this->request('POST', '/openapi/v1/ai/billing-preview', $payload);
    }

    /* ---------------- 内容生成 ---------------- */

    /**
     * 提交异步图像生成任务
     * @param array       $payload  ImageRequest（mode/prompt/n 必填；retail_pricing 模型须带 quote_id）
     * @param string      $idemKey  幂等键
     * @param array|null  $files     multipart 文件：array('reference_0'=>路径, ..., 'mask'=>路径)；传 null 用 JSON
     */
    public function createImage($payload, $idemKey, $files = null)
    {
        return $this->request('POST', '/openapi/v1/ai/images', $payload, $idemKey, $files);
    }

    /**
     * 提交异步视频生成任务
     * @param array       $payload  VideoRequest
     * @param string      $idemKey  幂等键
     * @param array|null  $files    multipart 文件：first_frame/last_frame/keyframe_N/reference_N
     */
    public function createVideo($payload, $idemKey, $files = null)
    {
        return $this->request('POST', '/openapi/v1/ai/videos', $payload, $idemKey, $files);
    }

    /* ---------------- 任务操作 ---------------- */

    /** 查询任务状态与结算信息 */
    public function getOperation($operationId)
    {
        return $this->request('GET', '/openapi/v1/ai/operations/' . rawurlencode($operationId));
    }

    /**
     * 下载任务结果原始字节到本地文件
     * @return array array('ok'=>bool,'msg'=>'','mime'=>'','bytes'=>0)
     */
    public function downloadResult($operationId, $resultId, $savePath)
    {
        $url = self::BASE_URL . '/openapi/v1/ai/operations/' . rawurlencode($operationId)
             . '/results/' . rawurlencode($resultId) . '/content';
        $ch = curl_init($url);
        $fp = fopen($savePath, 'wb');
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER     => array('X-API-Key: ' . $this->apiKey),
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
        ));
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err  = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if ($code === 200) {
            return array('ok' => true, 'msg' => 'ok', 'mime' => $mime, 'bytes' => filesize($savePath));
        }
        @unlink($savePath);
        return array('ok' => false, 'msg' => $err ? $err : ('HTTP ' . $code), 'mime' => '', 'bytes' => 0);
    }

    /* ---------------- 底层请求 ---------------- */

    /**
     * 统一请求方法
     * @return array 解析后的 JSON 关联数组；网络层失败时返回 array('__http'=>0,'message'=>错误)
     */
    private function request($method, $path, $payload = null, $idemKey = null, $files = null)
    {
        $ch = curl_init(self::BASE_URL . $path);
        $headers = array('X-API-Key: ' . $this->apiKey);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($files !== null) {
                // multipart/form-data：request 字段放 JSON 字符串，文件用 CURLFile
                $post = array('request' => json_encode($payload, JSON_UNESCAPED_UNICODE));
                foreach ($files as $field => $filePath) {
                    $post[$field] = new CURLFile($filePath);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // cURL 自动带 multipart 头
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            }
        }
        if ($idemKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idemKey; // 幂等键：重试不重复扣费
        }

        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HEADER         => false,
        ));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return array('__http' => 0, 'code' => 'network_error', 'message' => $err, 'http_status' => 0);
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return array('__http' => $code, 'code' => 'bad_response', 'message' => '上游返回非 JSON', 'http_status' => $code, 'raw' => $body);
        }
        $json['__http'] = $code;
        return $json;
    }
}
