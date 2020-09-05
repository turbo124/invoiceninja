<?php

namespace App\Helpers\Email;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use League\CommonMark\CommonMarkConverter;

class EmailBuilder
{
    public $subject;
    public $body;
    public $recipients;
    public $attachments;
    public $footer;
    public $template_style;
    public $variables = [];
    public $contact = null;
    public $view_link;
    public $view_text;

    private function parseTemplate(string $data, bool $is_markdown = true, $contact = null): string
    {
        //process variables
        if (! empty($this->variables)) {
            $data = str_replace(array_keys($this->variables), array_values($this->variables), $data);
        }

        //process markdown
        if ($is_markdown) {
            $converter = new CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => true,
            ]);

            $data = $converter->convertToHtml($data);
        }

        return $data;
    }

    /**
     * @param $footer
     * @return $this
     */
    public function setFooter($footer)
    {
        $this->footer = $footer;

        return $this;
    }

    public function setVariables($variables)
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * @param $contact
     * @return $this
     */
    public function setContact($contact)
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * @param $subject
     * @return $this
     */
    public function setSubject($subject)
    {
        //$this->subject = $this->parseTemplate($subject, false, $this->contact);

        if (! empty($this->variables)) {
            $subject = str_replace(array_keys($this->variables), array_values($this->variables), $subject);
        }

        $this->subject = $subject;

        return $this;
    }

    /**
     * @param $body
     * @return $this
     */
    public function setBody($body)
    {
        //$this->body = $this->parseTemplate($body, true);

        if (! empty($this->variables)) {
            $body = str_replace(array_keys($this->variables), array_values($this->variables), $body);
        }

        $this->body = $body;

        return $this;
    }

    /**
     * @param $template_style
     * @return $this
     */
    public function setTemplate($template_style)
    {
        $this->template_style = $template_style;

        return $this;
    }

    public function setAttachments($attachments)
    {
        $this->attachments[] = $attachments;

        return $this;
    }

    public function setViewLink($link)
    {
        $this->view_link = $link;

        return $this;
    }

    public function setViewText($text)
    {
        $this->view_text = $text;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return mixed
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * @return mixed
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @return mixed
     */
    public function getFooter()
    {
        return $this->footer;
    }

    /**
     * @return mixed
     */
    public function getTemplate()
    {
        return $this->template_style;
    }

    public function getViewLink()
    {
        return $this->view_link;
    }

    public function getViewText()
    {
        return $this->view_text;
    }
}
