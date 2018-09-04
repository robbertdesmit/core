<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\ChildCountField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class SimpleDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'simple';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                new StringField('string_field', 'stringField'),
                new IntField('int_field', 'intField'),
                new FloatField('float_field', 'floatField'),
                new BoolField('bool_field', 'boolField'),
                new IdField('id_field', 'idField'),
                new ChildCountField(),

                (new StringField('required_field', 'requiredField'))->setFlags(new Required()),
                (new StringField('read_only_field', 'readOnlyField'))->setFlags(new ReadOnly()),
            ]
        );
    }
}
