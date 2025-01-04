<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\PdfMaker;

use App\Services\Template\TemplateService;
use League\CommonMark\CommonMarkConverter;

class PdfMaker
{
    use PdfMakerUtilities;

    protected $data;

    public $design;

    public $html;

    public $document;

    private $options;

    /** @var CommonMarkConverter */
    protected $commonmark;

    public function __construct(array $data)
    {
        $this->data = $data;

        if (array_key_exists('options', $data)) {
            $this->options = $data['options'];
        }

        $this->commonmark = new CommonMarkConverter([
            'allow_unsafe_links' => false,
            // 'html_input' => 'allow',
        ]);
    }

    public function design(Design $design)
    {
        $this->design = $design;

        $this->initializeDomDocument();

        return $this;
    }

    public function build()
    {
        if (isset($this->data['template']) && isset($this->data['variables'])) {
            $this->getEmptyElements($this->data['template'], $this->data['variables']);
        }

        if (isset($this->data['template'])) {
            $this->updateElementProperties($this->data['template']);
        }

        if (isset($this->options)) {

            $replacements = [];
            $contents = $this->document->getElementsByTagName('ninja');

            $ts = new TemplateService();

            if (isset($this->options['client'])) {
                $client = $this->options['client'];
                try {
                    $ts->setCompany($client->company);
                    $ts->addGlobal(['currency_code' => $client->company->currency()->code]);
                } catch (\Exception $e) {
                    nlog($e->getMessage());
                }
            }

            if (isset($this->options['vendor'])) {
                $vendor = $this->options['vendor'];
                try {
                    $ts->setCompany($vendor->company);
                    $ts->addGlobal(['currency_code' => $vendor->company->currency()->code]);
                } catch (\Exception $e) {
                    nlog($e->getMessage());
                }
            }

            $data = $ts->processData($this->options)->setGlobals()->getData();
            $twig = $ts->twig;

            foreach ($contents as $content) {

                $template = $content->ownerDocument->saveHTML($content);

                $template = $twig->createTemplate(html_entity_decode($template));
                $template = $template->render($data);

                $f = $this->document->createDocumentFragment();
                $f->appendXML($template);
                $replacements[] = $f;

            }

            foreach ($contents as $key => $content) {
                $content->parentNode->replaceChild($replacements[$key], $content);
            }

        }

        if (isset($this->data['variables'])) {
            $this->updateVariables($this->data['variables']);
        }

        return $this;
    }

    /**
     * Final method to get compiled HTML.
     *
     * @param bool $final
     * @return mixed
     */
    public function getCompiledHTML($final = false)
    {
        $this->cleanHtml();

        $html = $this->document->saveHTML();

        return str_replace('%24', '$', $html);
    }


    private function cleanHtml(): self
    {
        if (!$this->document || !$this->document->documentElement) {
            return $this;
        }

        $dangerous_elements = [
            'iframe', 'form', 'object', 'embed', 
            'applet', 'audio', 'video',
            'frame', 'frameset', 'base','svg'
        ];

        $dangerous_attributes = [
            'onabort', 'onblur', 'onchange', 'onclick', 'ondblclick', 
            'onerror', 'onfocus', 'onkeydown', 'onkeypress', 'onkeyup', 
            'onload', 'onmousedown', 'onmousemove', 'onmouseout', 
            'onmouseover', 'onmouseup', 'onreset', 'onresize', 
            'onselect', 'onsubmit', 'onunload'
        ];

        // Function to recursively check nodes
        $removeNodes = function ($node) use (&$removeNodes, $dangerous_elements, $dangerous_attributes) {
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
                $removeNodes($child);
            }

            // Only process element nodes
            if ($node instanceof \DOMElement) {
                // Remove dangerous elements
                if (in_array(strtolower($node->tagName), $dangerous_elements)) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                    return;
                }

                // Remove dangerous attributes
                $attributes_to_remove = [];
                foreach ($node->attributes as $attr) {
                    $attr_name = strtolower($attr->name);
                    $attr_value = strtolower($attr->value);

                    // Remove event handlers
                    if (in_array($attr_name, $dangerous_attributes) || strpos($attr_name, 'on') === 0) {
                        $attributes_to_remove[] = $attr->name;
                        continue;
                    }

                    // Remove dangerous URLs/protocols
                    if (in_array($attr_name, ['data', 'href', 'meta', 'link'])) {
                        if (preg_match('/(javascript|data|file|ftp|jar|dict|gopher|ldap|smb|php|alert|prompt|confirm):|\/\/\/\/+|127\.0\.0\.1|localhost/i', $attr_value)) {
                            $attributes_to_remove[] = $attr->name;
                            continue;
                        }
                    }else if ($attr_name === 'src') {
                        // For src attributes, only block dangerous protocols but allow data:image
                        if (preg_match('/(javascript|file|ftp|jar|dict|gopher|ldap|smb|php):|\/\/\/\/+|127\.0\.0\.1|localhost/i', $attr_value)) {
                            $attributes_to_remove[] = $attr->name;
                            continue;
                        }
                        // Additional check for data: URLs - only allow image types
                        if (strpos($attr_value, 'data:') === 0 && !preg_match('/^data:image\//i', $attr_value)) {
                            $attributes_to_remove[] = $attr->name;
                            continue;
                        }
                        
                        // Check for localhost references
                        if (preg_match('/localhost|127\.|0\.0\.0\.0|::1|0:0:0:0:0:0:0:1/i', $attr_value)) {
                            $attributes_to_remove[] = $attr->name;
                            continue;
                        }

                    }elseif ($attr_name === 'style') {
                        // if (preg_match('/(expression|javascript|behavior|vbscript):|url\s*\(|import/i', $attr_value)) {
                        //     $attributes_to_remove[] = $attr->name;
                        // }
                        
                        if (preg_match('/(expression|javascript|behavior|vbscript):|url\s*\(|import|@import|eval\s*\(|-moz-binding|behavior|expression/i', $attr_value)) {
                            $attributes_to_remove[] = $attr->name;
                            continue;
                        }

                    }

                    // Remove expressions
                    if (preg_match('/expression|javascript:|vbscript:|livescript:/i', $attr_value)) {
                        $attributes_to_remove[] = $attr->name;
                        continue;
                    }
                }

                // Remove the collected dangerous attributes
                foreach ($attributes_to_remove as $attr) {
                    $node->removeAttribute($attr);
                }
            }
        };

        try {
            $removeNodes($this->document->documentElement);
        } catch (\Exception $e) {
            info('Error cleaning HTML: ' . $e->getMessage());
            
            // Clear the document to prevent unsanitized content
            $this->document = new \DOMDocument();

            // Throw sanitized exception to alert calling code
            throw new \RuntimeException('HTML sanitization failed');

        }

        return $this;
    }


}
