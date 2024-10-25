<?php

namespace App\Services\EDocument\Gateway\Storecove;

use App\Services\EDocument\Gateway\Storecove\Models\Tax;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use App\Services\EDocument\Gateway\Storecove\Models\InvoiceLines;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
use Symfony\Component\Serializer\SerializerInterface;


class PeppolToStorecoveNormalizer implements DenormalizerInterface
{
    private SerializerInterface $serializer;
    private ObjectNormalizer $objectNormalizer;


    public function __construct(SerializerInterface $serializer)
    {
      

$this->serializer = $serializer;

$classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
$metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
$this->objectNormalizer = new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter);

}

   public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
{
    $peppolInvoice = $data;
    $storecoveInvoice = new StorecoveInvoice();

    
    $storecoveInvoice->setDocumentCurrency($peppolInvoice->DocumentCurrencyCode ?? '');
    $storecoveInvoice->setInvoiceNumber($peppolInvoice->ID ?? '');
    $storecoveInvoice->setIssueDate($peppolInvoice->IssueDate);
    $storecoveInvoice->setDueDate($peppolInvoice->DueDate);
    $storecoveInvoice->setNote($peppolInvoice->Note ?? '');
    $storecoveInvoice->setAmountIncludingVat((float)($peppolInvoice->LegalMonetaryTotal->TaxInclusiveAmount->amount ?? 0));

    if (isset($peppolInvoice->InvoicePeriod[0])) {
        $storecoveInvoice->setInvoicePeriod([
            'startDate' => $peppolInvoice->InvoicePeriod[0]->StartDate,
            'endDate' => $peppolInvoice->InvoicePeriod[0]->EndDate,
        ]);
    }

    $storecoveInvoice->setReferences([
        'buyerReference' => $peppolInvoice->BuyerReference ?? '',
        'orderReference' => $peppolInvoice->OrderReference->ID->value ?? '',
    ]);

    if (isset($peppolInvoice->AccountingSupplierParty->Party)) {
        $supplier = $peppolInvoice->AccountingSupplierParty->Party;
        $storecoveInvoice->setAccountingSupplierParty([
            'name' => $supplier->PartyName[0]->Name ?? '',
            'vatNumber' => $supplier->PartyIdentification[0]->ID->value ?? '',
            'streetName' => $supplier->PostalAddress->StreetName ?? '',
            'cityName' => $supplier->PostalAddress->CityName ?? '',
            'postalZone' => $supplier->PostalAddress->PostalZone ?? '',
            'countryCode' => $supplier->PostalAddress->Country->IdentificationCode->value ?? '',
        ]);
    }

    if (isset($peppolInvoice->AccountingCustomerParty->Party)) {
        $customer = $peppolInvoice->AccountingCustomerParty->Party;
        $storecoveInvoice->setAccountingCustomerParty([
            'name' => $customer->PartyName[0]->Name ?? '',
            'vatNumber' => $customer->PartyIdentification[0]->ID->value ?? '',
            'streetName' => $customer->PostalAddress->StreetName ?? '',
            'cityName' => $customer->PostalAddress->CityName ?? '',
            'postalZone' => $customer->PostalAddress->PostalZone ?? '',
            'countryCode' => $customer->PostalAddress->Country->IdentificationCode->value ?? '',
        ]);
    }

    if (isset($peppolInvoice->PaymentMeans[0])) {
        $storecoveInvoice->setPaymentMeans([
            'paymentID' => $peppolInvoice->PaymentMeans[0]->PayeeFinancialAccount->ID->value ?? '',
        ]);
    }

    // Map tax total at invoice level
    $taxTotal = [];
    if (isset($peppolInvoice->InvoiceLine[0]->TaxTotal[0])) {
        $taxTotal[] = [
            'taxAmount' => (float)($peppolInvoice->InvoiceLine[0]->TaxTotal[0]->TaxAmount->amount ?? 0),
            'taxCurrency' => $peppolInvoice->DocumentCurrencyCode ?? '',
        ];
    }
    $storecoveInvoice->setTaxTotal($taxTotal);

    if (isset($peppolInvoice->InvoiceLine)) {
        $invoiceLines = [];
        foreach ($peppolInvoice->InvoiceLine as $line) {
            $invoiceLine = new InvoiceLines();
            $invoiceLine->setLineId($line->ID->value ?? '');
            $invoiceLine->setAmountExcludingVat((float)($line->LineExtensionAmount->amount ?? 0));
            $invoiceLine->setQuantity((float)($line->InvoicedQuantity ?? 0));
            $invoiceLine->setQuantityUnitCode(''); // Not present in the provided JSON
            $invoiceLine->setItemPrice((float)($line->Price->PriceAmount->amount ?? 0));
            $invoiceLine->setName($line->Item->Name ?? '');
            $invoiceLine->setDescription($line->Item->Description ?? '');

            $tax = new Tax();
            if (isset($line->TaxTotal[0])) {
                $taxTotal = $line->TaxTotal[0];
                $tax->setTaxAmount((float)($taxTotal->TaxAmount->amount ?? 0));
                
                if (isset($line->Item->ClassifiedTaxCategory[0])) {
                    $taxCategory = $line->Item->ClassifiedTaxCategory[0];
                    $tax->setTaxPercentage((float)($taxCategory->Percent ?? 0));
                    $tax->setTaxCategory($taxCategory->ID->value ?? '');
                }
                
                $tax->setTaxableAmount((float)($line->LineExtensionAmount->amount ?? 0));
            }
            $invoiceLine->setTax($tax);

            $invoiceLines[] = $invoiceLine;
        }
        $storecoveInvoice->setInvoiceLines($invoiceLines);
    }

    return $storecoveInvoice;


}

private function validateStorecoveInvoice(StorecoveInvoice $invoice): void
{
    $requiredFields = ['documentCurrency', 'invoiceNumber', 'issueDate', 'dueDate'];
    foreach ($requiredFields as $field) {
        if (empty($invoice->$field)) {
            throw new \InvalidArgumentException("Required field '$field' is missing or empty");
        }
    }

    if (empty($invoice->invoiceLines)) {
        throw new \InvalidArgumentException("Invoice must have at least one line item");
    }

    // Add more validations as needed
}

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === StorecoveInvoice::class && $data instanceof PeppolInvoice;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            StorecoveInvoice::class => true,
        ];
    }

    private function mapNestedProperties($object, array $nestedPaths): array
    {
        $result = [];
        foreach ($nestedPaths as $key => $path) {
            if (is_array($path)) {
                // Try multiple paths
                foreach ($path as $possiblePath) {
                    $value = $this->getValueFromPath($object, $possiblePath);
                    if ($value !== null) {
                        $result[$key] = $value;
                        nlog("Mapped nested property: $key", ['path' => $possiblePath, 'value' => $value]);
                        break;
                    }
                }
                if (!isset($result[$key])) {
                    nlog("Failed to map nested property: $key", ['paths' => $path]);
                }
            } else {
                $value = $this->getValueFromPath($object, $path);
                if ($value !== null) {
                    $result[$key] = $value;
                    nlog("Mapped nested property: $key", ['path' => $path, 'value' => $value]);
                } else {
                    nlog("Failed to map nested property: $key", ['path' => $path]);
                }
            }
        }
        return $result;
    }

    private function getValueFromPath($object, string $path)
    {
        $parts = explode('.', $path);
        $value = $object;
        foreach ($parts as $part) {
            if (preg_match('/(.+)\[(\d+)\]/', $part, $matches)) {
                $property = $matches[1];
                $index = $matches[2];
                $value = $value->$property[$index] ?? null;
            } else {
                $value = $value->$part ?? null;
            }
            if ($value === null) {
                nlog("Null value encountered in path: $path at part: $part");
                return null;
            }
        }
        return $value instanceof \DateTime ? $value->format('Y-m-d') : $value;
    }

    private function castValue(string $property, $value)
    {
        try {
            $reflectionProperty = new \ReflectionProperty(StorecoveInvoice::class, $property);
            $type = $reflectionProperty->getType();

            if ($type instanceof \ReflectionNamedType) {
                switch ($type->getName()) {
                    case 'float':
                        return (float) $value;
                    case 'int':
                        return (int) $value;
                    case 'string':
                        return (string) $value;
                    case 'bool':
                        return (bool) $value;
                    case 'array':
                        return (array) $value;
                    default:
                        return $value;
                }
            }
        } catch (\ReflectionException $e) {
            nlog("Error casting value for property: $property", ['error' => $e->getMessage()]);
        }

        return $value;
    }
}
