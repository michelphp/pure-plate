<?php

namespace Test\Michel\PurePlate;

use Michel\PurePlate\PhpRenderer;
use Michel\UniTester\TestCase;

final class PhpRendererTest extends TestCase
{
    private PhpRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PhpRenderer(__DIR__ . '/views');
    }

    protected function tearDown(): void
    {
    }

    protected function execute(): void
    {
       $this->testRenderBasicView();
       $this->testRenderViewWithLayout();
       $this->testRenderViewWithBlock();
       $this->testRenderViewWithExtendingBlock();
       $this->testStartAndEndBlocks();
       $this->testRenderViewNotFound();
    }

    public function testRenderBasicView(): void
    {
        $expectedOutput = 'Hello, World!';
        $output = $this->renderer->render('test.php', ['message' => 'Hello, World!']);
        $this->assertEquals($expectedOutput, $output);
    }

    public function testRenderViewWithLayout(): void
    {
        $expectedOutput = '<!DOCTYPE html><html lang="fr"><head><title></title></head><body class="body"></body></html>';
        $output = $this->renderer->render('test_layout.php');
        $this->assertEquals($expectedOutput, $output);
    }

    public function testRenderViewWithBlock(): void
    {
        $expectedOutput = 'Block Content';
        $output = trim($this->renderer->render('test_block.php'));
        $this->assertEquals($expectedOutput, $output);
    }

    public function testRenderViewWithExtendingBlock(): void
    {
        $expectedOutput = '<!DOCTYPE html><html lang="fr"><head><title>Page Title</title></head><body class="body">Page Content</body></html>';
        $output = $this->renderer->render('test_extends.php');
        $this->assertEquals($expectedOutput, $output);
    }

    public function testStartAndEndBlocks(): void
    {
        ob_start();
        $this->renderer->startBlock('content');
        echo 'Block Content';
        $this->renderer->endBlock();
        $echoed = ob_get_clean();

        $expectedOutput = 'Block Content';
        $this->assertEquals($expectedOutput, $echoed);
        $this->assertEquals($expectedOutput, $this->renderer->block('content'));
    }

    public function testRenderViewNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class, function () {
            $this->renderer->render('non_existent_template.php');
        });
    }

}
