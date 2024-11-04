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

namespace App\Services\EDocument\Standards\Validation;

class XsltDocumentValidator
{
    private array $stylesheets = [
        '/Services/EDocument/Standards/Validation/Peppol/Stylesheets/CEN-EN16931-UBL.xslt',
        '/Services/EDocument/Standards/Validation/Peppol/Stylesheets/PEPPOL-EN16931-UBL.xslt',
    ];

    private string $ubl_xsd = 'app/Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-Invoice-2.1.xsd';

    private array $errors = [];

    public function __construct(public string $xml_document)
    {
    }

    /**
     * Validate the XSLT document
     *
     * @return self
     */
    public function validate(): self
    {
        $this->validateXsd()
             ->validateSchema();

        return $this;
    }

    private function validateSchema(): self
    {

        try{
            $processor = new \Saxon\SaxonProcessor();

            $xslt = $processor->newXslt30Processor();

            foreach($this->stylesheets as $stylesheet)
            {
                $xdmNode = $processor->parseXmlFromString($this->xml_document);
                
                /** @var \Saxon\XsltExecutable $xsltExecutable */
                $xsltExecutable = $xslt->compileFromFile(app_path($stylesheet)); //@phpstan-ignore-line
                $result = $xsltExecutable->transformToValue($xdmNode);

                if($result->size() == 0)
                    continue;

                for ($x=0; $x<$result->size(); $x++) 
                {
                    $a = $result->itemAt($x);

                    if(strlen($a->getStringValue() ?? '') > 1)
                        $this->errors['stylesheet'][] = $a->getStringValue();
                }

            }
            
        }
        catch(\Exception $e){

            $this->errors['general'][] = $e->getMessage();
        }   

        return $this;

    }

    private function validateXsd(): self
    {
                
        libxml_use_internal_errors(true);

        $xml = new \DOMDocument();
        $xml->loadXML($this->xml_document);

        if (!$xml->schemaValidate($this->ubl_xsd)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $errorMessages = [];
            foreach ($errors as $error) {
                $this->errors['xsd'] = sprintf(
                    'Line %d: %s',
                    $error->line,
                    trim($error->message)
                );
            }

        }

        return $this;
    }

    public function setStyleSheets(array $stylesheets): self
    {
        $this->stylesheets = $stylesheets;
        
        return $this;
    }

    public function getStyleSheets(): array
    {
        return $this->stylesheets;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

}
