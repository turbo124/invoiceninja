<?php

namespace App\Services\Pdf;

class Purify
{
    private static array $allowed_elements = [
        // Document structure
        'html', 'head', 'body', 'meta', 'title', 'style', 'script',

        // Root element
        'root',

        // Block Elements
        'div', 'p', 'section', 'header', 'footer',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre',

        // Text Elements
        'span', 'strong', 'em', 'b', 'i', 'u', 'small',
        'sub', 'sup', 'del', 'ins',

        // Line Breaks
        'br', 'hr',

        // Lists
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',

        // Tables
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',

        // Media & Links
        'img', 'a',

        // Template specific
        'ninja'
    ];

    private static array $allowed_attributes = [
        // Global Attributes
        'class' => ['*'],
        'id' => ['*'],
        'style' => ['*'],
        'title' => ['*'],
        'lang' => ['*'],
        'dir' => ['*'],  // Allow all dir values
        'tabindex' => ['*'],
        'data-*' => ['*'], // Custom data attributes
        'data-ref' => ['*'],
        'data-element' => ['*'],
        'data-state' => ['*'],

        // Layout & Presentation
        'align' => ['left', 'center', 'right', 'justify'],
        'valign' => ['top', 'middle', 'bottom', 'baseline'],
        'width' => ['*'],
        'height' => ['*'],
        'cellspacing' => ['*'],
        'cellpadding' => ['*'],
        'border' => ['*'],
        'min-width' => ['*'],
        'max-width' => ['*'],

        // Table-specific
        'colspan' => ['*'],
        'rowspan' => ['*'],
        'scope' => ['row', 'col', 'rowgroup', 'colgroup'],
        'headers' => ['*'],

        // Links & Media
        'href' => ['http://*', 'https://*', 'data:image/*', '${*}'],
        'src' => ['http://*', 'https://*', 'data:image/*', '${*}'],
        'alt' => ['*'],
        'target' => ['_blank', '_self'],
        'rel' => ['nofollow', 'noopener', 'noreferrer'],

        // Lists
        'type' => ['1', 'A', 'a', 'I', 'i', 'disc', 'circle', 'square'],
        'start' => ['*'],

        // Accessibility
        'aria-*' => ['*'],
        'role' => ['*'],

        // Template specific
        'hidden' => ['*'],
        'zoom' => ['*'],
        'size' => ['*']
    ];

    private static array $dangerous_css_patterns = [
            // JavaScript execution patterns
            '/expression\s*\(/', // CSS expressions
            '/javascript\s*:/',  // JavaScript protocol
            '/behaviour\s*:/',   // IE behavior
            '/-moz-binding\s*:/', // Mozilla binding

            // URL patterns that might lead to script execution
            '/url\s*\(\s*[^)]*(?:javascript|data|vbscript)/i',

            // Import directives
            '/@import\s/',           // Added proper delimiters

            // Other potentially dangerous properties
            '/-o-link\s*:/',
            '/-o-link-source\s*:/',
            '/-o-replace\s*:/',
            '/call\s*\(/',
            '/position\s*:\s*fixed/i',

            // Common attack vectors
            '/background(?:-image)?\s*:\s*[^;]*(?:url|expression|javascript|data|vbscript)/i',

            // IE-specific expressions
            '/progid\s*:/',
            '/setExpression\s*\(/',
            '/AlphaImageLoader\s*\(/'
        ];

    private static array $dangerous_css_properties = [
        'behavior',
        '-moz-binding',
        'pointer-events',
        'expression',
        'clip-path',
        'mask',
        'filter',
        'backdrop-filter',
    ];

    private static array $allowed_js_methods = [
        'document.addEventListener',
        'document.getElementById',
        'document.querySelector',
        'document.querySelectorAll',
        'forEach',
        'style.setProperty',
        'console.log'
    ];

    private static array $allowed_js_properties = [
        'childElementCount',
        'style',
        'hidden',
        'display'
    ];

    private static function isAllowedScript(string $script): bool
    {
        // Allow both entry points with comments and whitespace
        if (!preg_match('/^\s*(?:\/\/[^\n]*\n\s*)*(?:document\.(?:addEventListener\s*\(\s*[\'"]DOMContentLoaded[\'"]\s*,|querySelectorAll\s*\())/', $script)) {
            return false;
        }

        $tokens = token_get_all('<?php ' . $script);

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenText = $token[1];

                // Block dangerous methods

                if (preg_match('/\b(window|global|globalThis|this|self|top|parent|frames|eval|Function|setTimeout|setInterval|fetch|XMLHttpRequest|WebSocket|Ajax|Promise|async|await|createElement|appendChild|insertBefore|write|prepend|append|insertAfter|replaceWith|innerHTML|outerHTML|insertAdjacentHTML|document\.body|document\.head|document\.domain|document\.write|document\.writeln|constructor|prototype|__proto__|Image|Script|Object|btoa|atob|encodeURI|decodeURI|FileReader|Blob|Buffer)\b/', $tokenText)) {
                    return false;
                }

                // Allow specific property assignments
                if (preg_match('/\.(hidden|style|childElementCount)\s*=/', $tokenText)) {
                    continue;
                }

                // Allow specific method calls
                if (preg_match('/\b([a-zA-Z]+(?:\.[a-zA-Z]+)*)\s*\(/', $tokenText, $matches)) {
                    $method = $matches[1];
                    if (!in_array($method, [
                        'document.addEventListener',
                        'document.querySelectorAll',
                        'document.getElementById',
                        'forEach',
                        'style.setProperty',
                        'console.log'
                    ])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
    
    /**
     * Filter CSS to remove potentially dangerous styles
     */
    private static function filterCssStyles(string $css): string
    {
        // Remove comments that might hide malicious code
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Convert to lowercase for consistent checking
        $css_lower = strtolower($css);

        // Check for dangerous patterns
        foreach (self::$dangerous_css_patterns as $pattern) {
            if (preg_match($pattern, $css_lower)) {
                return ''; // Return empty if dangerous pattern found
            }
        }

        // Split into individual declarations
        $declarations = array_filter(array_map('trim', explode(';', $css)));
        $safe_declarations = [];

        foreach ($declarations as $declaration) {
            // Split property and value
            $parts = array_map('trim', explode(':', $declaration, 2));
            if (count($parts) !== 2) {
                continue;
            }

            [$property, $value] = $parts;
            $property = strtolower($property);

            // Skip dangerous properties
            if (in_array($property, self::$dangerous_css_properties)) {
                continue;
            }

            // Additional URL safety check
            if (stripos($value, 'url(') !== false) {
                // Only allow specific URL patterns
                if (!preg_match('/url\s*\(\s*[\'"]?(https?:\/\/[^"\'\)]+)[\'"]?\s*\)/i', $value)) {
                    continue;
                }
            }

            $safe_declarations[] = $property . ': ' . $value;
        }

        return implode('; ', $safe_declarations);
    }

    public static function purify(\DOMDocument $document): string
    {
        if (!$document->documentElement) {
            return '';
        }

        // Function to recursively check nodes
        $cleanNodes = function ($node) use (&$cleanNodes, &$allowed_elements, &$allowed_attributes) {

            $allowed_elements = self::$allowed_elements;
            $allowed_attributes = self::$allowed_attributes;

            if (!$node) {
                return;
            }

            // Store children in array first to avoid modification during iteration
            $children = [];
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    $children[] = $child;
                }
            }

            // Process each child
            foreach ($children as $child) {
                $cleanNodes($child);
            }

            // Only process element nodes
            if ($node instanceof \DOMElement) {
                // Remove element if not in allowed list
                if (!in_array(strtolower($node->tagName), $allowed_elements)) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                    return;
                }

                if (strtolower($node->tagName) === 'script') {
                    if (!self::isAllowedScript($node->textContent)) {
                        if ($node->parentNode) {
                            $node->parentNode->removeChild($node);
                        }
                        return;
                    }
                    // If script is allowed, continue with normal processing
                }

                // Store current attributes before removing them
                $current_attributes = [];
                foreach ($node->attributes as $attr) {
                    $current_attributes[$attr->name] = $attr->value;
                }

                // First, remove ALL attributes from the node
                while ($node->attributes->length > 0) {
                    $node->removeAttribute($node->attributes->item(0)->name);
                }

                // Then add back only the allowed attributes
                foreach ($current_attributes as $name => $value) {
                    $attr_name = strtolower($name);

                    // Add special handling for style attributes
                    if ($attr_name === 'style') {
                        $filtered_css = self::filterCssStyles($value);
                        if (!empty($filtered_css)) {
                            $node->setAttribute($name, $filtered_css);
                        }
                        continue;
                    }

                    // Handle data-* attributes
                    if (strpos($attr_name, 'data-') === 0 && isset($allowed_attributes['data-*'])) {
                        $node->setAttribute($name, $value);
                        continue;
                    }

                    // Handle aria-* attributes
                    if (strpos($attr_name, 'aria-') === 0 && isset($allowed_attributes['aria-*'])) {
                        $node->setAttribute($name, $value);
                        continue;
                    }

                    // Skip if attribute isn't in allowed list
                    if (!isset($allowed_attributes[$attr_name])) {
                        continue;
                    }

                    $allowed_values = $allowed_attributes[$attr_name];

                    // Special handling for URLs (src and href)
                    if (($attr_name === 'src' || $attr_name === 'href') && !empty($allowed_values)) {
                        $is_allowed = false;

                        // Debug log
                        info("Checking URL attribute {$attr_name} with value: {$value}");

                        foreach ($allowed_values as $pattern) {
                            // Fix the pattern conversion for URL matching
                            if ($pattern === 'http://*') {
                                $regex = '^http\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(\/\S*)?$';
                            } elseif ($pattern === 'https://*') {
                                $regex = '^https\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(\/\S*)?$';
                            } elseif ($pattern === 'data:image/*') {
                                $regex = '^data\:image\/[a-zA-Z0-9\+]+;base64,.*$';
                            } else {
                                $regex = preg_quote($pattern, '/');
                                $regex = str_replace('\*', '.*', $regex);
                            }

                            // Debug log
                            info("Checking against pattern: {$pattern} with regex: {$regex}");

                            if (preg_match('/' . $regex . '/i', $value)) {
                                $is_allowed = true;
                                info("Match found! URL is allowed");
                                break;
                            }
                        }

                        if ($is_allowed) {
                            $node->setAttribute($name, $value);
                            info("Attribute set successfully");
                        } else {
                            info("URL was not allowed");
                        }
                        continue;
                    }

                    // For attributes that allow all values
                    if ($allowed_values === ['*']) {
                        $node->setAttribute($name, $value);
                        continue;
                    }

                    // For attributes with specific allowed values
                    if (in_array($value, $allowed_values)) {
                        $node->setAttribute($name, $value);
                    }
                }
            }
        };

        try {
            $cleanNodes($document->documentElement);

            return $document->saveHTML();

        } catch (\Exception $e) {

            nlog('Error cleaning HTML: ' . $e->getMessage());

            throw new \RuntimeException('HTML sanitization failed');
        }

    }

}
