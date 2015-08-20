<?php

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;

final class Test extends PHPUnit_Framework_TestCase
{
    const SCRIPT_PATH = __DIR__ . '/../src/sfvcheck.php';

    /** @var vfsStreamDirectory */
    private $directory;

    /** @var vfsStreamFile */
    private $sfv;

    protected function setUp()
    {
        vfsStream::setup();

        $this->directory = vfsStream::create(
            [
                'foo.sfv' => <<<'SFV'
foo 8c736521
bar 76ff8caa

SFV
                ,
                'foo' => 'foo',
                'bar' => 'bar',
            ]
        );

        $this->sfv = $this->directory->getChild('foo.sfv');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp [^Usage: .++]
     */
    public function testNoArgumentsShowsUsage()
    {
        $this->executeSUT();
    }

    /**
     * @expectedException IOException
     * @expectedExceptionMessage Cannot read "foo".
     */
    public function testInvalidFileThrowsException()
    {
        $this->setExpectedException(IOException::class);

        $this->expectOutputRegex('[]');

        $this->executeSUT('foo');
    }

    public function testValidSfv()
    {
        $this->expectOutputRegex('[^Summary: 2 passed, 0 failed, 0 missing\.$]m');

        $this->executeSUT($this->sfv->url());
    }

    public function testEmptyLineSkipped()
    {
        $this->sfv->setContent(preg_replace('[$]m', "\n", $this->sfv->getContent(), 1));

        $this->expectOutputRegex('[^Summary: 2 passed, 0 failed, 0 missing\.$]m');

        $this->executeSUT($this->sfv->url());
    }

    public function testCommentSkipped()
    {
        $this->sfv->setContent(preg_replace('[$]m', "\n;foo", $this->sfv->getContent(), 1));

        $this->expectOutputRegex('[^Summary: 2 passed, 0 failed, 0 missing\.$]m');

        $this->executeSUT($this->sfv->url());
    }

    public function testSpaceInFilename()
    {
        file_put_contents($this->sfv->url(), 'foo bar be460134', FILE_APPEND);
        (new vfsStreamFile('foo bar'))->setContent('foo bar')->at($this->directory);

        $this->expectOutputRegex('[^Summary: 3 passed, 0 failed, 0 missing\.$]m');

        $this->executeSUT($this->sfv->url());
    }

    public function testFileMissing()
    {
        $this->directory->removeChild('bar');

        $this->expectOutputRegex('[^Summary: 1 passed, 0 failed, 1 missing\.$]m');

        $this->executeSUT($this->sfv->url());
    }

    public function testFileCorrupt()
    {
        file_put_contents($this->directory->getChild('bar')->url(), 'baz');

        $this->expectOutputRegex('[^Summary: 1 passed, 1 failed, 0 missing\.$]m');

        $this->executeSUT($this->sfv->url());
    }

    /**
     * @expectedException MalformedFileException
     */
    public function testInvalidSfv()
    {
        $this->sfv->setContent('foo');

        $this->expectOutputRegex('[]');

        $this->executeSUT($this->sfv->url());
    }

    public function testDirectoryWithSfv()
    {
        $this->expectOutputRegex('[^Summary: 2 passed, 0 failed, 0 missing\.$]m');

        $this->executeSUT($this->directory->url());
    }

    public function testDirectoryWithoutSfv()
    {
        $this->directory->removeChild($this->sfv->getName());

        $this->expectOutputRegex('[^No file matching \*\.sfv in ".+"\.$]');

        $this->executeSUT($this->directory->url());
    }

    public function testMultipleSfv()
    {
        $this->expectOutputRegex('[(?:^Summary: 2 passed, 0 failed, 0 missing\.$.+?){2}]ms');

        $this->executeSUT($this->sfv->url(), $this->sfv->url());
    }

    public function testRecursiveDirectorySearch()
    {
        vfsStream::setup();
        vfsStream::create(
            [
                'foo' => [
                    'foo.sfv' => 'foo 8c736521',
                    'foo' => 'foo',
                ],
                'bar' => [
                    'bar.sfv' => 'bar 76ff8caa',
                    'bar' => 'bar',
                ],
            ]
        );

        $this->expectOutputRegex('[(?:^Processing ".*?\b(?:(?!\1)(foo|bar))\.sfv"\.\.\.$.+?){2}]ms');

        $this->executeSUT($this->directory->url());
    }

    /**
     * Execute the system under test with the specified arguments.
     *
     * @param mixed ...$arguments Command line arguments.
     * @throws Exception SUT exception.
     */
    private function executeSUT(...$arguments)
    {
        // Import argument globals so they are visible to included script.
        global $argc, $argv;

        $this->emulateArguments($arguments);

        ob_start(function ($output) {
            // Strip shebang line from script output.
            return preg_replace('[\A#!.+?\n]', null, $output, 1);
        });

        try {
            // Execute system under test.
            require self::SCRIPT_PATH;
        } catch (Exception $e) {
            throw $e;
        } finally {
            ob_end_flush();
        }
    }

    /**
     * Emulate passing the specified command line arguments.
     *
     * @param array $arguments Command line arguments.
     */
    private function emulateArguments(array $arguments = [])
    {
        global $argc, $argv;

        $argv = $arguments;
        array_unshift($argv, 'test');
        $argc = count($argv);
    }
}
