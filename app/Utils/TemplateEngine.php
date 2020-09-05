<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils;

use App\DataMapper\EmailTemplateDefaults;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\MakesTemplateData;
use League\CommonMark\CommonMarkConverter;

class TemplateEngine
{
    use MakesHash;
    use MakesTemplateData;
    use MakesInvoiceHtml;

    public $body;

    public $subject;

    public $entity;

    public $entity_id;

    public $template;

    private $entity_obj;

    private $settings_entity;

    public function __construct($body, $subject, $entity, $entity_id, $template)
    {
        $this->body = $body;

        $this->subject = $subject;

        $this->entity = $entity;

        $this->entity_id = $entity_id;

        $this->template = $template;

        $this->entity_obj = null;

        $this->settings_entity = null;
    }

    public function build()
    {
        return $this->setEntity()
                 ->setSettingsObject()
                 ->setTemplates()
                 ->replaceValues()
                 ->renderTemplate();
    }

    private function setEntity()
    {
        if (strlen($this->entity) > 1 && strlen($this->entity_id) > 1) {
            $class = 'App\Models\\'.ucfirst($this->entity);
            $this->entity_obj = $class::whereId($this->decodePrimaryKey($this->entity_id))->company()->first();
        }

        return $this;
    }

    private function setSettingsObject()
    {
        if ($this->entity_obj) {
            $this->settings_entity = $this->entity_obj->client;
        } else {
            $this->settings_entity = auth()->user()->company();
        }

        return $this;
    }

    /* If the body / subject are not populated we need to get the defaults */
    private function setTemplates()
    {
        if (strlen($this->subject) == 0 && strlen($this->template) > 1) {
            $subject_template = str_replace('template', 'subject', $this->template);
            $this->subject = EmailTemplateDefaults::getDefaultTemplate($subject_template, $this->settings_entity->locale());
        }

        if (strlen($this->body) == 0 && strlen($this->template) > 1) {
            $this->body = EmailTemplateDefaults::getDefaultTemplate($this->template, $this->settings_entity->locale());
        }

        return $this;
    }

    private function replaceValues()
    {
        if ($this->entity_obj) {
            $this->entityValues($this->entity_obj->client->primary_contact()->first());
        } else {
            $this->fakerValues();
        }

        return $this;
    }

    private function fakerValues()
    {
        $labels = $this->makeFakerLabels();
        $values = $this->makeFakerValues();

        $this->body = strtr($this->body, $labels);
        $this->body = strtr($this->body, $values);

        $this->subject = strtr($this->subject, $labels);
        $this->subject = strtr($this->subject, $values);

        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        $this->body = $converter->convertToHtml($this->body);
    }

    private function entityValues($contact)
    {
        $data = $this->entity_obj->buildLabelsAndValues($contact);

        $this->body = strtr($this->body, $data['labels']);
        $this->body = strtr($this->body, $data['values']);

        $this->subject = strtr($this->subject, $data['labels']);
        $this->subject = strtr($this->subject, $data['values']);

        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        $this->body = $converter->convertToHtml($this->body);
    }

    private function renderTemplate()
    {
        /* wrapper */
        $email_style = $this->settings_entity->getSetting('email_style');

        $data['title'] = '';
        $data['body'] = '$body';
        $data['footer'] = '';
        $data['signature'] = $this->settings_entity->getSetting('email_signature');

        if ($email_style == 'custom') {
            $wrapper = $this->settings_entity->getSetting('email_style_custom');

            /*If no custom design exists, send back a blank!*/
            if (strlen($wrapper) > 1) {
                $wrapper = $this->renderView($wrapper, $data);
            } else {
                $wrapper = '';
            }
        } else {
            $wrapper = view($this->getTemplatePath($email_style), $data)->render();
            $injection = '<head><link rel="stylesheet" type="text/css" property="stylesheet" href="'.config('ninja.app_url').'/css/tailwind-1.2.0.css">';
            $wrapper = str_replace('<head>', $injection, $wrapper);
        }

        $data = [
            'subject' => $this->subject,
            'body' => $this->body,
            'wrapper' => $wrapper,
        ];

        return $data;
    }
}
