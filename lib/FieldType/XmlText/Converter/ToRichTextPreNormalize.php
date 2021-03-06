<?php

/**
 * File containing the namespace eZ\Publish\Core\FieldType\XmlText\Converter\ToRichTextPreNormalize class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\XmlText\Converter;

use DOMDocument;
use Ibexa\Contracts\FieldTypeRichText\RichText\Converter;

/**
 * Expands paragraphs and links embeds of a XML document in legacy ezxml format.
 *
 * Relies on XmlText's ExpandingToRichText, ExpandingSectionParagraph and EmbedLinking converters implementation.
 */
class ToRichTextPreNormalize implements Converter
{
    /**
     * @var \eZ\Publish\Core\FieldType\XmlText\Converter[]
     */
    protected $converters;

    /**
     * @var \eZ\Publish\Core\FieldType\XmlText\Converter\EmbedLinking
     */
    protected $embedLinkingConverter;

    /**
     * Construct from XmlText converters implementation.
     *
     * @param \eZ\Publish\Core\FieldType\XmlText\Converter[] $converters
     */
    public function __construct(array $converters)
    {
        $this->converters = $converters;
    }

    /**
     * Converts given $document into another \DOMDocument object.
     *
     * @param \DOMDocument $document
     *
     * @return \DOMDocument
     */
    public function convert(DOMDocument $document)
    {
        foreach ($this->converters as $converter) {
            $converter->convert($document);
        }

        return $document;
    }
}
