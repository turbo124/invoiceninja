<?php

namespace App\Ninja\Tickets\Inbound;

use Illuminate\Support\Facades\File;

/**
 * Class Attachment.
 */
class Attachment extends InboundTicketFactory
{
    /**
     * @var Attachment
     */
    protected $attachment;

    /**
     * Attachment constructor.
     *
     * @param bool $attachment
     */
    public function __construct($attachment)
    {
        $this->attachment = $attachment;
        $this->Name = $this->attachment->Name;
        $this->ContentType = $this->attachment->ContentType;
        $this->ContentLength = $this->attachment->ContentLength;
        $this->Content = $this->attachment->Content;
    }

    /**
     * @return string
     */
    private function _read()
    {
        return base64_decode(chunk_split($this->attachment->Content));
    }

    /**
     * @return mixed
     */
    public function download()
    {
        $directory = sys_get_temp_dir();
        file_put_contents($directory.'/'.$this->Name, $this->_read());

        return File::get($directory.$this->Name);
    }
}
