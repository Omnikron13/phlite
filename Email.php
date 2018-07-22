<?php
namespace Phlite;

require_once __DIR__.'/Base64.php';

class Email {
    protected const BOUNDARY_BYTES = 32;
    protected const CHARSET = 'UTF-8';

    protected $boundary  = NULL;
    protected $charset   = 'UTF-8';
    protected $headers   = [
        'MIME-Version' => '1.0',
    ];
    protected $to        = NULL;
    protected $subject   = NULL;
    protected $plaintext = '';
    protected $html      = '';

    public function __construct(string $to, string $subject='') {
        $this->to = $to;
        $this->subject = $subject;
        $this->boundary = Base64::encode(random_bytes(self::BOUNDARY_BYTES));
        $this->setHeader('Content-type', "multipart/alternative;boundary=$this->boundary");
    }

    public function setPlaintext(string $t) : void {
        $this->plaintext = $t;
    }

    //TODO: document
    public function setHTML(string $t) : void {
        $this->html = $t;
    }

    //TODO: document
    public function setHeader(string $k, string $v) : void {
        $this->headers[$k] = $v;
    }

    //TODO: document
    public function send() : void {
        mail(
            $this->to,
            $this->subject,
            $this->processBody(),
            $this->processHeaders()
        );
    }

    protected function processHeaders() : string {
        return array_reduce(
            array_keys($this->headers),
            function(string $carry, string $k) {
                return $carry .= "$k: {$this->headers[$k]}\r\n";
            },
            ''
        );
    }

    protected function processBody() : string {
        return "\r\nThis email requires a MIME compatible client\r\n".
               $this->processPart('text/plain', $this->plaintext).
               $this->processPart('text/html',  $this->html).
               "--$this->boundary--";
    }

    protected function processPart(string $type, string $body) : string {
        return "--$this->boundary\r\n".
               "Content-type: $type;charset=".self::CHARSET."\r\n".
               "\r\n".
               "$body\r\n";
    }
}
