<?php

namespace Michel\PurePlate;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use function extract;
use function file_exists;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function rtrim;
use function trim;

final class PhpRenderer
{
    private string $templateDir;
    private array $globals;
    private array $blocks = [];
    private array $blockStack = [];
    private ?string $layout = null;

    public function __construct(
        string $templateDir,
        array  $globals = []
    )
    {
        $this->templateDir = rtrim($templateDir, '/');
        $this->globals = $globals;
    }

    public function render(string $view, array $context = []): string
    {
        $filename = $this->findTemplateFile($view);
        $this->layout = null;

        $level = ob_get_level();
        ob_start();

        try {
            $context = $this->mergeContext($context);
            extract($context);
            include $filename;
            $content = ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        if ($this->layout === null) {
            return $content;
        }

        return $this->render($this->layout);
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function startBlock(string $name): void
    {
        $content = $this->block($name);
        $this->blockStack[] = $name;
        if (!empty($content)) {
            echo $content;
        }
        ob_start();
    }

    public function endBlock(): void
    {
        if (empty($this->blockStack)) {
            throw new RuntimeException("No block started. Call startBlock() before calling endBlock().");
        }

        $content = trim(ob_get_clean());
        $name = array_pop($this->blockStack);
        $this->blocks[$name] = $content;
        if (!empty($this->blockStack)) {
            echo $content;
        }
    }

    public function block(string $name): string
    {
        return $this->blocks[$name] ?? '';
    }

    private function findTemplateFile(string $view): string
    {
        $filename = $this->templateDir . DIRECTORY_SEPARATOR . $view;
        if (!file_exists($filename)) {
            throw new InvalidArgumentException($filename . ' template not found');
        }
        return $filename;
    }

    private function mergeContext(array $context): array
    {
        foreach ($context as $key => $_) {
            if (array_key_exists($key, $this->globals)) {
                throw new InvalidArgumentException(sprintf(
                    'Context key "%s" conflicts with a global variable',
                    $key
                ));
            }
        }

        return array_merge($this->globals, $context);
    }
}
