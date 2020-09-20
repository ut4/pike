<?php

declare(strict_types=1);

namespace Pike\Extensions\Validation;

use Masterminds\{HTML5};
use Masterminds\HTML5\Parser\{DOMTreeBuilder, Scanner, Tokenizer};

abstract class SafeHTMLValidator {
    public const DEFAULT_ALLOWED_TAGS = [
        'abbr' => 'abbr',
        'b' => 'b',
        'blockquote' => 'blockquote',
        'br' => 'br',
        'caption' => 'caption',
        'cite' => 'cite',
        'div' => 'div',
        'em' => 'em',
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'h6' => 'h6',
        'hr' => 'hr',
        'i' => 'i',
        'li' => 'li',
        'ol' => 'ol',
        'p' => 'p',
        'pre' => 'pre',
        'q' => 'q',
        's' => 's',
        'small' => 'small',
        'span' => 'span',
        'strong' => 'strong',
        'sub' => 'sub',
        'sup' => 'sup',
        'table' => 'table',
        'tbody' => 'tbody',
        'td' => 'td',
        'tfoot' => 'tfoot',
        'th' => 'th',
        'thead' => 'thead',
        'tr' => 'tr',
        'ul' => 'ul',
    ];
    public const DEFAULT_ALLOWED_ATTRIBUTES = [
        'id',
        'class',
        'title'
    ];
    /**
     * @param mixed $html
     * @param string[] $allowedTags = self::DEFAULT_ALLOWED_TAGS
     * @param string[] $allowedAttributes = self::DEFAULT_ALLOWED_ATTRIBUTES
     * @return bool
     */
    public static function isSafeHTML($html,
                                      array $allowedTags = [],
                                      array $allowedAttributes = []): bool {
        if (!is_string($html)) return false;
        if (!$html) return true;
        // @allow \Exception
        $parser = new MyHTML5();
        $parser->loadHTMLFragment($html, [
            'allowedTags' => $allowedTags
                ? array_combine($allowedTags, $allowedTags)
                : self::DEFAULT_ALLOWED_TAGS,
            'allowedAttributes' => $allowedAttributes
                ? $allowedAttributes
                : self::DEFAULT_ALLOWED_ATTRIBUTES,
        ]);
        return !$parser->hasValidationOrParseErrors();
    }
}

final class MyHTML5 extends HTML5 {
    /** @inheritdoc */
    private $defaultOptions = [
        'encode_entities' => false,
        'disable_html_ns' => false,
    ];
    /** @var bool */
    private $hasValidationErrors = true;
    /**
     * @inheritdoc
     */
    public function parseFragment($input, array $options = array()) {
        $options = array_merge($this->defaultOptions, $options);
        $events = new MyDOMTreeBuilder(true, $options);
        $scanner = new Scanner($input, !empty($options['encoding']) ? $options['encoding'] : 'UTF-8');
        $parser = new Tokenizer($scanner, $events, !empty($options['xmlNamespaces']) ? Tokenizer::CONFORMANT_XML : Tokenizer::CONFORMANT_HTML);
        $parser->parse();
        $this->errors = $events->getErrors();
        $this->hasValidationErrors = $events->hasErrors();
        return $events->fragment();
    }
    /**
     * @return bool
     */
    public function hasValidationOrParseErrors(): bool {
        return $this->errors || $this->hasValidationErrors;
    }
}

final class MyDOMTreeBuilder extends DOMTreeBuilder {
    /** @var array<string, string> */
    private $allowedTags;
    /** @var string[] */
    private $allowedAttributes;
    /** @var int */
    private $numInvalidItems;
    /**
     * @inheritdoc
     */
    public function __construct($isFragment = false, array $options = array()) {
        parent::__construct($isFragment, $options);
        $this->allowedTags = $options['allowedTags'];
        $this->allowedAttributes = $options['allowedAttributes'];
        $this->numInvalidItems = 0;
    }
    /**
     * @inheritdoc
     */
    public function startTag($name, $attributes = [], $selfClosing = false) {
        $out = parent::startTag($name, $attributes, $selfClosing);
        if (!array_key_exists($name, $this->allowedTags)) {
            ++$this->numInvalidItems;
            return $out;
        }
        foreach ($attributes as $name => $_val){
            if (!in_array($name, $this->allowedAttributes))
                ++$this->numInvalidItems;
        }
        return $out;
    }
    /**
     * @inheritdoc
     */
    public function cdata($data) {
        parent::cdata($data);
        ++$this->numInvalidItems;
    }
    /**
     * @inheritdoc
     */
    public function processingInstruction($name, $data = null) {
        parent::processingInstruction($name, $data);
        ++$this->numInvalidItems;
    }
    /**
     * @inheritdoc
     */
    public function hasErrors(): bool {
        return $this->numInvalidItems > 0;
    }
}
