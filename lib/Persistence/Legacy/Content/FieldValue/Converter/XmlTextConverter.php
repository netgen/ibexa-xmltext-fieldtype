<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter;

use Ibexa\Core\FieldType\FieldSettings;
use eZ\Publish\Core\FieldType\XmlText\Type;
use eZ\Publish\Core\FieldType\XmlText\Value;
use Ibexa\Core\Persistence\Legacy\Content\FieldValue\Converter;
use Ibexa\Core\Persistence\Legacy\Content\StorageFieldDefinition;
use Ibexa\Core\Persistence\Legacy\Content\StorageFieldValue;
use Ibexa\Contracts\Core\Persistence\Content\FieldValue;
use Ibexa\Contracts\Core\Persistence\Content\Type\FieldDefinition;

class XmlTextConverter implements Converter
{
    /**
     * Converts data from $value to $storageFieldValue.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\FieldValue $value
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldValue $storageFieldValue
     */
    public function toStorageValue(FieldValue $value, StorageFieldValue $storageFieldValue)
    {
        $storageFieldValue->dataText = $value->data;
    }

    /**
     * Converts data from $value to $fieldValue.
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldValue $value
     * @param \eZ\Publish\SPI\Persistence\Content\FieldValue $fieldValue
     */
    public function toFieldValue(StorageFieldValue $value, FieldValue $fieldValue)
    {
        $fieldValue->data = $value->dataText ?: Value::EMPTY_VALUE;
    }

    /**
     * Converts field definition data from $fieldDefinition into $storageFieldDefinition.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\Type\FieldDefinition $fieldDefinition
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldDefinition $storageDefinition
     */
    public function toStorageFieldDefinition(FieldDefinition $fieldDefinition, StorageFieldDefinition $storageDefinition)
    {
        $storageDefinition->dataInt1 = $fieldDefinition->fieldTypeConstraints->fieldSettings['numRows'];
        $storageDefinition->dataText2 = $fieldDefinition->fieldTypeConstraints->fieldSettings['tagPreset'];

        if (!empty($fieldDefinition->defaultValue->data)) {
            $storageDefinition->dataText1 = $fieldDefinition->defaultValue->data;
        }
    }

    /**
     * Converts field definition data from $storageDefinition into $fieldDefinition.
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldDefinition $storageDefinition
     * @param \eZ\Publish\SPI\Persistence\Content\Type\FieldDefinition $fieldDefinition
     */
    public function toFieldDefinition(StorageFieldDefinition $storageDefinition, FieldDefinition $fieldDefinition)
    {
        $fieldDefinition->fieldTypeConstraints->fieldSettings = new FieldSettings(
            [
                'numRows' => $storageDefinition->dataInt1,
                'tagPreset' => $storageDefinition->dataText2 ? (int)$storageDefinition->dataText2 : Type::TAG_PRESET_DEFAULT,
            ]
        );

        $defaultValue = null;
        if (!empty($storageDefinition->dataText1)) {
            $defaultValue = $storageDefinition->dataText1;
        }

        $fieldDefinition->defaultValue->data = $defaultValue;
    }

    /**
     * Returns the name of the index column in the attribute table.
     *
     * Returns the name of the index column the datatype uses, which is either
     * "sort_key_int" or "sort_key_string". This column is then used for
     * filtering and sorting for this type.
     *
     * @return string|false
     */
    public function getIndexColumn()
    {
        return false;
    }
}
