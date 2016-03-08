<?php
namespace Ckcr;

class Token {
    const ARG_SPLIT   = 0x0001;
    const FILED_SPLIT = 0x0002;
    const OP_COLON    = 0x0004;
    const FIELD_COLON = 0x0008;
    const PIPE        = 0x0010;
    const STRING      = 0x0020;
    const NUMERIC     = 0x0040;
    const IDENTIFIER  = 0x0080;
    const FIELD       = 0x0100;
    const BLOK_START  = 0x0200;
    const BLOK_END    = 0x0400;
    const BOOL_TRUE   = 0x0800;
    const BOOL_FALSE  = 0x1000;
    const ISNULL      = 0x2000;
    const EMPTY_ARRAY = 0x4000;
    const EOF         = 0x8000;

    public $literal;
    public $type;
    public $pos;

    private function __construct($literal, $pos, $type) {
        $this->literal = $literal;
        $this->pos = $pos;
        $this->type = $type;
    }
    public static function buildToken($literal, $pos, $type) {
        return new self($literal, $pos, $type);
    }
    public function isValid($type) {
        return $this->type & $type;
    }

    public function __toString() {
        $typeStr = '[';
        if ($this->type & self::ARG_SPLIT) {
            $typeStr .= 'ARG_SPLIT ';
        } else if ($this->type & self::FILED_SPLIT) {
            $typeStr .= 'FILED_SPLIT ';
        } else if ($this->type & self::OP_COLON) {
            $typeStr .= 'OP_COLON ';
        } else if ($this->type & self::FIELD_COLON ) {
            $typeStr .= 'FIELD_COLON  ';
        } else if ($this->type & self::PIPE) {
            $typeStr .= 'PIPE ';
        } else if ($this->type & self::STRING) {
            $typeStr .= 'STRING ';
        } else if ($this->type & self::NUMERIC) {
            $typeStr .= 'NUMERIC ';
        } else if ($this->type & self::IDENTIFIER) {
            $typeStr .= 'IDENTIFIER ';
        } else if ($this->type & self::FIELD) {
            $typeStr .= 'FIELD ';
        } else if ($this->type & self::BLOK_START) {
            $typeStr .= 'BLOK_START ';
        } else if ($this->type & self::BLOK_END) {
            $typeStr .= 'BLOK_END ';
        } else if ($this->type & self::BOOL_TRUE) {
            $typeStr .= 'BOOL_TRUE ';
        } else if ($this->type & self::BOOL_FALSE) {
            $typeStr .= 'BOOL_FALSE ';
        } else if ($this->type & self::ISNULL) {
            $typeStr .= 'ISNULL ';
        } else if ($this->type & self::EMPTY_ARRAY) {
            $typeStr .= 'EMPTY_ARRAY ';
        } else if ($this->type & self::EOF) {
            $typeStr .= 'EOF ';
        }
        $typeStr[strlen($typeStr) - 1] = ']';
       
        return sprintf("%20s%20s [position: %6d]", $this->literal, $typeStr, $this->pos);
    }
}
