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
    private ?string $currentBlock = null;
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
        if ($this->currentBlock !== null) {
            throw new RuntimeException("A block is already started. Call endBlock() before starting a new block.");
        }

        $content = $this->block($name);
        $this->currentBlock = $name;
        if (!empty($content)) {
            echo $content;
        }
        ob_start();
    }

    public function endBlock(): void
    {
        if ($this->currentBlock === null) {
            throw new RuntimeException("No block started. Call startBlock() before calling endBlock().");
        }
        $this->blocks[$this->currentBlock] = trim(ob_get_clean());
        $this->currentBlock = null;
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
