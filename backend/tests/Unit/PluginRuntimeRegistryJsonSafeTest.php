<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use Tests\TestCase;

class PluginRuntimeRegistryJsonSafeTest extends TestCase
{
    /**
     * @return array{PluginRuntimeRegistry&MockObject, ReflectionMethod}
     */
    private function method(): array
    {
        // 仅测试纯函数 jsonSafeValue，用 mock 规避构造依赖。
        /** @var PluginRuntimeRegistry&MockObject $registry */
        $registry = $this->getMockBuilder(PluginRuntimeRegistry::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $method = new ReflectionMethod(PluginRuntimeRegistry::class, 'jsonSafeValue');
        $method->setAccessible(true);

        return [$registry, $method];
    }

    public function test_object_is_normalized_to_class_snapshot(): void
    {
        [$registry, $method] = $this->method();

        $object = new \stdClass;
        $object->name = 'demo';

        $result = $method->invoke($registry, $object, new \WeakMap, 0);

        $this->assertSame([
            '__class' => \stdClass::class,
            'properties' => ['name' => 'demo'],
        ], $result);
    }

    public function test_circular_reference_does_not_recurse_infinitely(): void
    {
        [$registry, $method] = $this->method();

        $object = new \stdClass;
        $object->self = $object;

        $result = $method->invoke($registry, $object, new \WeakMap, 0);

        $this->assertSame(\stdClass::class, $result['__class']);
        $this->assertTrue($result['properties']['self']['__circular']);
    }

    public function test_mutual_references_are_detected_as_circular(): void
    {
        [$registry, $method] = $this->method();

        $a = new \stdClass;
        $b = new \stdClass;
        $a->peer = $b;
        $b->peer = $a;

        $result = $method->invoke($registry, $a, new \WeakMap, 0);

        $this->assertSame(\stdClass::class, $result['__class']);
        $this->assertTrue($result['properties']['peer']['properties']['peer']['__circular']);
    }

    public function test_deep_nesting_beyond_max_depth_is_truncated(): void
    {
        [$registry, $method] = $this->method();

        // 构造超过最大深度的嵌套数组。
        $deep = ['leaf' => true];
        foreach (range(1, 30) as $_) {
            $deep = ['next' => $deep];
        }

        $result = $method->invoke($registry, $deep, new \WeakMap, 0);

        // 逐层下钻到截断点，应出现 '(max depth)' 而非栈溢出。
        $current = $result;
        for ($i = 0; $i < 30; $i++) {
            if (is_string($current) || ! is_array($current)) {
                break;
            }
            $current = $current['next'] ?? null;
        }

        $this->assertSame('(max depth)', $current);
    }

    public function test_closure_and_resource_are_replaced(): void
    {
        [$registry, $method] = $this->method();

        $this->assertNull($method->invoke($registry, fn () => 1, new \WeakMap, 0));
        $this->assertNull($method->invoke($registry, fopen('php://memory', 'r'), new \WeakMap, 0));
    }
}
