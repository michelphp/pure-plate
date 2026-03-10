<?php

namespace Michel\PurePlate;

use ErrorException;
use Exception;
use InvalidArgumentException;
use ParseError;
use RuntimeException;
use Throwable;

final class Engine
{
    private string $templateDir;
    private bool $devMode;
    private string $cacheDir;
    private PhpRenderer $renderer;
    private array $blocks = [];

    public function __construct(
        string  $templateDir,
        bool    $devMode = false,
        ?string $cacheDir = null,
        array   $globals = []
    )
    {
        $this->templateDir = $templateDir;
        $this->devMode = $devMode;
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/pure_cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        foreach ($globals as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Global key must be a string.');
            }
        }
        $globals['_pure'] = $this;

        $this->renderer = new PhpRenderer($this->cacheDir, $globals);
    }

    /**
     * @throws ErrorException
     */
    public function render(string $filename, array $context = []): string
    {
        $templatePath = $this->templateDir . '/' . ltrim($filename, '/');
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }

        $cacheFile = $this->getCacheFilename($filename);
        if ($this->devMode === true || !$this->isCacheValid($templatePath, $cacheFile)) {
            $compiledCode = $this->compile($templatePath);
            try {
                token_get_all($compiledCode, TOKEN_PARSE);
                $this->saveToCache($cacheFile, trim($compiledCode) . PHP_EOL);
            } catch (ParseError $e) {
                $this->handleError($e, $compiledCode, $templatePath);
            }
        }

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $this->renderer->render(str_replace($this->cacheDir, '', realpath($cacheFile)), $context);
        } catch (Throwable $e) {
            $this->handleError($e, file_get_contents($cacheFile), $templatePath);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @throws ErrorException
     */
    public function extend(string $filename): void
    {
        $_ = $this->render($filename);
        $cacheFile = $this->getCacheFilename($filename);
        $this->renderer->extend(str_replace($this->cacheDir, '', realpath($cacheFile)));
    }

    private function compile(string $path): string
    {
        $html = file_get_contents($path);
        $this->blocks = [];
        $lines = explode(PHP_EOL, $html);
        $output = "";

        foreach ($lines as $i => $line) {
            $num = $i + 1;

            $line = preg_replace('/{#.*?#}/', '', $line);
            if (trim($line) === '') {
                $output .= $line .PHP_EOL;
                continue;
            }
            $line = preg_replace_callback('/{{(.*?)}}|{% (.*?) %}/', function ($m) use ($num, $path) {
                $isBlock = !empty($m[2]);
                $content = trim($isBlock ? $m[2] : $m[1]);

                if ($isBlock) {
                    return $this->compileStructure($content, $num);
                }

                $phpExpr = $this->parseTokens($content);
                return "<?php /*L:$num;F:$path*/ echo htmlspecialchars((string)($phpExpr), ENT_QUOTES); ?>";

            }, $line);

            $output .= $line .PHP_EOL;
        }
        return $output;
    }

    private function compileStructure(string $rawContent, int $line): string
    {
        $parts = preg_split('/\s+/', $rawContent, 2);
        $cmd = strtolower(trim($parts[0]));
        $rawExpr = $parts[1] ?? '';

        if ($cmd === 'extends') {
            $phpExpr = $this->parseTokens($rawExpr);
            return "<?php \$_epure->extend($phpExpr); ?>";
        }


        if ($cmd === 'block') {
            $blockName = trim($rawExpr, "\"' ");
            return "<?php \$this->startBlock('$blockName'); ?>";
        }

        if ($cmd === 'endblock') {
            return "<?php \$this->endblock(); ?>";
        }

        if ($cmd === 'include') {
            $phpExpr = $this->parseTokens($rawExpr);
            return "<?php \$_pure->render($phpExpr); ?>";
        }

        if ($cmd === 'set') {
            $phpExpr = $this->parseTokens($rawExpr);
            return "<?php $phpExpr; ?>";
        }

        if (in_array($cmd, ['if', 'foreach', 'while', 'for', 'elseif'])) {
            if ($cmd !== 'elseif') {
                $this->blocks[] = ['type' => $cmd, 'line' => $line];
            }
            $phpExpr = $this->parseTokens($rawExpr);

            return "<?php $cmd ($phpExpr): ?>";
        }

        if ($cmd === 'else') {
            return "<?php else: ?>";
        }

        if (substr($cmd, 0, 3) === 'end') {
            if (empty($this->blocks)) {
                throw new Exception("ÉPURE ERROR: '$cmd' inattendu à la ligne $line");
            }
            array_pop($this->blocks);
            return "<?php $cmd; ?>";
        }

        $phpExpr = $this->parseTokens($rawContent);
        return "<?php $phpExpr; ?>";
    }

    private function parseTokens(string $expr): string
    {
        if (trim($expr) === '') return '';

        $tokens = token_get_all("<?php " . trim($expr));
        $res = "";
        $len = count($tokens);

        for ($i = 0; $i < $len; $i++) {
            $t = $tokens[$i];

            if ($t === '|') {
                $filterName = '';
                $hasParens = false;
//                $filterIndex = -1;

                for ($j = $i + 1; $j < $len; $j++) {
                    $nt = $tokens[$j];
                    if (is_array($nt) && $nt[0] === T_WHITESPACE) {
                        continue;
                    }
                    if (is_array($nt) && $nt[0] === T_STRING) {
                        $filterName = $nt[1];
                        $filterIndex = $j;

                        for ($k = $j + 1; $k < $len; $k++) {
                            $nnt = $tokens[$k];
                            if (is_array($nnt) && $nnt[0] === T_WHITESPACE) {
                                continue;
                            }
                            if ($nnt === '(') {
                                $hasParens = true;
                                $i = $k;
                            } else {
                                $i = $filterIndex;
                            }
                            break 2;
                        }
                        $i = $filterIndex;
                        break;
                    }
                }

                if ($hasParens) {
                    $res = "$filterName(" . trim($res) . ", ";
                } else {
                    $res = "$filterName(" . trim($res) . ")";
                }
                continue;
            }

            if (is_array($t)) {
                [$id, $text] = $t;
                if ($id === T_OPEN_TAG) {
                    continue;
                }

                $word = strtolower($text);

                if ($id === T_STRING && $word === 'is') {
                    $nextWords = [];
                    $nextIndexes = [];
                    for ($j = $i + 1; $j < $len && count($nextWords) < 2; $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                            continue;
                        }
                        $nextWords[] = strtolower(is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j]);
                        $nextIndexes[] = $j;
                    }

                    if (isset($nextWords[0]) && $nextWords[0] === 'empty') {
                        $res = "empty(" . trim($res) . ")";
                        $i = $nextIndexes[0];
                        continue;
                    }
                    if (isset($nextWords[1]) && $nextWords[0] === 'not' && $nextWords[1] === 'empty') {
                        $res = "!empty(" . trim($res) . ")";
                        $i = $nextIndexes[1];
                        continue;
                    }
                }

                if ($id === T_STRING || $id === T_EMPTY) {
                    $isFunction = false;
                    for ($j = $i + 1; $j < $len; $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
                        if ($tokens[$j] === '(') {
                            $isFunction = true;
                        }
                        break;
                    }

                    if ($word === 'not') {
                        $res .= '!';
                        continue;
                    }

                    $trimmedRes = trim($res);
                    $isProp = (substr($trimmedRes, -2) === '->');
                    $isReserved = in_array($word, ['as', 'is', 'true', 'false', 'null', 'empty']);
                    $res .= ($isProp || $isReserved || $isFunction) ? $text : '$' . $text;

                } else {
                    $res .= $text;
                }
            } else {
                $res .= ($t === '.') ? '->' : $t;
            }
        }

        return $res;
    }

    private function handleError(Throwable $e, string $compiled, string $path): void
    {
        $lines = explode("\n", $compiled);
        $errorLine = $e->getLine();
        $faultyCode = $lines[$errorLine - 1] ?? '';

        preg_match('/\/\*L:(\d+);F:(.*?)\*\//', $faultyCode, $m);

        $origLine = isset($m[1]) ? (int)$m[1] : $e->getLine();
        $origFile = $m[2] ?? $path;
        throw new \ErrorException(
            "PurePlate Render Error: " . $e->getMessage(),
            0,
            ($e instanceof \ErrorException) ? $e->getSeverity() : E_USER_ERROR,
            $origFile,
            $origLine
        );

    }

    private function isCacheValid(string $templateFile, string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }

        return filemtime($cacheFile) >= filemtime($templateFile);
    }

    private function getCacheFilename(string $templateFile): string
    {
        $hash = md5(realpath($templateFile));
        $basename = pathinfo($templateFile, PATHINFO_FILENAME);
        return $this->cacheDir . DIRECTORY_SEPARATOR . $basename . '_' . $hash . '.cache.php';
    }

    private function saveToCache(string $cacheFile, string $compiledCode): void
    {
        $tempFile = $cacheFile . '.tmp';
        if (file_put_contents($tempFile, $compiledCode, LOCK_EX) !== false) {
            @rename($tempFile, $cacheFile);
        } else {
            throw new RuntimeException("Unable to write cache file: {$cacheFile}");
        }
    }
}
