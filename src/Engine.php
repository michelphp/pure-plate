<?php

namespace Michel\PurePlate;

use ErrorException;
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
    private array $globals = [];
    /** Cache file name => template path, to report errors in the template. */
    private array $sources = [];

    public function __construct(
        string  $templateDir,
        bool    $devMode = false,
        ?string $cacheDir = null,
        array   $globals = []
    )
    {
        $this->templateDir = $templateDir;
        $this->devMode = $devMode;
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/pp_cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        foreach ($globals as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Global key must be a string.');
            }
        }
        $globals['_plate'] = $this;

        $this->globals = $globals;
        $this->renderer = new PhpRenderer($this->cacheDir, $this->globals);
    }

    /**
     * @throws ErrorException
     */
    public function render(string $filename, array $context = []): string
    {
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'php') {
            $render = new PhpRenderer($this->templateDir, $this->globals);
            return $render->render($filename, $context);
        }

        $cacheFile = $this->compileIfNeeded($filename);

        try {
            set_error_handler(function ($severity, $message, $file, $line) {
                if (!(error_reporting() & $severity)) {
                    return;
                }
                throw new ErrorException($message, 0, $severity, $file, $line);
            });
            return $this->renderer->render(basename($cacheFile), $context);
        } catch (Throwable $e) {
            throw $this->mapToTemplate($e);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @throws ErrorException
     */
    public function extend(string $filename): void
    {
        $cacheFile = $this->compileIfNeeded($filename);
        $this->renderer->extend(basename($cacheFile));
    }

    /**
     * Compiles the template into the cache when needed and returns the cache file path.
     *
     * @throws ErrorException
     */
    private function compileIfNeeded(string $filename): string
    {
        $templatePath = $this->templateDir . '/' . ltrim($filename, '/');
        $cacheFile = $this->getCacheFilename($templatePath);
        $this->sources[basename($cacheFile)] = $templatePath;

        if ($this->isCacheFresh($templatePath, $cacheFile)) {
            return $cacheFile;
        }

        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }
        try {
            $compiledCode = $this->compile($templatePath);
            token_get_all($compiledCode, TOKEN_PARSE);
        } catch (ParseError | ErrorException $e) {
            throw $this->templateError($e, $templatePath, $e->getLine());
        }
        $this->saveToCache($cacheFile, $compiledCode);

        return $cacheFile;
    }

    /**
     * Production trusts the cache once written: clear the cache directory when deploying.
     * Dev mode recompiles only the templates that changed.
     */
    private function isCacheFresh(string $templatePath, string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }
        if (!$this->devMode) {
            return true;
        }
        return file_exists($templatePath) && filemtime($cacheFile) > filemtime($templatePath);
    }

    /**
     * One compiled line per template line, so a line number in the cache
     * is the same line number in the template.
     */
    private function compile(string $path): string
    {
        $this->blocks = [];

        // A comment may span lines: keep its line breaks so that line numbers do not move.
        $template = preg_replace_callback('/{#.*?#}/s', function ($m) {
            return str_repeat("\n", substr_count($m[0], "\n"));
        }, file_get_contents($path));

        $lines = explode("\n", $template);
        $lastIndex = count($lines) - 1;

        foreach ($lines as $i => $line) {
            $leftover = preg_replace('/{{.*?}}|{%.*?%}/', '', $line);
            foreach (['{{', '{%', '{#'] as $tag) {
                if (strpos($leftover, $tag) !== false) {
                    throw $this->syntaxError("Unclosed '$tag' (a tag must end on the line where it starts)", $i + 1);
                }
            }

            // Printed as text, whatever the short_open_tag setting.
            $line = str_replace('<?xml', "<?php echo '<?xml'; ?>", $line);

            // PHP swallows the newline right after a closing tag: echo it back when the line ends with {{ ... }}.
            $endsWithOutput = $i < $lastIndex && substr(rtrim($line), -2) === '}}';

            $line = preg_replace_callback('/{{(.*?)}}|{%(.*?)%}/', function ($m) use ($i) {
                if (!empty($m[2])) {
                    return $this->compileStructure(trim($m[2]), $i + 1);
                }
                $phpExpr = $this->parseTokens(trim($m[1]));
                return "<?php echo htmlspecialchars((string)($phpExpr), ENT_QUOTES); ?>";
            }, $line);

            $lines[$i] = $endsWithOutput ? $line . '<?php echo "\n"; ?>' : $line;
        }

        if (!empty($this->blocks)) {
            $open = array_pop($this->blocks);
            throw $this->syntaxError("Unclosed '{$open['type']}'", $open['line']);
        }

        return implode("\n", $lines);
    }

    private function compileStructure(string $rawContent, int $line): string
    {
        $parts = preg_split('/\s+/', $rawContent, 2);
        $cmd = strtolower(trim($parts[0]));
        $rawExpr = $parts[1] ?? '';

        if ($cmd === 'extends') {
            $phpExpr = $this->parseTokens($rawExpr);
            return "<?php \$_plate->extend($phpExpr); ?>";
        }


        if ($cmd === 'block') {
            $this->blocks[] = ['type' => 'block', 'line' => $line];
            $blockName = var_export(trim($rawExpr, "\"' "), true);
            return "<?php \$this->startBlock($blockName); ?>";
        }

        if ($cmd === 'break' || $cmd === 'continue') {
            $levels = (int) ($rawExpr ?: 1);
            $loops = array_filter($this->blocks, function ($open) {
                return in_array($open['type'], ['foreach', 'for', 'while', 'switch'], true);
            });
            if (count($loops) < $levels) {
                throw $this->syntaxError($levels === 1
                    ? "'$cmd' outside of a loop or switch"
                    : "'$cmd $levels' needs $levels nested loops or switch", $line);
            }
            return "<?php $cmd $levels; ?>";
        }

        if ($cmd === 'include') {
            $phpExpr = $this->parseTokens($rawExpr);
            return "<?php echo \$_plate->render($phpExpr, get_defined_vars()); ?>";
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

        if ($cmd === 'switch') {
            // PHP allows no output between "switch" and its first "case": the first case opens it.
            $this->blocks[] = ['type' => 'switch', 'line' => $line, 'subject' => $this->parseTokens($rawExpr)];
            return '';
        }

        if ($cmd === 'case' || $cmd === 'default') {
            $key = array_key_last($this->blocks);
            if ($key === null || $this->blocks[$key]['type'] !== 'switch') {
                throw $this->syntaxError("Unexpected '$cmd' outside of a 'switch'", $line);
            }
            $label = $cmd === 'case' ? 'case ' . $this->parseTokens($rawExpr) . ':' : 'default:';
            if (!isset($this->blocks[$key]['subject'])) {
                return "<?php $label ?>";
            }
            $subject = $this->blocks[$key]['subject'];
            unset($this->blocks[$key]['subject']);
            return "<?php switch ($subject): $label ?>";
        }

        if (substr($cmd, 0, 3) === 'end') {
            $open = array_pop($this->blocks);
            if ($open === null) {
                throw $this->syntaxError("Unexpected '$cmd' (missing opening tag)", $line);
            }
            if (isset($open['subject'])) {
                throw $this->syntaxError("'switch' without 'case' (opened at line {$open['line']})", $line);
            }
            if ($cmd !== 'end' . $open['type']) {
                throw $this->syntaxError("Unexpected '$cmd', expected 'end{$open['type']}' (opened at line {$open['line']})", $line);
            }
            return $cmd === 'endblock' ? "<?php \$this->endBlock(); ?>" : "<?php $cmd; ?>";
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

        // A filter or "is empty" applies to the value just before it: the value starts
        // right after the last operator or opening bracket. Brackets save and restore it.
        $start = 0;
        $savedStarts = [];

        for ($i = 0; $i < $len; $i++) {
            $t = $tokens[$i];
            [$id, $text] = is_array($t) ? $t : [null, $t];

            if ($id === T_OPEN_TAG) {
                continue;
            }

            if ($t === '|') {
                $j = $this->nextToken($tokens, $i);
                $filterName = $tokens[$j][1] ?? '';
                $k = $this->nextToken($tokens, $j);
                $hasArguments = ($tokens[$k] ?? null) === '(';
                $i = $hasArguments ? $k : $j;

                $value = trim(substr($res, $start));
                $res = substr($res, 0, $start) . "$filterName($value" . ($hasArguments ? ', ' : ')');
                if ($hasArguments) {
                    $savedStarts[] = $start;
                    $start = strlen($res);
                }
                continue;
            }

            $word = strtolower($text);

            if ($id === T_STRING && $word === 'is') {
                $j = $this->nextToken($tokens, $i);
                $k = $this->nextToken($tokens, $j);
                $next = strtolower($tokens[$j][1] ?? '');
                $afterNext = strtolower($tokens[$k][1] ?? '');
                $test = $next === 'empty' ? 'empty' : ($next === 'not' && $afterNext === 'empty' ? '!empty' : null);
                if ($test !== null) {
                    $value = trim(substr($res, $start));
                    $res = substr($res, 0, $start) . "$test($value)";
                    $i = $test === 'empty' ? $j : $k;
                    continue;
                }
            }

            if ($id === T_STRING && $word === 'not') {
                [$id, $text] = [null, '!'];
            } elseif ($id === T_STRING || $id === T_EMPTY) {
                // A function name is followed by "(", a class name by "::" or "\".
                $next = $tokens[$this->nextToken($tokens, $i)] ?? null;
                $isName = $next === '(' || (is_array($next) && in_array($next[0], [T_DOUBLE_COLON, T_NS_SEPARATOR], true));

                // A member comes after "->" or "::", a namespaced name after "\".
                $trimmedRes = trim($res);
                $isMember = substr($trimmedRes, -2) === '->' || substr($trimmedRes, -2) === '::' || substr($trimmedRes, -1) === '\\';
                $isReserved = in_array($word, ['as', 'is', 'true', 'false', 'null', 'empty']);
                $text = ($isMember || $isReserved || $isName) ? $text : '$' . $text;
            } elseif ($t === '.') {
                $text = '->';
            }

            // Spaces right after an operator stay outside the next value.
            $atValueStart = $start === strlen($res);
            $res .= $text;

            if ($text === '(' || $text === '[') {
                $savedStarts[] = $start;
                $start = strlen($res);
            } elseif ($text === ')' || $text === ']') {
                $start = array_pop($savedStarts) ?? 0;
            } elseif (($id === T_WHITESPACE && $atValueStart) || $this->isOperator($id, $text)) {
                $start = strlen($res);
            }
        }

        return $res;
    }

    /** Index of the next token that is not whitespace. */
    private function nextToken(array $tokens, int $i): int
    {
        do {
            $i++;
        } while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE);
        return $i;
    }

    private function isOperator(?int $id, string $text): bool
    {
        if ($id === null) {
            return in_array($text, ['+', '-', '*', '/', '%', '=', '<', '>', '!', '?', ':', ',', '&', '^', '~', '@', ';'], true);
        }
        return in_array($id, [
            T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL,
            T_IS_GREATER_OR_EQUAL, T_SPACESHIP, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR,
            T_LOGICAL_XOR, T_COALESCE, T_COALESCE_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL,
            T_CONCAT_EQUAL, T_MOD_EQUAL, T_POW, T_POW_EQUAL, T_SL, T_SR, T_DOUBLE_ARROW, T_AS, T_INSTANCEOF,
            T_ECHO, T_PRINT, T_RETURN,
        ], true);
    }

    /**
     * Points the error at the template line that caused it, instead of the cache file.
     * Only runs when something already went wrong: rendering pays nothing for it.
     */
    private function mapToTemplate(Throwable $e): Throwable
    {
        // Already mapped by a nested render (include, layout).
        if (in_array($e->getFile(), $this->sources, true)) {
            return $e;
        }

        // The first cache file met, from the error up the call stack, is the culprit.
        $frames = array_merge([['file' => $e->getFile(), 'line' => $e->getLine()]], $e->getTrace());
        foreach ($frames as $frame) {
            $template = $this->sources[basename($frame['file'] ?? '')] ?? null;
            if ($template !== null) {
                return $this->templateError($e, $template, $frame['line']);
            }
        }

        return $e;
    }

    private function templateError(Throwable $e, string $template, int $line): ErrorException
    {
        return new ErrorException(
            "PurePlate Error: {$e->getMessage()} [At: {$template}:{$line}]",
            0,
            E_USER_ERROR,
            $template,
            $line,
            $e
        );
    }

    private function syntaxError(string $message, int $line): ErrorException
    {
        return new ErrorException($message, 0, E_USER_ERROR, '', $line);
    }

    private function getCacheFilename(string $templatePath): string
    {
        $basename = pathinfo($templatePath, PATHINFO_FILENAME);
        return $this->cacheDir . DIRECTORY_SEPARATOR . $basename . '_' . md5($templatePath) . '.cache.php';
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
