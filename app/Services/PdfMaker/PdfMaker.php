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

    public $xpath;

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


        $elements = [
                    'product-table', 'task-table', 'delivery-note-table',
                    'statement-invoice-table', 'statement-payment-table', 'statement-aging-table-totals',
                    'statement-invoice-table-totals', 'statement-payment-table-totals', 'statement-aging-table',
                    'client-details', 'vendor-details', 'swiss-qr', 'shipping-details', 'statement-credit-table', 'statement-credit-table-totals',
                ];

        foreach ($elements as $element) {

            $el = $this->document->getElementById($element);

            if ($el && $el->childElementCount === 0) {
                $el->setAttribute('style', 'display: none !important;');
            }

        }


        $xpath = new \DOMXPath($this->document);
        $elements = $xpath->query('//*[@data-state="encoded-html"]');

        nlog("count = > ".count($elements));

        foreach ($elements as $element) {
                    
                    
            $decoded = htmlspecialchars_decode($element->textContent, ENT_QUOTES | ENT_HTML5);
            $decoded = str_replace(['<br>', '<BR>'], '<br/>', $decoded);

            // Create a temporary document to parse the HTML
            $temp = new \DOMDocument();
            @$temp->loadHTML('<div>' . $decoded . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            // Get the content from the temporary document
            $content = $temp->getElementsByTagName('div')->item(0);

            if ($content) {
                // Import and replace
                $imported = $this->document->importNode($content, true);
                $element->parentNode->replaceChild($imported, $element);
            }

            nlog($decoded);

            // $clone = $element->cloneNode(true);

            // // Filter allowed tags?
            // $clone->textContent = htmlspecialchars_decode($clone->textContent, ENT_QUOTES | ENT_HTML5);
            // $clone->textContent = str_replace(['<br>', '<BR>'], '<br/>', $clone->textContent);

            // if ($clone) {
            //     $imported = $this->document->importNode($clone, true);
            //     $element->parentNode->replaceChild($imported, $element);
            // }

            // nlog($clone->textContent);
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

        $html = \App\Services\Pdf\Purify::clean($this->document->saveHTML());

        return $html;

    }

}
