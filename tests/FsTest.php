<?php

namespace Vertilia\Router;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Fs
 */
class FsTest extends TestCase
{
    /**
     * @dataProvider normalizePathProvider
     * @covers ::normalizePath
     */
    public function testNormalizePath($path, $expected)
    {
        $this->assertEquals($expected, Fs::normalizePath($path));
    }

    /** data provider */
    public function normalizePathProvider(): array
    {
        return [
            ['/', ''],
            ['///', ''],
            ['/index.php', 'index.php'],
            ['//b/../a//b/c/./d//', 'a/b/c/d'],
            ['//b/../a//b/c/./d//index.php', 'a/b/c/d/index.php'],
        ];
    }
}
