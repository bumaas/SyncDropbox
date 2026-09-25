<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

use PHPUnit\Framework\TestCase;

class UploadTest extends TestCase
{
    // Taken from a real log: git creates AUTO_MERGE only for the duration of a merge/rebase.
    // It was queued by ReSync and had vanished again when Upload() reached it.
    private const VANISHED_FILE = 'modules/MatterDiagnose/.git/AUTO_MERGE';
    private const VANISHED_FILE_2 = 'modules/MatterDiagnose/.git/index.lock';

    private $instanceID;

    protected function setUp(): void
    {
        //Reset
        IPS\Kernel::reset();

        //Register our core stubs for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');

        //Register our library we need for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');

        //System profiles are always available in Symcon, but not in the stubs
        IPS_CreateVariableProfile('~UnixTimestamp', VARIABLETYPE_INTEGER);

        $this->instanceID = IPS_CreateInstance('{E3A349A9-A733-A6D6-E20E-EC87B150BEB6}');

        parent::setUp();
    }

    public function testVanishedFileInAddQueueIsSkipped(): void
    {
        $this->setQueue(['add' => [self::VANISHED_FILE, self::VANISHED_FILE_2], 'update' => [], 'delete' => []]);

        $this->upload();

        $this->assertSame([self::VANISHED_FILE_2], $this->getQueue()['add']);
        $this->assertSame([], $this->getCache());
    }

    public function testVanishedFileInUpdateQueueIsSkipped(): void
    {
        $this->setQueue(['add' => [], 'update' => [self::VANISHED_FILE, self::VANISHED_FILE_2], 'delete' => []]);

        $this->upload();

        $this->assertSame([self::VANISHED_FILE_2], $this->getQueue()['update']);
        $this->assertSame([], $this->getCache());
    }

    public function testQueueOfVanishedFilesDrainsCompletely(): void
    {
        $this->setQueue(['add' => [self::VANISHED_FILE], 'update' => [self::VANISHED_FILE_2], 'delete' => []]);

        $this->upload();
        $this->upload();

        $this->assertSame(['add' => [], 'update' => [], 'delete' => []], $this->getQueue());
    }

    private function upload(): void
    {
        IPS\InstanceManager::getInstanceInterface($this->instanceID)->Upload();
    }

    private function setQueue(array $fileQueue): void
    {
        $this->callProtected('SetBuffer', 'FileCache', gzencode(json_encode([])));
        $this->callProtected('SetBuffer', 'FileQueue', gzencode(json_encode($fileQueue)));
    }

    private function getQueue(): array
    {
        return json_decode(gzdecode($this->callProtected('GetBuffer', 'FileQueue')), true);
    }

    private function getCache(): array
    {
        return json_decode(gzdecode($this->callProtected('GetBuffer', 'FileCache')), true);
    }

    private function callProtected(string $method, ...$args)
    {
        $intf = IPS\InstanceManager::getInstanceInterface($this->instanceID);
        $reflection = new ReflectionMethod($intf, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($intf, ...$args);
    }
}
