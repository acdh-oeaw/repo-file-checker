<?php

namespace acdhOeaw\arche\fileChecker\tests;

use acdhOeaw\arche\fileChecker\CheckFunctions;
use acdhOeaw\arche\fileChecker\FileInfo;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use ZipArchive;

class BagitCleanupTest extends TestCase {

    /**
     * @return array<string, array{string}>
     */
    public static function directoryNames(): array {
        return [
            'plain directory' => ['bag-normal'],
            'directory containing apostrophe' => ["bag'normal"],
        ];
    }

    #[DataProvider('directoryNames')]
    public function testTemporaryBagDirectoryIsRemoved(string $directoryName): void {
        $workspace = sys_get_temp_dir() . '/filechecker-bagit-cleanup-' . bin2hex(random_bytes(8));
        $tmpDir    = $workspace . '/tmp';
        $zipPath   = $workspace . '/bag.zip';

        mkdir($workspace, 0700, true);
        mkdir($tmpDir, 0700, true);

        try {
            $this->createBagItArchive($zipPath, $directoryName);

            $fileInfo            = new FileInfo();
            $fileInfo->type      = FileInfo::DROID_TYPEFILE;
            $fileInfo->path      = $zipPath;
            $fileInfo->filename  = basename($zipPath);
            $fileInfo->extension = 'zip';
            $fileInfo->valid     = true;

            // The constructor configures external validators unrelated to this check.
            $check = (new ReflectionClass(CheckFunctions::class))->newInstanceWithoutConstructor();
            (new ReflectionProperty(CheckFunctions::class, 'tmpDir'))->setValue($check, $tmpDir);

            $check->check80BagitFile($fileInfo);

            $this->assertSame(FileInfo::SPECIAL_BAGIT, $fileInfo->specialType);
            $this->assertTrue($fileInfo->isValid(false));
            $this->assertDirectoryDoesNotExist($tmpDir . '/' . $directoryName);
        } finally {
            $this->removeWorkspace($workspace);
        }
    }

    private function createBagItArchive(string $destination, string $rootName): void {
        $source = new ZipArchive();
        $this->assertTrue($source->open(__DIR__ . '/data/bagit/testbag.zip') === true);

        $target = new ZipArchive();
        $this->assertTrue($target->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        try {
            for ($i = 0; $i < $source->numFiles; $i++) {
                $name = $source->getNameIndex($i);
                $this->assertIsString($name);
                $this->assertStringStartsWith('testbag/', $name);
                $renamed = $rootName . substr($name, strlen('testbag'));

                if (str_ends_with($name, '/')) {
                    $this->assertTrue($target->addEmptyDir($renamed));
                } else {
                    $contents = $source->getFromIndex($i);
                    $this->assertIsString($contents);
                    $this->assertTrue($target->addFromString($renamed, $contents));
                }
            }
        } finally {
            $source->close();
            $target->close();
        }
    }

    private function removeWorkspace(string $path): void {
        if (!is_dir($path)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
