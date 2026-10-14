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
        // Each render owns its layout and blocks, so an include never disturbs its caller.
        $caller = [$this->layout, $this->blocks];
        $this->layout = null;
        $this->blocks = [];

        try {
            $content = $this->capture($this->findTemplateFile($view), $this->mergeContext($context));

            // The child is rendered first, then each layout up the chain, with the same context.
            while ($this->layout !== null) {
                $layout = $this->layout;
                $this->layout = null;
                $content = $this->capture($this->findTemplateFile($layout), $this->mergeContext($context));
            }

            return $content;
        } finally {
            [$this->layout, $this->blocks] = $caller;
        }
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function startBlock(string $name): void
    {
        $this->blockStack[] = $name;
        ob_start();
    }

    public function endBlock(): void
    {
        if (empty($this->blockStack)) {
            throw new RuntimeException("No block started. Call startBlock() before calling endBlock().");
        }

        $content = trim(ob_get_clean());
        $name = array_pop($this->blockStack);

        // The child template is rendered before its layout, so the first definition wins.
        if (!isset($this->blocks[$name])) {
            $this->blocks[$name] = $content;
        }

        echo $this->blocks[$name];
    }

    public function block(string $name): string
    {
        return $this->blocks[$name] ?? '';
    }

    private function capture(string $filename, array $context): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            // No named local variable in this closure: the context may use any key.
            (function () {
                extract(func_get_arg(1));
                include func_get_arg(0);
            })($filename, $context);

            return ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
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
        foreach ($context as $key => $value) {
            if (array_key_exists($key, $this->globals) && $this->globals[$key] !== $value) {
                throw new InvalidArgumentException(sprintf(
                    'Context key "%s" conflicts with a global variable',
                    $key
                ));
            }
        }

        return array_merge($this->globals, $context);
    }
}
