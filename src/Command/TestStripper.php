<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Command;

/**
 * Strips inline tests from PHP source code.
 *
 * Handles:
 * - Methods with test-related attributes (#[Test], #[Before], etc.)
 * - Factory methods (#[Factory], #[DefaultFactory])
 * - Data provider methods referenced by #[DataProvider]
 * - Entire \Tests sub-namespaces
 * - Test-related use statements
 */
final class TestStripper
{
    /** @var array<string> Attributes that mark methods for removal */
    private const TEST_ATTRIBUTES = [
        'Test',
        'Before',
        'After',
        'BeforeClass',
        'AfterClass',
        'Factory',
        'DefaultFactory',
    ];

    /** @var array<string> Use statement patterns to remove */
    private const TEST_USE_PATTERNS = [
        'PHPUnit\\Framework\\Attributes\\Test',
        'PHPUnit\\Framework\\Attributes\\Before',
        'PHPUnit\\Framework\\Attributes\\After',
        'PHPUnit\\Framework\\Attributes\\BeforeClass',
        'PHPUnit\\Framework\\Attributes\\AfterClass',
        'PHPUnit\\Framework\\Attributes\\DataProvider',
        'PHPUnit\\Framework\\Attributes\\TestDox',
        'NSRosenqvist\\PHPUnitInline\\Attributes\\Factory',
        'NSRosenqvist\\PHPUnitInline\\Attributes\\DefaultFactory',
    ];

    public function strip(string $content): string
    {
        // First, collect data providers referenced by tests
        $dataProviders = $this->collectDataProviders($content);

        // Remove entire \Tests sub-namespaces
        $content = $this->removeTestsNamespaces($content);

        // Remove test methods/functions and their attributes
        $content = $this->removeTestMethods($content);

        // Remove factory methods
        $content = $this->removeFactoryMethods($content);

        // Remove data provider methods/functions
        $content = $this->removeDataProviders($content, $dataProviders);

        // Remove test-related use statements
        $content = $this->removeTestUseStatements($content);

        // Clean up multiple blank lines
        $content = $this->cleanupBlankLines($content);

        return $content;
    }

    /**
     * Collect all data provider names referenced in the content.
     *
     * @return array<string>
     */
    private function collectDataProviders(string $content): array
    {
        $providers = [];

        // Match #[DataProvider('methodName')] or #[DataProvider("methodName")]
        if (preg_match_all('/#\[DataProvider\([\'"]([^\'"]+)[\'"]\)\]/', $content, $matches)) {
            $providers = array_merge($providers, $matches[1]);
        }

        return array_unique($providers);
    }

    /**
     * Remove entire \Tests sub-namespaces including all their contents.
     */
    private function removeTestsNamespaces(string $content): string
    {
        $tokens = token_get_all($content);
        $result = '';
        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            // Check for namespace declaration
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespaceStart = $i;
                $namespaceName = '';
                $j = $i + 1;

                // Collect namespace name
                while ($j < $count) {
                    $t = $tokens[$j];
                    if (is_array($t)) {
                        if (in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                            $namespaceName .= $t[1];
                        } elseif ($t[0] !== T_WHITESPACE) {
                            break;
                        }
                    } elseif ($t === ';' || $t === '{') {
                        break;
                    }
                    $j++;
                }

                // Check if this is a \Tests sub-namespace (ending in \Tests or containing \Tests\)
                // But NOT namespaces like \Fixtures or \TestCase which just have "Test" in them
                if (preg_match('/\\\\Tests$/', $namespaceName) || preg_match('/\\\\Tests\\\\/', $namespaceName)) {
                    // Find the end of this namespace block
                    // If using braces, find matching brace; if semicolon, find next namespace
                    while ($j < $count && $tokens[$j] !== ';' && $tokens[$j] !== '{') {
                        $j++;
                    }

                    if ($j < $count && $tokens[$j] === '{') {
                        // Brace-style namespace - find matching closing brace
                        $namespaceBraceDepth = 1;
                        $j++;
                        while ($j < $count && $namespaceBraceDepth > 0) {
                            if ($tokens[$j] === '{') {
                                $namespaceBraceDepth++;
                            } elseif ($tokens[$j] === '}') {
                                $namespaceBraceDepth--;
                            }
                            $j++;
                        }
                        $i = $j;
                        continue;
                    } else {
                        // Semicolon-style namespace - skip until next namespace or EOF
                        $j++;
                        while ($j < $count) {
                            $t = $tokens[$j];
                            if (is_array($t) && $t[0] === T_NAMESPACE) {
                                break;
                            }
                            $j++;
                        }
                        $i = $j;
                        continue;
                    }
                }
            }

            // Output token if not in tests namespace
            if (is_array($token)) {
                $result .= $token[1];
            } else {
                $result .= $token;
            }
            $i++;
        }

        return $result;
    }

    /**
     * Remove methods/functions with test attributes.
     */
    private function removeTestMethods(string $content): string
    {
        // Remove methods with test attributes
        foreach (self::TEST_ATTRIBUTES as $attr) {
            // Match method with attribute (handles multi-line)
            $pattern = '/(\s*)((?:#\[[^\]]*\]\s*)*#\[' . preg_quote($attr, '/') . '(?:\([^\)]*\))?\]\s*(?:#\[[^\]]*\]\s*)*)' .
                       '((?:private|protected|public)\s+(?:static\s+)?function\s+\w+\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{)/s';

            while (preg_match($pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
                /** @var array<int, array{0: string, 1: int<-1, max>}> $matches */
                $startPos = (int) $matches[0][1];
                $methodStartPos = (int) $matches[3][1];
                $bracePos = strpos($content, '{', $methodStartPos);

                if ($bracePos === false) {
                    break;
                }

                // Find matching closing brace
                $endPos = $this->findMatchingBrace($content, $bracePos);
                if ($endPos === false) {
                    break;
                }

                // Remove the method including leading whitespace
                $content = substr($content, 0, $startPos) . substr($content, $endPos + 1);
            }
        }

        // Remove standalone functions with test attributes
        foreach (self::TEST_ATTRIBUTES as $attr) {
            $pattern = '/(\s*)((?:#\[[^\]]*\]\s*)*#\[' . preg_quote($attr, '/') . '(?:\([^\)]*\))?\]\s*(?:#\[[^\]]*\]\s*)*)' .
                       '(function\s+\w+\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{)/s';

            while (preg_match($pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
                /** @var array<int, array{0: string, 1: int<-1, max>}> $matches */
                $startPos = (int) $matches[0][1];
                $functionStartPos = (int) $matches[3][1];
                $bracePos = strpos($content, '{', $functionStartPos);

                if ($bracePos === false) {
                    break;
                }

                $endPos = $this->findMatchingBrace($content, $bracePos);
                if ($endPos === false) {
                    break;
                }

                $content = substr($content, 0, $startPos) . substr($content, $endPos + 1);
            }
        }

        return $content;
    }

    /**
     * Remove factory methods.
     */
    private function removeFactoryMethods(string $content): string
    {
        // Already handled by removeTestMethods since Factory and DefaultFactory are in TEST_ATTRIBUTES
        return $content;
    }

    /**
     * Remove data provider methods/functions.
     *
     * @param array<string> $providers
     */
    private function removeDataProviders(string $content, array $providers): string
    {
        foreach ($providers as $provider) {
            // Remove method
            $pattern = '/\s*(?:private|protected|public)\s+(?:static\s+)?function\s+' .
                       preg_quote($provider, '/') . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{/s';

            if (preg_match($pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
                /** @var array<int, array{0: string, 1: int<-1, max>}> $matches */
                $startPos = (int) $matches[0][1];
                $bracePos = strpos($content, '{', $startPos);

                if ($bracePos !== false) {
                    $endPos = $this->findMatchingBrace($content, $bracePos);
                    if ($endPos !== false) {
                        $content = substr($content, 0, $startPos) . substr($content, $endPos + 1);
                    }
                }
            }

            // Remove standalone function
            $pattern = '/\s*function\s+' . preg_quote($provider, '/') . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{/s';

            if (preg_match($pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
                /** @var array<int, array{0: string, 1: int<-1, max>}> $matches */
                $startPos = (int) $matches[0][1];
                $bracePos = strpos($content, '{', $startPos);

                if ($bracePos !== false) {
                    $endPos = $this->findMatchingBrace($content, $bracePos);
                    if ($endPos !== false) {
                        $content = substr($content, 0, $startPos) . substr($content, $endPos + 1);
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Remove test-related use statements.
     */
    private function removeTestUseStatements(string $content): string
    {
        foreach (self::TEST_USE_PATTERNS as $pattern) {
            // Match use statement with this class
            $regex = '/\s*use\s+' . preg_quote($pattern, '/') . '\s*;\s*/';
            $content = preg_replace($regex, "\n", $content) ?? $content;

            // Also match aliased imports
            $regex = '/\s*use\s+' . preg_quote($pattern, '/') . '\s+as\s+\w+\s*;\s*/';
            $content = preg_replace($regex, "\n", $content) ?? $content;
        }

        // Remove grouped use statements that only contain test classes
        $content = preg_replace(
            '/\s*use\s+PHPUnit\\\\Framework\\\\Attributes\\\\{\s*[^}]*}\s*;\s*/',
            "\n",
            $content
        ) ?? $content;

        // Remove use function statements for test() helper
        $content = preg_replace(
            '/\s*use\s+function\s+test\s*;\s*/',
            "\n",
            $content
        ) ?? $content;

        return $content;
    }

    /**
     * Clean up multiple consecutive blank lines.
     */
    private function cleanupBlankLines(string $content): string
    {
        // Replace 3+ consecutive newlines with 2
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        // Remove trailing whitespace on lines
        $content = preg_replace('/[ \t]+$/m', '', $content) ?? $content;

        // Fix closing brace stuck to content (add newline before })
        $content = preg_replace('/([^\n\s{])(})/', "$1\n$2", $content) ?? $content;

        return $content;
    }

    /**
     * Find the position of the matching closing brace.
     */
    private function findMatchingBrace(string $content, int $openPos): int|false
    {
        $depth = 1;
        $len = strlen($content);
        $i = $openPos + 1;
        $inString = false;
        $stringChar = '';
        $inComment = false;
        $commentType = '';

        while ($i < $len && $depth > 0) {
            $char = $content[$i];
            $nextChar = $i + 1 < $len ? $content[$i + 1] : '';

            // Handle string context
            if (!$inComment && !$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
                $i++;
                continue;
            }

            if ($inString) {
                if ($char === '\\') {
                    $i += 2; // Skip escaped character
                    continue;
                }
                if ($char === $stringChar) {
                    $inString = false;
                }
                $i++;
                continue;
            }

            // Handle comment context
            if (!$inComment) {
                if ($char === '/' && $nextChar === '/') {
                    $inComment = true;
                    $commentType = 'line';
                    $i += 2;
                    continue;
                }
                if ($char === '/' && $nextChar === '*') {
                    $inComment = true;
                    $commentType = 'block';
                    $i += 2;
                    continue;
                }
            }

            if ($inComment) {
                if ($commentType === 'line' && $char === "\n") {
                    $inComment = false;
                } elseif ($commentType === 'block' && $char === '*' && $nextChar === '/') {
                    $inComment = false;
                    $i += 2;
                    continue;
                }
                $i++;
                continue;
            }

            // Track braces
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }

            $i++;
        }

        return $depth === 0 ? $i - 1 : false;
    }
}
