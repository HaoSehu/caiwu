<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Exceptions\BusinessException;
use App\Models\Service;
use App\Models\User;
use App\Services\System\OperationLogService;
use Illuminate\Support\Facades\Cache;

/**
 * 安全组子服务
 * 负责：getSecurityGroupsForUser、getSecurityGroupRulesForUser、createSecurityGroupForUser、
 *       applySecurityGroupForUser、deleteSecurityGroupForUser、createSecurityRuleForUser、
 *       deleteSecurityRuleForUser 及安全组 HTML 解析方法
 */
class ServiceSecurityGroupService
{
    private const SECURITY_GROUP_CONTEXT_CACHE_TTL_SECONDS = 600;

    private const SECURITY_GROUP_MODULE_KEYWORDS = ['安全组', '安全', 'securitygroup', 'security_group', 'security-group', 'security', 'secgroup', 'firewall', 'acl'];

    public function __construct(
        private readonly OperationLogService $operationLogService,
        private readonly ServiceDetailService $detailService,
        private readonly ServiceTransformService $transformService,
        private readonly ServiceNatService $natService,
    ) {}

    public function getSecurityGroupsForUser(User $user, int $serviceId, bool $fresh = false): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.supplier',
        ]);

        if (! $this->transformService->canManageService($service)) {
            return [
                'supported' => false,
                'message' => '当前服务暂不支持安全组管理',
                'error' => '',
                'module_key' => '',
                'module_name' => '',
                'host_type' => '',
                'directions' => [],
                'protocols' => [],
                'groups' => [],
            ];
        }

        try {
            $context = $this->resolveSecurityGroupContext($service, $fresh);

            return [
                'supported' => true,
                'message' => $context['groups'] === [] ? '当前暂无安全组' : '',
                'error' => '',
                'module_key' => $context['module_key'],
                'module_name' => $context['module_name'],
                'host_type' => $context['host_type'],
                'directions' => $context['directions'],
                'protocols' => $context['protocols'],
                'groups' => $context['groups'],
            ];
        } catch (\Throwable $exception) {
            return [
                'supported' => false,
                'message' => '',
                'error' => $exception->getMessage(),
                'module_key' => '',
                'module_name' => '',
                'host_type' => '',
                'directions' => [],
                'protocols' => [],
                'groups' => [],
            ];
        }
    }

    public function getSecurityGroupRulesForUser(User $user, int $serviceId, int $groupId): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.supplier',
        ]);

        $result = $this->callSecurityGroupAction($service, 'showSecurityRules', ['id' => $groupId], '读取安全组规则');
        $payload = $this->detailService->extractPayload($result['response']);
        $raw = $payload['list'] ?? [];
        $list = is_array($raw) ? (is_array($raw['preview'] ?? null) ? $raw['preview'] : array_values(array_filter($raw, 'is_array'))) : [];

        return [
            'group_id' => $groupId,
            'host_type' => $result['context']['host_type'],
            'list' => collect($list)
                ->filter(fn ($item) => is_array($item))
                ->map(fn (array $item) => $this->normalizeSecurityRuleItem($item, $result['context']['host_type']))
                ->values()->all(),
        ];
    }

    public function createSecurityGroupForUser(User $user, int $serviceId, array $data, array $context = []): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.supplier',
        ]);
        $name = trim((string) ($data['name'] ?? ''));
        $this->assertSecurityGroupNameAvailable($service, $name);

        $result = $this->callSecurityGroupAction($service, 'createSecurityGroup', [
            'name' => $name,
            'description' => trim((string) ($data['description'] ?? '')),
        ], '创建安全组');
        $this->forgetSecurityGroupContextCache($service);

        $message = trim((string) ($result['response']['msg'] ?? '')) ?: '安全组创建成功';
        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.security_group.create', [
            'category' => 'security_group',
            'summary' => '创建安全组',
            'host_id' => (int) ($result['context']['host_id'] ?? 0),
            'group_name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'message' => $message,
        ], $context);

        return ['message' => $message];
    }

    public function applySecurityGroupForUser(User $user, int $serviceId, int $groupId, array $context = []): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.supplier',
        ]);

        $result = $this->callSecurityGroupAction($service, 'linkSecurityGroup', ['id' => $groupId], '应用安全组');
        $this->forgetSecurityGroupContextCache($service);

        $message = trim((string) ($result['response']['msg'] ?? '')) ?: '安全组已应用';
        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.security_group.apply', [
            'category' => 'security_group',
            'summary' => '应用安全组',
            'host_id' => (int) ($result['context']['host_id'] ?? 0),
            'group_id' => $groupId,
            'group_name' => $this->resolveSecurityGroupName((array) ($result['context']['groups'] ?? []), $groupId),
            'message' => $message,
        ], $context);

        return ['message' => $message];
    }

    public function deleteSecurityGroupForUser(User $user, int $serviceId, int $groupId, array $context = []): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.supplier',
        ]);

        $result = $this->callSecurityGroupAction($service, 'delSecurityGroup', ['id' => $groupId], '删除安全组');
        $this->forgetSecurityGroupContextCache($service);

        $message = trim((string) ($result['response']['msg'] ?? '')) ?: '安全组已删除';
        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.security_group.delete', [
            'category' => 'security_group',
            'summary' => '删除安全组',
            'host_id' => (int) ($result['context']['host_id'] ?? 0),
            'group_id' => $groupId,
            'group_name' => $this->resolveSecurityGroupName((array) ($result['context']['groups'] ?? []), $groupId),
            'message' => $message,
        ], $context);

        return ['message' => $message];
    }

    public function createSecurityRuleForUser(User $user, int $serviceId, int $groupId, array $data, array $context = []): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.supplier',
        ]);

        $result = $this->callSecurityGroupAction($service, 'createSecurityRule', [
            'id' => $groupId,
            'direction' => trim((string) ($data['direction'] ?? '')),
            'protocol' => trim((string) ($data['protocol'] ?? '')),
            'port' => trim((string) ($data['port'] ?? '')),
            'ip' => trim((string) ($data['ip'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
        ], '创建安全组规则');
        $this->forgetSecurityGroupContextCache($service);

        $message = trim((string) ($result['response']['msg'] ?? '')) ?: '规则创建成功';
        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.security_rule.create', [
            'category' => 'security_rule',
            'summary' => '创建安全组规则',
            'host_id' => (int) ($result['context']['host_id'] ?? 0),
            'group_id' => $groupId,
            'group_name' => $this->resolveSecurityGroupName((array) ($result['context']['groups'] ?? []), $groupId),
            'direction' => trim((string) ($data['direction'] ?? '')),
            'direction_label' => $this->transformService->resolveSelectOptionLabel((array) ($result['context']['directions'] ?? []), trim((string) ($data['direction'] ?? ''))),
            'protocol' => trim((string) ($data['protocol'] ?? '')),
            'protocol_label' => $this->transformService->resolveSelectOptionLabel((array) ($result['context']['protocols'] ?? []), trim((string) ($data['protocol'] ?? ''))),
            'port' => trim((string) ($data['port'] ?? '')),
            'ip' => trim((string) ($data['ip'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'message' => $message,
        ], $context);

        return ['message' => $message];
    }

    public function deleteSecurityRuleForUser(User $user, int $serviceId, int $groupId, int $ruleId, array $context = []): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.supplier',
        ]);

        $result = $this->callSecurityGroupAction($service, 'delSecurityRule', [
            'id' => $ruleId,
            'group' => $groupId,
        ], '删除安全组规则');
        $this->forgetSecurityGroupContextCache($service);

        $message = trim((string) ($result['response']['msg'] ?? '')) ?: '规则已删除';
        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.security_rule.delete', [
            'category' => 'security_rule',
            'summary' => '删除安全组规则',
            'host_id' => (int) ($result['context']['host_id'] ?? 0),
            'group_id' => $groupId,
            'group_name' => $this->resolveSecurityGroupName((array) ($result['context']['groups'] ?? []), $groupId),
            'rule_id' => $ruleId,
            'message' => $message,
        ], $context);

        return ['message' => $message];
    }

    // ── Security group context resolution ─────────────────────────────────

    public function resolveSecurityGroupContext(Service $service, bool $fresh = false): array
    {
        [$supplier, $hostId] = $this->detailService->resolveManagedSupplierAndHost($service);
        $cacheKey = $this->buildSecurityGroupContextCacheKey($supplier->id, $hostId);

        if (! $fresh && ($cached = Cache::get($cacheKey)) && is_array($cached)) {
            $cached['supplier'] = $supplier;
            if (trim((string) ($cached['jwt'] ?? '')) !== '') {
                return $cached;
            }
        }

        $runtime = $this->detailService->resolveRuntimeCapabilityForSupplier($supplier);
        $jwt = $runtime->login($supplier);
        $modules = $this->detailService->fetchSupportedModules($supplier, $hostId, $jwt);
        $module = collect($modules)->first(fn ($item) => is_array($item) && $this->isSecurityGroupModule($item));

        throw_if(! is_array($module), new BusinessException('当前主机未开放安全组模块', 42200));

        $moduleKey = trim((string) ($module['function'] ?? ''));
        $moduleName = trim((string) ($module['name'] ?? '')) ?: '安全组';
        $html = $this->natService->fetchCustomModulePage($runtime, $supplier, $hostId, $jwt, $moduleKey);
        $page = $this->parseSecurityGroupPage($html);

        $context = [
            'supplier' => $supplier,
            'supplier_id' => $supplier->id,
            'host_id' => $hostId,
            'jwt' => $jwt,
            'module_key' => $moduleKey,
            'module_name' => $moduleName,
            'endpoint' => $page['endpoint'],
            'host_type' => $page['host_type'],
            'directions' => $page['directions'],
            'protocols' => $page['protocols'],
            'groups' => $page['groups'],
            'html' => $page['html'],
        ];

        Cache::put(
            $cacheKey,
            collect($context)->except('supplier')->all(),
            now()->addSeconds(self::SECURITY_GROUP_CONTEXT_CACHE_TTL_SECONDS)
        );

        return $context;
    }

    public function forgetSecurityGroupContextCache(Service $service): void
    {
        $provisionData = (array) ($service->provision_data ?? []);
        $hostId = (int) (($provisionData['upstream_host_id'] ?? 0) ?: 0);
        $supplierId = (int) (($provisionData['supplier_id'] ?? ($service->product?->supplier_id ?? 0)) ?: 0);

        if ($supplierId <= 0 || $hostId <= 0) {
            return;
        }

        Cache::forget($this->buildSecurityGroupContextCacheKey($supplierId, $hostId));
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function callSecurityGroupAction(Service $service, string $func, array $payload, string $action): array
    {
        $context = $this->resolveSecurityGroupContext($service);
        $runtime = $this->detailService->resolveRuntimeCapabilityForSupplier($context['supplier']);
        $response = $runtime->post(
            $context['supplier'],
            $context['endpoint'],
            array_merge($payload, ['func' => $func]),
            $context['jwt'],
            ['content-type: application/x-www-form-urlencoded']
        );
        $this->detailService->assertSuccess($response, $action);

        return ['context' => $context, 'response' => $response];
    }

    private function assertSecurityGroupNameAvailable(Service $service, string $name): void
    {
        if ($name === '') {
            return;
        }

        $context = $this->resolveSecurityGroupContext($service, true);
        $normalizedName = $this->normalizeKeywordText($name);
        $exists = collect((array) ($context['groups'] ?? []))
            ->contains(fn ($item) => is_array($item)
                && $this->normalizeKeywordText((string) ($item['name'] ?? '')) === $normalizedName);

        throw_if($exists, new BusinessException('安全组名称已存在，请换一个名称', 42200));
    }

    private function isSecurityGroupModule(array $module): bool
    {
        $type = trim((string) ($module['type'] ?? ''));
        $text = $this->normalizeKeywordText(
            implode(' ', array_filter([
                (string) ($module['function'] ?? ''),
                (string) ($module['name'] ?? ''),
            ]))
        );

        if ($text === '') {
            return false;
        }

        return ($type === '' || strtolower($type) === 'custom')
            && collect(self::SECURITY_GROUP_MODULE_KEYWORDS)
                ->contains(fn (string $keyword) => str_contains($text, $this->normalizeKeywordText($keyword)));
    }

    private function normalizeKeywordText(string $value): string
    {
        return preg_replace('/\s+/u', '', mb_strtolower(trim($value), 'UTF-8')) ?? '';
    }

    private function parseSecurityGroupPage(string $html): array
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $endpoint = $this->extractSecurityGroupEndpoint($html);

        throw_if($endpoint === '', new BusinessException('已发现安全组模块，但未解析到上游请求地址', 50000));

        return [
            'html' => $html,
            'endpoint' => $endpoint,
            'host_type' => $this->extractSecurityGroupHostType($html),
            'directions' => $this->extractSecuritySelectOptions($html, 'direction'),
            'protocols' => $this->extractSecuritySelectOptions($html, 'protocol'),
            'groups' => $this->extractSecurityGroupRows($html),
        ];
    }

    private function extractSecurityGroupEndpoint(string $html): string
    {
        if (preg_match('/url\s*:\s*[\'"]([^\'"]*\/provision\/custom\/[^\'"]+)[\'"]/iu', $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function extractSecurityGroupHostType(string $html): string
    {
        if (preg_match('/var\s+host_type\s*=\s*[\'"]([^\'"]+)[\'"]/iu', $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? '')) ?: 'host';
        }

        return 'host';
    }

    private function extractSecuritySelectOptions(string $html, string $fieldName): array
    {
        $xpath = $this->createHtmlXPath($html);
        if (! $xpath) {
            return [];
        }

        $options = $xpath->query("//select[@name='{$fieldName}']/option");
        if (! $options) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            $value = trim((string) ($option->attributes?->getNamedItem('value')?->nodeValue ?? ''));
            if ($value === '') {
                continue;
            }

            $item = ['value' => $value, 'label' => $this->normalizeHtmlNodeText($option)];
            $port = trim((string) ($option->attributes?->getNamedItem('data-port')?->nodeValue ?? ''));
            if ($port !== '') {
                $item['port'] = $port;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function extractSecurityGroupRows(string $html): array
    {
        $xpath = $this->createHtmlXPath($html);
        if (! $xpath) {
            return [];
        }

        $rows = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' table-responsive ')]//table[1]/tbody/tr");
        if (! $rows) {
            return [];
        }

        $groups = [];
        foreach ($rows as $row) {
            $rowClass = strtolower(trim((string) ($row->attributes?->getNamedItem('class')?->nodeValue ?? '')));
            if ($rowClass !== '' && str_contains($rowClass, 'expandline')) {
                continue;
            }

            $cells = [];
            $cellNodes = $xpath->query('./td', $row);
            if (! $cellNodes) {
                continue;
            }
            foreach ($cellNodes as $cell) {
                $cells[] = $cell;
            }
            if (count($cells) < 2) {
                continue;
            }

            $actionState = $this->extractSecurityGroupRowActions($xpath, $row);
            $groupId = (int) ($actionState['id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            $rawName = $this->normalizeHtmlNodeText($cells[0]);
            $isAppliedByName = preg_match('/^[（(]\s*当前\s*[)）]\s*/u', $rawName) === 1;
            $normalizedName = trim((string) (preg_replace('/^[（(]\s*当前\s*[)）]\s*/u', '', $rawName) ?? $rawName));
            $isApplied = (bool) ($actionState['is_applied'] ?? false) || $isAppliedByName;

            $groups[] = [
                'id' => $groupId,
                'name' => $normalizedName !== '' ? $normalizedName : $rawName,
                'description' => $this->normalizeHtmlNodeText($cells[1]),
                'can_view' => (bool) ($actionState['can_view'] ?? false),
                'can_add_rule' => (bool) ($actionState['can_add_rule'] ?? true),
                'can_apply' => (bool) ($actionState['can_apply'] ?? false),
                'can_delete' => (bool) ($actionState['can_delete'] ?? false),
                'apply_disabled' => (bool) ($actionState['apply_disabled'] ?? false) || $isApplied,
                'delete_disabled' => (bool) ($actionState['delete_disabled'] ?? false),
                'apply_text' => (string) ($actionState['apply_text'] ?? ''),
                'delete_text' => (string) ($actionState['delete_text'] ?? ''),
                'view_text' => (string) ($actionState['view_text'] ?? ''),
                'add_rule_text' => (string) ($actionState['add_rule_text'] ?? ''),
                'is_applied' => $isApplied,
            ];
        }

        return $groups;
    }

    private function extractSecurityGroupRowActions(\DOMXPath $xpath, \DOMNode $row): array
    {
        $buttons = $xpath->query('.//*[@data-id or self::button or self::a]', $row);
        $state = [
            'id' => 0,
            'can_view' => false,
            'can_add_rule' => true,
            'can_apply' => false,
            'can_delete' => false,
            'apply_disabled' => false,
            'delete_disabled' => false,
            'apply_text' => '',
            'delete_text' => '',
            'view_text' => '',
            'add_rule_text' => '',
            'is_applied' => false,
        ];

        if (! $buttons) {
            return $state;
        }

        foreach ($buttons as $button) {
            $dataId = (int) (($button->attributes?->getNamedItem('data-id')?->nodeValue ?? 0) ?: 0);
            if ($dataId > 0 && $state['id'] === 0) {
                $state['id'] = $dataId;
            }

            $class = strtolower(trim((string) ($button->attributes?->getNamedItem('class')?->nodeValue ?? '')));
            $text = $this->normalizeHtmlNodeText($button);
            $disabled = strtolower(trim((string) ($button->attributes?->getNamedItem('data-disabled')?->nodeValue ?? ''))) === 'true'
                || $button->attributes?->getNamedItem('disabled') !== null;

            if ($class !== '' && str_contains($class, 'trview') || str_contains($text, '查看')) {
                $state['can_view'] = true;
                $state['view_text'] = $text !== '' ? $text : '查看规则';

                continue;
            }

            if (($class !== '' && str_contains($class, 'apply')) || str_contains($text, '应用')) {
                $state['can_apply'] = true;
                $state['apply_disabled'] = $disabled;
                $state['apply_text'] = $text !== '' ? $text : '应用';
                $state['is_applied'] = $disabled && preg_match('/已应用|当前/u', $text) === 1;

                continue;
            }

            if (($class !== '' && str_contains($class, 'deletegroup')) || str_contains($text, '删除')) {
                $state['can_delete'] = true;
                $state['delete_disabled'] = $disabled;
                $state['delete_text'] = $text !== '' ? $text : '删除';

                continue;
            }

            if (str_contains($text, '策略')) {
                $state['can_add_rule'] = true;
                $state['add_rule_text'] = $text !== '' ? $text : '新增规则';
            }
        }

        return $state;
    }

    private function normalizeSecurityRuleItem(array $item, string $hostType): array
    {
        return [
            'id' => (int) (($item['id'] ?? 0) ?: 0),
            'description' => trim((string) ($item['description'] ?? '')),
            'direction' => trim((string) ($item['direction'] ?? '')),
            'direction_label' => match (strtolower(trim((string) ($item['direction'] ?? '')))) {
                'in' => '入方向',
                'out' => '出方向',
                default => '--',
            },
            'protocol' => trim((string) ($item['protocol'] ?? '')),
            'port' => $this->normalizeSecurityRulePort($item),
            'ip' => $this->normalizeSecurityRuleIp($item),
            'action' => trim((string) ($item['action'] ?? '')),
            'action_label' => match (strtolower(trim((string) ($item['action'] ?? '')))) {
                'accept' => '允许',
                'deny' => '拒绝',
                default => '',
            },
            'priority' => isset($item['priority']) ? (int) $item['priority'] : null,
            'lock' => isset($item['lock']) ? (int) $item['lock'] : 0,
            'create_time' => trim((string) ($item['create_time'] ?? '')),
            'host_type' => $hostType,
            'raw' => $item,
        ];
    }

    private function normalizeSecurityRulePort(array $item): string
    {
        $port = trim((string) ($item['port'] ?? ''));
        if ($port !== '') {
            return $port;
        }

        $startPort = trim((string) ($item['start_port'] ?? ''));
        $endPort = trim((string) ($item['end_port'] ?? ''));

        if ($startPort === '' && $endPort === '') {
            return '';
        }

        return $startPort !== '' && $startPort === $endPort
            ? $startPort
            : trim($startPort.'-'.$endPort, '-');
    }

    private function normalizeSecurityRuleIp(array $item): string
    {
        $ip = trim((string) ($item['ip'] ?? ''));
        if ($ip !== '') {
            return $ip;
        }

        $startIp = trim((string) ($item['start_ip'] ?? ''));
        $endIp = trim((string) ($item['end_ip'] ?? ''));

        if ($startIp === '' && $endIp === '') {
            return '';
        }

        return $startIp !== '' && $startIp === $endIp
            ? $startIp
            : trim($startIp.'-'.$endIp, '-');
    }

    private function resolveSecurityGroupName(array $groups, int $groupId): string
    {
        if ($groupId <= 0) {
            return '';
        }

        $group = collect($groups)->first(fn ($item) => is_array($item) && (int) ($item['id'] ?? 0) === $groupId);

        return trim((string) ($group['name'] ?? ''));
    }

    private function buildSecurityGroupContextCacheKey(int $supplierId, int $hostId): string
    {
        return "sg_ctx:{$supplierId}:{$hostId}";
    }

    private function createHtmlXPath(string $html): ?\DOMXPath
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return $loaded ? new \DOMXPath($dom) : null;
    }

    private function normalizeHtmlNodeText(\DOMNode $node): string
    {
        $text = html_entity_decode(trim((string) $node->textContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
